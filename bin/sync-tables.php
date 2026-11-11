<?php

declare(strict_types=1);

/**
 * Copies the CalcBloc tax tables into this package's data/ directory.
 *
 *   php bin/sync-tables.php [--from <dir>] [--to <dir>] [--national-only] [--keep-rows]
 *   composer sync-tables -- [options]
 *
 * --from           Directory with manifest.json and <country>/<year>.json (+ <country>/<year>/<region>.json) files.
 *                  Default: $CALCBLOC_TABLES_DIR, else ../calcbloc-data/tables (the sibling calcbloc-data repository).
 * --to             Destination. Default: data/ in this repository. Its JSON files and sub-directories are replaced.
 * --national-only  Skip the regional files.
 * --keep-rows      Keep the flat `rows` array in every file instead of rebuilding it when a table is loaded.
 *
 * Every source file has the exact shape of GET /api/v1/tax-tables/{country}/{year}[?region=]. Files are written as
 * compact JSON. The flat `rows` array repeats data that is already in `sections`, so it is left out and rebuilt by the
 * loader (CalcBloc\Internal\RowBuilder) - but only when the rebuild reproduces it exactly; otherwise the file keeps
 * its rows. The SHA-256 of every source file's canonical JSON goes to tests/data-integrity.json, and the test suite
 * checks that the loader restores each table exactly.
 *
 * The country list always comes from manifest.json: nothing in this script names a country.
 */

require __DIR__ . '/../src/CalcBlocException.php';
require __DIR__ . '/../src/Internal/Json.php';
require __DIR__ . '/../src/Internal/RowBuilder.php';

use CalcBloc\Internal\Json;
use CalcBloc\Internal\RowBuilder;

$root = dirname(__DIR__);

/** @return array{from: string, to: string, nationalOnly: bool, keepRows: bool} */
function parseArgs(array $argv, string $root): array
{
    $env = getenv('CALCBLOC_TABLES_DIR');
    $opts = [
        'from' => $env !== false && $env !== '' ? $env : $root . '/../calcbloc-data/tables',
        'to' => $root . '/data',
        'nationalOnly' => false,
        'keepRows' => false,
    ];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        switch ($arg) {
            case '--from':
            case '--to':
                if (!isset($argv[$i + 1])) {
                    fail("$arg needs a directory.");
                }
                $opts[substr($arg, 2)] = $argv[++$i];
                break;
            case '--national-only':
                $opts['nationalOnly'] = true;
                break;
            case '--keep-rows':
                $opts['keepRows'] = true;
                break;
            case '--help':
            case '-h':
                fwrite(STDOUT, "Usage: php bin/sync-tables.php [--from <dir>] [--to <dir>] [--national-only] [--keep-rows]\n");
                exit(0);
            default:
                fail("Unknown argument: $arg");
        }
    }

    return $opts;
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/** Removes the JSON files and sub-directories of $dir (other files, such as LICENSE, stay). */
function clearDataDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);

        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_dir($path)) {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($path);
        } elseif (str_ends_with($name, '.json')) {
            unlink($path);
        }
    }
}

/** @return list<string> problems */
function checkTable(array $t): array
{
    $problems = [];
    foreach (['country', 'year', 'currency', 'sections', 'sources', 'rows'] as $k) {
        if (!array_key_exists($k, $t)) {
            $problems[] = "missing \"$k\"";
        }
    }
    foreach (($t['rows'] ?? []) as $i => $r) {
        $where = sprintf('row %d (%s "%s")', $i, $r['section_id'] ?? '?', $r['label'] ?? '');
        if (!preg_match('~^https?://\S+\.\S+~', (string) ($r['source_url'] ?? ''))) {
            $problems[] = "$where: no source_url";
        }
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($r['retrieved_at'] ?? ''))) {
            $problems[] = "$where: no retrieved_at";
        }
    }

    return $problems;
}

$opts = parseArgs($argv, $root);
$from = rtrim(str_replace('\\', '/', realpath($opts['from']) ?: $opts['from']), '/');
$to = rtrim(str_replace('\\', '/', $opts['to']), '/');
$manifestPath = $from . '/manifest.json';
if (!is_file($manifestPath)) {
    fail("No manifest.json in $from.\nPass --from <dir> or set CALCBLOC_TABLES_DIR (the calcbloc-data repository's tables/ directory).");
}
if (realpath($from) === realpath($to)) {
    fail('--to must differ from --from.');
}

