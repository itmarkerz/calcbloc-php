<?php

declare(strict_types=1);

namespace CalcBloc\Internal;

use CalcBloc\CalcBlocException;

/**
 * JSON helpers shared by the loader, the API client and the sync script.
 *
 * @internal
 */
final class Json
{
    public const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * Compact, stable encoding: the form the bundled data files are written in and the form their checksums use.
     */
    public static function encode(mixed $value): string
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');
        try {
            return json_encode($value, self::ENCODE_FLAGS);
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', $previous);
            }
        }
    }

    /**
     * @return array<mixed>
     */
    public static function decode(string $json, string $context): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new CalcBlocException(sprintf('%s is not valid JSON: %s', $context, $e->getMessage()), 'invalid_json', null, null, $e);
        }
        if (!is_array($data)) {
            throw new CalcBlocException(sprintf('%s is not a JSON object.', $context), 'invalid_json');
        }

        return $data;
    }

    /**
     * @return array<mixed>
     */
    public static function decodeFile(string $path): array
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new CalcBlocException(sprintf('Cannot read %s.', $path), 'file_not_readable');
        }

        return self::decode($json, $path);
    }
}
