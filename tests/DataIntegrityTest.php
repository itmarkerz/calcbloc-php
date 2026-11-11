<?php

declare(strict_types=1);

namespace CalcBloc\Tests;

use CalcBloc\Internal\Json;
use CalcBloc\Tables;
use CalcBloc\TaxTable;
use PHPUnit\Framework\TestCase;

/**
 * The bundled files leave out the flat `rows` array, which the loader rebuilds. This test proves the loader gives
 * back each source table exactly: tests/data-integrity.json holds the SHA-256 of every source file's canonical JSON,
 * written by bin/sync-tables.php.
 */
final class DataIntegrityTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private static function checksums(): array
    {
        $data = Json::decodeFile(__DIR__ . '/data-integrity.json');
        self::assertIsArray($data['files'] ?? null);
        /** @var array<string, string> $files */
        $files = $data['files'];

        return $files;
    }

    public function testEveryBundledFileHasAChecksum(): void
    {
        $listed = ['manifest.json'];
        foreach (Tables::listCountries() as $c) {
            array_push($listed, ...$c->files);
        }
        sort($listed);
        $checksums = array_keys(self::checksums());
        sort($checksums);
        self::assertSame($checksums, $listed);
    }

    public function testLoaderRestoresEverySourceTableExactly(): void
    {
        foreach (self::checksums() as $file => $expected) {
            $raw = Json::decodeFile(Tables::directory() . '/' . $file);
            /** @var array<string, mixed> $raw */
            $restored = $file === 'manifest.json' ? $raw : TaxTable::fromArray($raw)->toArray();
            self::assertSame($expected, hash('sha256', Json::encode($restored)), "$file differs from its source");
        }
    }
}
