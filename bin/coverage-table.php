<?php

declare(strict_types=1);

/**
 * Prints the README coverage table, generated from data/manifest.json.
 *
 *   php bin/coverage-table.php [--countries <file>] [--write README.md]
 *
 * --countries  A saved GET https://calcbloc.com/api/v1/countries response. Adds what the API serves for each
 *              country; countries the API does not list yet are shown without links.
 * --write      Replace the block between <!-- coverage:start --> and <!-- coverage:end --> in that file.
 */

$root = dirname(__DIR__);
$countriesFile = null;
$write = null;
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--countries' && isset($argv[$i + 1])) {
        $countriesFile = $argv[++$i];
    } elseif ($argv[$i] === '--write' && isset($argv[$i + 1])) {
        $write = $argv[++$i];
    } else {
        fwrite(STDERR, "Usage: php bin/coverage-table.php [--countries <file>] [--write README.md]\n");
        exit(1);
    }
}

$manifest = json_decode((string) file_get_contents($root . '/data/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$api = null;
if ($countriesFile !== null) {
    $api = [];
    $payload = json_decode((string) file_get_contents($countriesFile), true, 512, JSON_THROW_ON_ERROR);
    foreach ($payload['countries'] as $c) {
        $api[$c['code']] = $c;
    }
}

// Referral tag for the README's links to calcbloc.com (the bundled pageUrl values stay plain).
$readmeLink = static function (string $url): string {
    $parts = explode('#', $url, 2);
    $tagged = $parts[0] . (str_contains($parts[0], '?') ? '&' : '?') . 'utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss';
    return isset($parts[1]) ? $tagged . '#' . $parts[1] : $tagged;
};

$rows = [];
foreach ($manifest['countries'] as $c) {
    $live = $api === null || isset($api[$c['country']]);
    $name = $live ? sprintf('[%s](%s)', $c['name'], $readmeLink($c['pageUrl'])) : $c['name'];
    $regions = count($c['regions']);
    $regionText = $regions === 0 ? '-' : (string) $regions;
    $row = [$name, '`' . $c['country'] . '`', $c['currency'], implode(', ', $c['years']), $regionText, $c['lastVerified']];
    if ($api !== null) {
        if (!$live) {
            $row[] = 'bundled only (not yet on the API)';
        } else {
            $endpoints = array_values(array_filter($api[$c['country']]['available_endpoints'], static fn (string $e): bool => $e !== 'tax-tables'));
            $row[] = $endpoints === [] ? 'tax tables' : 'tax tables, ' . implode(', ', $endpoints);
        }
    }
    $rows[] = $row;
}
usort($rows, static fn (array $a, array $b): int => strcmp(strip_tags(preg_replace('~\[([^\]]+)\].*~', '$1', $a[0]) ?? ''), strip_tags(preg_replace('~\[([^\]]+)\].*~', '$1', $b[0]) ?? '')));

$header = ['Country', 'Code', 'Currency', 'Tax years', 'Regions', 'Last checked'];
if ($api !== null) {
    $header[] = 'API endpoints';
}
$lines = ['| ' . implode(' | ', $header) . ' |', '|' . str_repeat(' --- |', count($header))];
foreach ($rows as $row) {
    $lines[] = '| ' . implode(' | ', $row) . ' |';
}
$table = implode("\n", $lines) . "\n";

if ($write === null) {
    echo $table;
    exit(0);
}
$readme = (string) file_get_contents($write);
$pattern = '~(<!-- coverage:start -->\n).*?(<!-- coverage:end -->)~s';
if (!preg_match($pattern, $readme)) {
    fwrite(STDERR, "No coverage markers in $write.\n");
    exit(1);
}
file_put_contents($write, preg_replace_callback($pattern, static fn (array $m): string => $m[1] . $table . $m[2], $readme));
echo "Updated $write (" . count($rows) . " countries).\n";
