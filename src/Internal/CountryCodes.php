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
        'japan' => ['jp', 'JP', ['japan', 'jpn', 'nippon', 'nihon', '日本']],
        'south-korea' => ['kr', 'KR', ['south korea', 'kor', 'korea', 'republic of korea', '대한민국', '한국']],
        'mexico' => ['mx', 'MX', ['mexico', 'mex', 'méxico', 'estados unidos mexicanos']],
        'brazil' => ['br', 'BR', ['brazil', 'bra', 'brasil', 'república federativa do brasil']],
        'chile' => ['cl', 'CL', ['chile', 'chl', 'república de chile']],
        'colombia' => ['co', 'CO', ['colombia', 'col', 'república de colombia']],
        'argentina' => ['ar', 'AR', ['argentina', 'arg', 'república argentina']],
        'costa-rica' => ['cr', 'CR', ['costa rica', 'cri', 'república de costa rica']],
        'saudi-arabia' => ['sa', 'SA', ['saudi arabia', 'sau', 'ksa', 'saudi', 'kingdom of saudi arabia', 'المملكة العربية السعودية', 'السعودية']],
        'qatar' => ['qa', 'QA', ['qatar', 'qat', 'state of qatar', 'دولة قطر', 'قطر']],
        'kuwait' => ['kw', 'KW', ['kuwait', 'kwt', 'state of kuwait', 'دولة الكويت', 'الكويت']],
        'bahrain' => ['bh', 'BH', ['bahrain', 'bhr', 'kingdom of bahrain', 'مملكة البحرين', 'البحرين']],
        'oman' => ['om', 'OM', ['oman', 'omn', 'sultanate of oman', 'سلطنة عمان', 'عمان']],
        'israel' => ['il', 'IL', ['israel', 'isr', 'state of israel', 'ישראל', 'מדינת ישראל']],
        'south-africa' => ['za', 'ZA', ['south africa', 'zaf', 'rsa', 'republic of south africa', 'suid-afrika']],
        'indonesia' => ['id', 'ID', ['indonesia', 'idn', 'republik indonesia']],
        'china' => ['cn', 'CN', ['china', 'chn', 'prc', 'people\'s republic of china', '中国', '中华人民共和国']],
        'malaysia' => ['my', 'MY', ['malaysia', 'mys', 'persekutuan malaysia']],
        'thailand' => ['th', 'TH', ['thailand', 'tha', 'kingdom of thailand', 'ประเทศไทย', 'ไทย']],
        'vietnam' => ['vn', 'VN', ['vietnam', 'vnm', 'viet nam', 'việt nam', 'socialist republic of vietnam']],
        'philippines' => ['ph', 'PH', ['philippines', 'phl', 'pilipinas', 'republic of the philippines']],
        'pakistan' => ['pk', 'PK', ['pakistan', 'pak', 'islamic republic of pakistan', 'پاکستان']],
        'bangladesh' => ['bd', 'BD', ['bangladesh', 'bgd', 'people\'s republic of bangladesh', 'বাংলাদেশ']],
        'sri-lanka' => ['lk', 'LK', ['sri lanka', 'lka', 'democratic socialist republic of sri lanka', 'ceylon', 'ශ්‍රී ලංකාව', 'இலங்கை']],
        'nepal' => ['np', 'NP', ['nepal', 'npl', 'federal democratic republic of nepal', 'नेपाल']],
        'jordan' => ['jo', 'JO', ['jordan', 'jor', 'hashemite kingdom of jordan', 'المملكة الأردنية الهاشمية', 'الأردن']],
        'tunisia' => ['tn', 'TN', ['tunisia', 'tun', 'republic of tunisia', 'tunisie', 'الجمهورية التونسية', 'تونس']],
        'algeria' => ['dz', 'DZ', ['algeria', 'dza', 'people\'s democratic republic of algeria', 'algérie', 'الجزائر', 'الجمهورية الجزائرية الديمقراطية الشعبية']],
        'paraguay' => ['py', 'PY', ['paraguay', 'pry', 'república del paraguay', 'tetã paraguái']],
        'bolivia' => ['bo', 'BO', ['bolivia', 'bol', 'estado plurinacional de bolivia', 'plurinational state of bolivia']],
        'el-salvador' => ['sv', 'SV', ['el salvador', 'slv', 'república de el salvador', 'republic of el salvador']],
        'honduras' => ['hn', 'HN', ['honduras', 'hnd', 'república de honduras', 'republic of honduras']],
        'jamaica' => ['jm', 'JM', ['jamaica', 'jam']],
        'trinidad-and-tobago' => ['tt', 'TT', ['trinidad and tobago', 'tto', 'trinidad', 'tobago', 'republic of trinidad and tobago']],
        'egypt' => ['eg', 'EG', ['egypt', 'egy', 'arab republic of egypt', 'misr', 'مصر', 'جمهورية مصر العربية']],
        'nigeria' => ['ng', 'NG', ['nigeria', 'nga', 'federal republic of nigeria']],
        'kenya' => ['ke', 'KE', ['kenya', 'ken', 'republic of kenya', 'jamhuri ya kenya']],
        'morocco' => ['ma', 'MA', ['morocco', 'mar', 'kingdom of morocco', 'maroc', 'royaume du maroc', 'المغرب', 'المملكة المغربية']],
        'ghana' => ['gh', 'GH', ['ghana', 'gha', 'republic of ghana']],
        'peru' => ['pe', 'PE', ['peru', 'per', 'perú', 'republica del peru', 'república del perú']],
        'uruguay' => ['uy', 'UY', ['uruguay', 'ury', 'república oriental del uruguay', 'republica oriental del uruguay']],
        'ecuador' => ['ec', 'EC', ['ecuador', 'ecu', 'república del ecuador', 'republica del ecuador']],
        'panama' => ['pa', 'PA', ['panama', 'pan', 'panamá', 'república de panamá', 'republica de panama']],
        'dominican-republic' => ['do', 'DO', ['dominican republic', 'dom', 'república dominicana', 'republica dominicana', 'rd']],
        'guatemala' => ['gt', 'GT', ['guatemala', 'gtm', 'república de guatemala', 'republica de guatemala']],
        'kazakhstan' => ['kz', 'KZ', ['kazakhstan', 'kaz', 'qazaqstan', 'казахстан', 'қазақстан']],
        'georgia' => ['ge', 'GE', ['georgia', 'geo', 'sakartvelo', 'საქართველო']],
        'armenia' => ['am', 'AM', ['armenia', 'arm', 'hayastan', 'հայաստան']],
        'azerbaijan' => ['az', 'AZ', ['azerbaijan', 'aze', 'azerbaycan', 'azərbaycan']],
        'uzbekistan' => ['uz', 'UZ', ['uzbekistan', 'uzb', 'ozbekiston', 'oʻzbekiston', 'ўзбекистон']],
        'russia' => ['ru', 'RU', ['russia', 'rus', 'russian federation', 'россия', 'российская федерация']],
        'tanzania' => ['tz', 'TZ', ['tanzania', 'tza', 'united republic of tanzania', 'jamhuri ya muungano wa tanzania']],
        'uganda' => ['ug', 'UG', ['uganda', 'uga', 'republic of uganda', 'jamhuri ya uganda']],
        'rwanda' => ['rw', 'RW', ['rwanda', 'rwa', 'republic of rwanda', 'repubulika y\'u rwanda', 'république du rwanda']],
        'ethiopia' => ['et', 'ET', ['ethiopia', 'eth', 'federal democratic republic of ethiopia', 'ኢትዮጵያ']],
        'mauritius' => ['mu', 'MU', ['mauritius', 'mus', 'republic of mauritius', 'maurice', 'république de maurice']],
        'botswana' => ['bw', 'BW', ['botswana', 'bwa', 'republic of botswana', 'lefatshe la botswana']],
        'montenegro' => ['me', 'ME', ['montenegro', 'mne', 'crna gora', 'црна гора']],
        'north-macedonia' => ['mk', 'MK', ['north macedonia', 'mkd', 'macedonia', 'republic of north macedonia', 'северна македонија', 'maqedonia e veriut']],
        'albania' => ['al', 'AL', ['albania', 'alb', 'shqiperia', 'shqipëria', 'republika e shqiperise']],
        'bosnia-and-herzegovina' => ['ba', 'BA', ['bosnia and herzegovina', 'bih', 'bosnia', 'bosna i hercegovina', 'босна и херцеговина']],
        'moldova' => ['md', 'MD', ['moldova', 'mda', 'republic of moldova', 'republica moldova']],
        'kosovo' => ['xk', 'XK', ['kosovo', 'xkx', 'kosova', 'kosovë', 'republic of kosovo', 'republika e kosovës']],
        'andorra' => ['ad', 'AD', ['andorra', 'and', 'principat d\'andorra', 'principality of andorra']],
        'monaco' => ['mc', 'MC', ['monaco', 'mco', 'principality of monaco', 'principauté de monaco']],
        'san-marino' => ['sm', 'SM', ['san marino', 'smr', 'repubblica di san marino', 'republic of san marino']],
        'hong-kong' => ['hk', 'HK', ['hong kong', 'hkg', 'hong kong sar', 'hong kong sar china', 'hksar', '香港']],
        'taiwan' => ['tw', 'TW', ['taiwan', 'twn', 'republic of china', 'roc', '臺灣', '台灣', '中華民國']],
        'macao' => ['mo', 'MO', ['macao', 'mac', 'macau', 'macao sar', 'macao sar china', '澳門', '澳门']],
        'cambodia' => ['kh', 'KH', ['cambodia', 'khm', 'kingdom of cambodia', 'កម្ពុជា']],
        'brunei' => ['bn', 'BN', ['brunei', 'brn', 'brunei darussalam', 'negara brunei darussalam']],
        'cote-divoire' => ['ci', 'CI', ['côte d’ivoire', 'civ', 'ivory coast', 'cote d\'ivoire', 'côte d\'ivoire', 'republic of côte d\'ivoire', 'république de côte d\'ivoire']],
        'senegal' => ['sn', 'SN', ['senegal', 'sen', 'sénégal', 'republic of senegal', 'république du sénégal']],
        'cameroon' => ['cm', 'CM', ['cameroon', 'cmr', 'cameroun', 'republic of cameroon', 'république du cameroun']],
        'zambia' => ['zm', 'ZM', ['zambia', 'zmb', 'republic of zambia']],
        'zimbabwe' => ['zw', 'ZW', ['zimbabwe', 'zwe', 'republic of zimbabwe']],
        'namibia' => ['na', 'NA', ['namibia', 'nam', 'republic of namibia']],
        'bahamas' => ['bs', 'BS', ['bahamas', 'bhs', 'the bahamas', 'commonwealth of the bahamas']],
        'barbados' => ['bb', 'BB', ['barbados', 'brb', 'bajan']],
        'belize' => ['bz', 'BZ', ['belize', 'blz', 'british honduras']],
        'guyana' => ['gy', 'GY', ['guyana', 'guy', 'co-operative republic of guyana']],
        'suriname' => ['sr', 'SR', ['suriname', 'sur', 'republiek suriname', 'republic of suriname']],
        'haiti' => ['ht', 'HT', ['haiti', 'hti', 'haïti', 'ayiti', 'republic of haiti', 'république d\'haïti']],
        'kyrgyzstan' => ['kg', 'KG', ['kyrgyzstan', 'kgz', 'kyrgyz republic', 'kirghizia', 'кыргызстан', 'кыргыз республикасы', 'киргизия']],
        'tajikistan' => ['tj', 'TJ', ['tajikistan', 'tjk', 'republic of tajikistan', 'тоҷикистон', 'таджикистан', 'ҷумҳурии тоҷикистон']],
        'maldives' => ['mv', 'MV', ['maldives', 'mdv', 'republic of maldives', 'ދިވެހިރާއްޖެ', 'dhivehi raajje']],
        'bhutan' => ['bt', 'BT', ['bhutan', 'btn', 'kingdom of bhutan', 'druk yul', 'འབྲུག་ཡུལ།']],
        'myanmar' => ['mm', 'MM', ['myanmar', 'mmr', 'burma', 'republic of the union of myanmar', 'မြန်မာ']],
        'madagascar' => ['mg', 'MG', ['madagascar', 'mdg', 'republic of madagascar', 'repoblikan\'i madagasikara', 'madagasikara', 'république de madagascar']],
        'dr-congo' => ['cd', 'CD', ['dr congo', 'cod', 'democratic republic of the congo', 'drc', 'congo-kinshasa', 'république démocratique du congo', 'rdc']],
        'congo' => ['cg', 'CG', ['republic of the congo', 'cog', 'congo-brazzaville', 'république du congo', 'congo brazzaville']],
        'malawi' => ['mw', 'MW', ['malawi', 'mwi', 'republic of malawi', 'malaŵi', 'dziko la malaŵi']],
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
