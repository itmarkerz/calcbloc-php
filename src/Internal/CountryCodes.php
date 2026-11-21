<?php

declare(strict_types=1);

namespace CalcBloc\Internal;

/**
 * Country identifiers accepted by the CalcBloc API (1.7): the short code (ISO 3166-1 alpha-2 in lower case, with the
 * United Kingdom as `uk` and `gb` as an alias), the ISO code, English and local names, and the older keys
 * (`new-zealand`), which are still the country keys of the data and of API responses.
 *
 * Mirrors COUNTRY_CODES and normKey() in the CalcBloc API (src/lib/api/country-codes.ts).
 *
 * @internal
 */
final class CountryCodes
{
    /**
     * Country key => [short code, ISO code, other names].
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public const CODES = [
        'us' => ['us', 'US', ['usa', 'united states', 'united states of america', 'america']],
        'uk' => ['uk', 'GB', ['gb', 'gbr', 'united kingdom', 'great britain', 'britain', 'england', 'scotland', 'wales', 'northern ireland']],
        'australia' => ['au', 'AU', ['aus']],
        'new-zealand' => ['nz', 'NZ', ['nzl', 'aotearoa']],
        'canada' => ['ca', 'CA', ['can']],
        'ireland' => ['ie', 'IE', ['irl', 'eire', 'republic of ireland']],
        'india' => ['in', 'IN', ['ind', 'bharat']],
        'germany' => ['de', 'DE', ['deu', 'deutschland']],
        'spain' => ['es', 'ES', ['esp', 'espana']],
        'netherlands' => ['nl', 'NL', ['nld', 'the netherlands', 'holland', 'nederland']],
        'france' => ['fr', 'FR', ['fra']],
        'singapore' => ['sg', 'SG', ['sgp']],
        'uae' => ['ae', 'AE', ['are', 'united arab emirates', 'emirates']],
        'italy' => ['it', 'IT', ['ita', 'italia']],
        'portugal' => ['pt', 'PT', ['prt']],
        'belgium' => ['be', 'BE', ['bel', 'belgie', 'belgique']],
        'switzerland' => ['ch', 'CH', ['che', 'schweiz', 'suisse', 'svizzera']],
        'denmark' => ['dk', 'DK', ['dnk', 'danmark']],
        'sweden' => ['se', 'SE', ['swe', 'sverige']],
        'austria' => ['at', 'AT', ['aut', 'osterreich']],
        'poland' => ['pl', 'PL', ['pol', 'polska']],
        'luxembourg' => ['lu', 'LU', ['lux']],
        'czechia' => ['cz', 'CZ', ['cze', 'czech republic', 'cesko']],
        'norway' => ['no', 'NO', ['nor', 'norge']],
        'finland' => ['fi', 'FI', ['fin', 'suomi']],
        'slovenia' => ['si', 'SI', ['svn', 'slovenija']],
        'romania' => ['ro', 'RO', ['rou']],
        'hungary' => ['hu', 'HU', ['hun', 'magyarorszag']],
        'slovakia' => ['sk', 'SK', ['svk', 'slovensko']],
        'greece' => ['gr', 'GR', ['grc', 'hellas', 'ελλαδα']],
        'latvia' => ['lv', 'LV', ['lva', 'latvija']],
        'croatia' => ['hr', 'HR', ['hrv', 'hrvatska']],
        'estonia' => ['ee', 'EE', ['est', 'eesti']],
        'bulgaria' => ['bg', 'BG', ['bgr', 'българия']],
        'lithuania' => ['lt', 'LT', ['ltu', 'lietuva']],
        'malta' => ['mt', 'MT', ['mlt']],
        'cyprus' => ['cy', 'CY', ['cyp', 'κυπρος']],
        'iceland' => ['is', 'IS', ['isl', 'island']],
        'ukraine' => ['ua', 'UA', ['ukr', 'ukraina', 'україна']],
        'liechtenstein' => ['li', 'LI', ['lie']],
        'serbia' => ['rs', 'RS', ['srb', 'srbija', 'србија']],
        'turkey' => ['tr', 'TR', ['tur', 'turkiye', 'türkiye']],
    ];

    /** Accented Latin letters and their base letters, used when ext-intl (Normalizer) is not loaded. */
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
        'ė' => 'e', 'ę' => 'e', 'ě' => 'e', 'ģ' => 'g', 'ğ' => 'g', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ī' => 'i', 'į' => 'i', 'ķ' => 'k', 'ĺ' => 'l', 'ļ' => 'l', 'ľ' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ņ' => 'n',
        'ň' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o', 'ŕ' => 'r', 'ř' => 'r',
        'ś' => 's', 'ş' => 's', 'ș' => 's', 'š' => 's', 'ţ' => 't', 'ț' => 't', 'ť' => 't', 'ù' => 'u', 'ú' => 'u',
        'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z',
        'ż' => 'z', 'ž' => 'z', 'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
        'й' => 'и', 'ї' => 'і',
    ];

    /** @var array<string, string>|null Normalised alias => country key. */
    private static ?array $aliases = null;

    /**
     * Lower case, accents removed, spaces, dots and underscores turned into hyphens (normKey() in the API).
     */
    public static function normalise(string $value): string
    {
        $value = trim($value);
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_KD);
            if (is_string($decomposed)) {
                $value = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
            }
        }
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = strtr($value, self::ACCENTS);

        return (string) preg_replace('/[\s_.]+/u', '-', $value);
    }

    /**
     * The country key (`new-zealand`) for any accepted identifier (`nz`, `NZ`, `New Zealand`, `new-zealand`,
     * `gb`, ...), or null when it is not one of them.
     */
    public static function key(string $value): ?string
    {
        if (self::$aliases === null) {
            self::$aliases = [];
            foreach (self::CODES as $key => [$short, $iso, $names]) {
                foreach ([$key, $short, $iso, ...$names] as $alias) {
                    self::$aliases[self::normalise($alias)] = $key;
                }
            }
        }

        return self::$aliases[self::normalise($value)] ?? null;
    }
}