$manifest = Json::decodeFile($manifestPath);
if (!isset($manifest['countries']) || !is_array($manifest['countries']) || $manifest['countries'] === []) {
    fail('manifest.json has no countries.');
}

clearDataDir($to);
$integrity = [];
$bytesIn = filesize($manifestPath);
$stripped = 0;
$kept = 0;
$files = 0;
$listed = [];

foreach ($manifest['countries'] as $ci => $country) {
    $code = $country['country'] ?? null;
    if (!is_string($code) || !preg_match('~^[a-z0-9-]+$~', $code)) {
        fail("manifest.json: country #$ci has no valid code.");
    }
    $paths = $country['files'] ?? null;
    if (!is_array($paths) || $paths === []) {
        fail("manifest.json: $code lists no files.");
    }
    $bundled = [];
    foreach ($paths as $rel) {
        if (!is_string($rel) || !preg_match('~^' . preg_quote($code, '~') . '/[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)?\.json$~', $rel)) {
            fail("manifest.json: $code has an unexpected file path " . json_encode($rel));
        }
        $listed[$rel] = true;
        $regional = substr_count($rel, '/') === 2;
        if ($regional && $opts['nationalOnly']) {
            continue;
        }
        $src = $from . '/' . $rel;
        if (!is_file($src)) {
            fail("manifest.json lists $rel, but $src does not exist.");
        }
        $bytesIn += filesize($src);
        $table = Json::decodeFile($src);
        $problems = checkTable($table);
        if ($problems !== []) {
            fail("$rel: " . implode('; ', array_slice($problems, 0, 10)) . (count($problems) > 10 ? sprintf(' (+%d more)', count($problems) - 10) : ''));
        }
        $integrity[$rel] = hash('sha256', Json::encode($table));

        $out = $table;
        if (!$opts['keepRows']) {
            $withoutRows = $table;
            unset($withoutRows['rows']);
            $restored = RowBuilder::withRows($withoutRows, RowBuilder::build($withoutRows));
            if ($restored === $table) {
                $out = $withoutRows;
                $stripped++;
            } else {
                $kept++;
                fwrite(STDERR, "note: $rel keeps its rows (the rebuild would differ).\n");
            }
        }
        $dest = $to . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0777, true);
        }
        file_put_contents($dest, Json::encode($out) . "\n");
        $bundled[] = $rel;
        $files++;
    }
    $manifest['countries'][$ci]['files'] = $bundled;
}

// Files on disk that the manifest does not list are not copied: the manifest is the contract.
$unlisted = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($from) + 1);
    if (str_ends_with($rel, '.json') && $rel !== 'manifest.json' && !isset($listed[$rel])) {
        $unlisted[] = $rel;
    }
}
if ($unlisted !== []) {
    fwrite(STDERR, sprintf("warning: %d file(s) not listed in manifest.json were skipped: %s\n", count($unlisted), implode(', ', array_slice($unlisted, 0, 5))));
}

$integrity['manifest.json'] = hash('sha256', Json::encode($manifest));
file_put_contents($to . '/manifest.json', Json::encode($manifest) . "\n");

// The data licence (CC BY 4.0) travels with the data when the source repository has it.
$licence = dirname($from) . '/LICENSE';
if (is_file($licence) && str_contains((string) file_get_contents($licence), 'Attribution 4.0 International')) {
    copy($licence, $to . '/LICENSE');
}

ksort($integrity);
$integrityFile = $root . '/tests/data-integrity.json';
if ($to === rtrim(str_replace('\\', '/', $root), '/') . '/data') {
    file_put_contents($integrityFile, json_encode([
        'comment' => 'SHA-256 of the canonical JSON (CalcBloc\\Internal\\Json::encode) of each source file. Written by bin/sync-tables.php; checked by tests/DataIntegrityTest.php.',
        'files' => $integrity,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

$bytesOut = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($to, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $bytesOut += $f->getSize();
}
printf(
    "Synced %d countries, %d table files from %s\n  rows rebuilt at load time: %d, rows kept: %d\n  %s in, %s out (data/ total, incl. manifest and licence)\n",
    count($manifest['countries']),
    $files,
    $from,
    $stripped,
    $kept,
    number_format($bytesIn / 1048576, 2) . ' MB',
    number_format($bytesOut / 1048576, 2) . ' MB',
);
