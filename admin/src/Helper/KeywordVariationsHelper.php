<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

namespace Phoca\Component\PhocaSeo\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
// use Joomla\CMS\Language\Language;
// use Joomla\CMS\Filesystem\File;
// use Joomla\CMS\Filesystem\Path;
use Phoca\Component\PhocaSeo\Administrator\Ai\AiClientFactory;
use Phoca\Component\PhocaSeo\Administrator\Helper\JsonFixerHelper;

/**
 * Keyword Variations Helper
 *
 * Generates keyword variations for inflected languages to improve keyword matching.
 * Supports multiple strategies:
 * 1. Language-specific suffix patterns (fast, offline)
 * 2. AI-powered variations (accurate, requires API)
 * 3. Custom JSON rules per language
 *
 * @since  6.0.0
 */
class KeywordVariationsHelper
{
    /**
     * Cache for generated variations to avoid repeated processing
     *
     * @var array
     */
    private static $variationsCache = [];

    /**
     * Cache for language pattern files
     *
     * @var array
     */
    private static $languagePatternsCache = [];

    /**
     * Check if keyword variations feature is enabled
     *
     * @return  boolean
     * @since   6.0.0
     */
    public static function isEnabled(): bool
    {
        $params = ComponentHelper::getParams('com_phocaseo');
        return (bool) $params->get('enable_keyword_variations', false);
    }

    /**
     * Get the variation strategy from configuration
     *
     * @return  string  'suffix', 'ai', or 'hybrid'
     * @since   6.0.0
     */
    public static function getStrategy(): string
    {
        $params = ComponentHelper::getParams('com_phocaseo');
        return $params->get('variation_strategy', 'suffix');
    }

    /**
     * Get the language code to use for variations
     * Can be overridden in config, otherwise uses site language
     *
     * @return  string  Language code (e.g., 'cs', 'sk', 'pl')
     * @since   6.0.0
     */
    public static function getLanguageCode(): string
    {
        $app    = Factory::getApplication();
        $input  = $app->input;
        $params = ComponentHelper::getParams('com_phocaseo');

        // 1. Priority: Check the language assigned to the specific item (article)
        // 'language' is the field name in com_content and most Joomla components
        $itemLang = $input->get('language', '');

        // If we are in the edit form, the data might be in the 'jform' array
        if (empty($itemLang)) {
            $jForm = $input->get('jform', [], 'array');
            $itemLang = $jForm['language'] ?? '';
        }

        // If a specific language is set (and it's not '*' for All)
        if (!empty($itemLang) && $itemLang !== '*') {
            return strtolower(explode('-', $itemLang)[0]);
        }

        // 2. Secondary: Configuration preference
        $configLang = $params->get('variation_language', '');
        if (!empty($configLang)) {
            return strtolower($configLang);
        }

        // 3. Fallback: Site default language
        $lang    = $app->getLanguage();
        $langTag = $lang->getTag(); // e.g., 'en-GB'
        return strtolower(explode('-', $langTag)[0]); // 'en'
    }

    /**
     * Generate keyword variations using the configured strategy
     *
     * @param   string  $keyword  The focus keyword
     * @param   string  $lang     Optional language override
     *
     * @return  array   Array of keyword variations (including original)
     * @since   6.0.0
     */
    public static function generate(string $keyword, string $lang = ''): array
    {
        if (!self::isEnabled()) {
            return [trim(strtolower($keyword))];
        }

        if (empty($keyword)) {
            return [];
        }

        // Use cache if available
        $cacheKey = md5($keyword . ($lang ?: self::getLanguageCode()));
        if (isset(self::$variationsCache[$cacheKey])) {
            return self::$variationsCache[$cacheKey];
        }

        $lang = $lang ?: self::getLanguageCode();
        $strategy = self::getStrategy();

        $variations = [];

        switch ($strategy) {
            case 'ai':
                $variations = self::generateUsingAi($keyword, $lang);
                break;

            case 'hybrid':
                // Try AI first, fallback to suffix-based
                try {
                    $variations = self::generateUsingAi($keyword, $lang);
                } catch (\Exception $e) {
                    $variations = self::generateUsingSuffixes($keyword, $lang);
                }
                break;

            case 'suffix':
            default:
                $variations = self::generateUsingSuffixes($keyword, $lang);
                break;
        }

        // Cache and return
        self::$variationsCache[$cacheKey] = $variations;
        return $variations;
    }

    /**
     * Generate variations using AI model
     *
     * @param   string  $keyword  The focus keyword
     * @param   string  $lang     Language code
     *
     * @return  array   Array of variations
     * @throws  \Exception
     * @since   6.0.0
     */
    private static function generateUsingAi(string $keyword, string $lang): array
    {
        $languageNames = [
            'cs' => 'Czech',
            'sk' => 'Slovak',
            'pl' => 'Polish',
            'ru' => 'Russian',
            'de' => 'German',
            'es' => 'Spanish',
            'it' => 'Italian',
            'fr' => 'French',
            'pt' => 'Portuguese',
            'en' => 'English',
        ];

        $languageName = $languageNames[$lang] ?? 'the detected language';

        $aiClient = AiClientFactory::create();

        $prompt = <<<PROMPT
Generate all grammatical variations (inflections, declensions, conjugations) for the following keyword in {$languageName}.

Keyword: "{$keyword}"

Include:
- All grammatical cases (nominative, genitive, dative, accusative, etc.)
- Singular and plural forms
- Different gender forms (if applicable)
- Common usage variations

Return ONLY a valid JSON array of strings, no explanations:
["variation1", "variation2", "variation3", ...]

Example for Czech "nabídka":
["nabídka", "nabídky", "nabídku", "nabídce", "nabídkou", "nabídkách", "nabídkami", "nabídek", "nabíd"]

Return ONLY the JSON array.
Ensure the JSON is complete and valid even if you reach token limit."
PROMPT;

        $response = $aiClient->sendRequest($prompt, true);
        $response = JsonFixerHelper::fix($response);

        // Extract JSON array
        $start = strpos($response, '[');
        $end = strrpos($response, ']');

        if ($start === false || $end === false) {
            throw new \Exception('AI did not return valid JSON array');
        }

        $json = substr($response, $start, $end - $start + 1);
        $variations = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($variations)) {
            throw new \Exception('AI returned invalid format');
        }

        // Ensure original keyword is included
        $variations[] = strtolower($keyword);

        return array_values(array_unique(array_map('strtolower', array_map('trim', $variations))));
    }

    /**
     * Generate variations using suffix-based patterns
     *
     * @param   string  $keyword  The focus keyword
     * @param   string  $lang     Language code
     *
     * @return  array   Array of variations
     * @since   6.0.0
     */
    private static function generateUsingSuffixes(string $keyword, string $lang): array
    {
        $keyword = trim(strtolower($keyword));
        $variations = [$keyword];

        // For multi-word keywords, include individual words
        $words = preg_split('/\s+/', $keyword);
        if (count($words) > 1) {
            foreach ($words as $word) {
                if (mb_strlen($word, 'UTF-8') > 3) {
                    $variations[] = $word;
                }
            }
        }

        // Load language-specific suffix patterns
        $suffixGroups = self::getLanguageSuffixes($lang);

        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') < 4) {
                continue;
            }

            $wordVariations = self::applyLanguageSuffixes($word, $suffixGroups);
            $variations = array_merge($variations, $wordVariations);
        }

        return array_values(array_unique($variations));
    }

    /**
     * Get language-specific suffix patterns
     * Can be loaded from custom JSON files or use built-in patterns
     *
     * @param   string  $lang  Language code
     *
     * @return  array   Array of suffix groups
     * @since   6.0.0
     */
    private static function getLanguageSuffixes(string $lang): array {

        // Check cache first
        if (isset(self::$languagePatternsCache[$lang])) {
            return self::$languagePatternsCache[$lang];
        }

        // Try to load custom patterns from JSON
        $customPatterns = self::loadCustomLanguagePatterns($lang);

        if (!empty($customPatterns)) {
            self::$languagePatternsCache[$lang] = $customPatterns;
            return $customPatterns;
        }

        // Use built-in patterns
        $patterns = self::getBuiltInSuffixes($lang);
        self::$languagePatternsCache[$lang] = $patterns;

        return $patterns;
    }

    /**
     * Load custom language patterns from JSON file
     * Looks in: media/com_phocaseo/language-patterns/{lang}.json
     *
     * @param   string  $lang  Language code
     *
     * @return  array   Suffix patterns or empty array
     * @since   6.0.0
     */
    private static function loadCustomLanguagePatterns(string $lang): array {
        // Clean language code to prevent path injection
        $lang = preg_replace('/[^a-z0-9_\-]/i', '', $lang);
        $path = JPATH_ROOT . '/media/com_phocaseo/language-patterns/' . $lang . '.json';

        if (!is_file($path)) {
            return [];
        }

        try {
            $content = file_get_contents($path);
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $data['suffix_groups'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getLanguagePatterns(string $lang): array {
        // Clean language code to prevent path injection
        $lang = preg_replace('/[^a-z0-9_\-]/i', '', $lang);
        
        $path = JPATH_ROOT . '/media/com_phocaseo/language-patterns/' . $lang . '.json';

        if (!is_file($path)) {
            return [];
        }

        try {
            $content = file_get_contents($path);
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return [
                'suffix_groups'    => $data['suffix_groups'] ?? [],
                'transition_words' => $data['transition_words'] ?? [],
                'passive_voice'    => $data['passive_voice'] ?? []
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get built-in suffix patterns for supported languages
     *
     * @param   string  $lang  Language code
     *
     * @return  array   Array of suffix groups
     * @since   6.0.0
     */
    private static function getBuiltInSuffixes(string $lang): array
    {
        $patterns = [
            'cs' => [ // Czech
                ['a', 'y', 'ě', 'ou', 'ách', 'ami', 'e', 'u', 'em'],
                ['o', 'a', 'u', 'em', 'ech', 'y', 'ům'],
                ['e', 'ě', 'i', 'í', 'ím', 'ích', 'em'],
                ['í', 'ího', 'ímu', 'ím', 'ích', 'ími'],
                ['ka', 'ky', 'ce', 'ek', 'ku', 'kou', 'kách', 'kami'],
                ['ní', 'ním', 'ních', 'ného'],
                ['ost', 'osti', 'ostem', 'ostech'],
            ],
            'sk' => [ // Slovak
                ['a', 'y', 'e', 'ou', 'ách', 'ami', 'u', 'om'],
                ['o', 'a', 'u', 'om', 'och', 'y', 'ám'],
                ['í', 'ieho', 'iemu', 'ím', 'ích', 'ími'],
                ['ka', 'ky', 'ku', 'kou', 'kách', 'kami'],
                ['ný', 'ného', 'nému', 'ným', 'ných'],
            ],
            'pl' => [ // Polish
                ['a', 'y', 'ę', 'ą', 'ach', 'ami', 'e', 'ie'],
                ['o', 'a', 'u', 'em', 'ach', 'y'],
                ['ka', 'ki', 'kę', 'ką', 'kach', 'kami'],
                ['nia', 'nie', 'niu', 'niem', 'niach'],
                ['ość', 'ości', 'ością', 'ościach'],
            ],
            'ru' => [ // Russian (Cyrillic)
                ['а', 'ы', 'е', 'ой', 'ах', 'ами', 'у', 'ом'],
                ['о', 'а', 'у', 'ом', 'ах', 'ы', 'ам'],
                ['ие', 'ия', 'ии', 'ием', 'иях', 'иям'],
                ['ка', 'ки', 'ку', 'кой', 'ках', 'ками'],
                ['ность', 'ности', 'ностью', 'ностях'],
            ],
            'de' => [ // German
                ['e', 'en', 'er', 'es', 'em'],
                ['ung', 'ungen'],
                ['keit', 'keiten'],
                ['schaft', 'schaften'],
                ['chen', 'lein'],
            ],
            'es' => [ // Spanish
                ['a', 'as', 'o', 'os'],
                ['ción', 'ciones'],
                ['dad', 'dades'],
                ['mente'],
            ],
            'it' => [ // Italian
                ['a', 'e', 'i', 'o'],
                ['zione', 'zioni'],
                ['tà'],
                ['mente'],
            ],
            'fr' => [ // French
                ['e', 'es', 's'],
                ['tion', 'tions'],
                ['té', 'tés'],
                ['ment', 'ments'],
            ],
            'pt' => [ // Portuguese
                ['a', 'as', 'o', 'os'],
                ['ção', 'ções'],
                ['dade', 'dades'],
                ['mente'],
            ],
            'en' => [ // English (minimal)
                ['s', 'es', 'ed', 'ing'],
                ['er', 'est'],
                ['tion', 'ness'],
            ],
        ];

        return $patterns[$lang] ?? $patterns['en'];
    }

    /**
     * Apply suffix patterns to a word to generate variations
     *
     * @param   string  $word          The word to process
     * @param   array   $suffixGroups  Groups of related suffixes
     *
     * @return  array   Generated variations
     * @since   6.0.0
     */
    /*private static function applyLanguageSuffixes(string $word, array $suffixGroups): array
    {
        $variations = [];
        $foundSuffix = false;

        foreach ($suffixGroups as $suffixes) {

            foreach ($suffixes['suffixes'] as $suffix) {

                $suffixLen = mb_strlen($suffix, 'UTF-8');

                // Check if word ends with this suffix
                if ($suffixLen > 0 &&
                    mb_strlen($word, 'UTF-8') > $suffixLen + 2 &&
                    mb_substr($word, -$suffixLen, null, 'UTF-8') === $suffix) {

                    // Extract stem
                    $stem = mb_substr($word, 0, -$suffixLen, 'UTF-8');
                    $foundSuffix = true;

                    // Generate variations with different suffixes from same group
                    foreach ($suffixes['suffixes'] as $altSuffix) {
                        $variant = $stem . $altSuffix;
                        if ($variant !== $word && mb_strlen($variant, 'UTF-8') >= 3) {
                            $variations[] = $variant;
                        }
                    }

                    // Add stem itself
                    if (mb_strlen($stem, 'UTF-8') >= 3) {
                        $variations[] = $stem;
                    }

                    break 2; // Exit both loops
                }
            }
        }

        return $variations;
    }*/

    private static function applyLanguageSuffixes(string $word, array $suffixGroups): array
    {
        $variations = [];
        $bestGroup = null;
        $bestStem = '';
        $longestSuffixLen = -1;

        foreach ($suffixGroups as $group) {
            if (!isset($group['suffixes'])) {
                continue;
            }

            foreach ($group['suffixes'] as $suffix) {
                $suffixLen = mb_strlen($suffix, 'UTF-8');
                $wordLen = mb_strlen($word, 'UTF-8');

                if ($wordLen > $suffixLen + 2) {
                    $isMatch = false;
                    if ($suffix === '') {
                        $isMatch = true;
                    } elseif (mb_substr($word, -$suffixLen, null, 'UTF-8') === $suffix) {
                        $isMatch = true;
                    }

                    if ($isMatch && $suffixLen > $longestSuffixLen) {
                        $longestSuffixLen = $suffixLen;
                        $bestStem = ($suffix === '') ? $word : mb_substr($word, 0, -$suffixLen, 'UTF-8');
                        $bestGroup = $group;
                    }
                }
            }
        }

        if ($bestGroup) {
            foreach ($bestGroup['suffixes'] as $altSuffix) {
                $variant = $bestStem . $altSuffix;
                if ($variant !== $word && mb_strlen($variant, 'UTF-8') >= 3) {
                    $variations[] = $variant;
                }
            }
            if (mb_strlen($bestStem, 'UTF-8') >= 3) {
                $variations[] = $bestStem;
            }
        }

        return array_unique($variations);
    }

    /**
     * Check if text contains keyword or its variations
     *
     * @param   string  $text     Text to search in
     * @param   string  $keyword  Keyword to find
     * @param   string  $lang     Optional language code
     *
     * @return  boolean  True if found
     * @since   6.0.0
     */
    public static function containsKeyword(string $text, string $keyword, string $lang = ''): bool
    {
        if (empty($text) || empty($keyword)) {
            return false;
        }

        $text = mb_strtolower($text, 'UTF-8');
        $keyword = mb_strtolower($keyword, 'UTF-8');

        // Fast exact match first
        if (mb_strpos($text, $keyword, 0, 'UTF-8') !== false) {
            return true;
        }

        if (!self::isEnabled()) {
            return false;
        }

        // Try with variations
        $variations = self::generate($keyword, $lang);

        foreach ($variations as $variant) {
            if (mb_strpos($text, $variant, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count keyword occurrences including variations
     *
     * @param   string  $text     Text to search in
     * @param   string  $keyword  Keyword to count
     * @param   string  $lang     Optional language code
     *
     * @return  integer  Number of occurrences
     * @since   6.0.0
     */
    public static function countOccurrences(string $text, string $keyword, string $lang = ''): int
    {
        if (empty($text) || empty($keyword)) {
            return 0;
        }

        $text = mb_strtolower($text, 'UTF-8');
        $keyword = mb_strtolower($keyword, 'UTF-8');

        // Count exact matches
        $count = mb_substr_count($text, $keyword, 'UTF-8');

        if (!self::isEnabled()) {
            return $count;
        }

        // Count variations (avoid double-counting)
        $variations = self::generate($keyword, $lang);
        $words = preg_split('/\s+/', $text);
        $countedPositions = [];

        foreach ($variations as $variant) {
            if ($variant === $keyword) {
                continue; // Skip original, already counted
            }

            foreach ($words as $index => $word) {
                // Clean punctuation
                $cleanWord = preg_replace('/[.,;:!?()[\]{}\'"""«»„]/u', '', $word);

                if ($cleanWord === $variant && !in_array($index, $countedPositions)) {
                    $count++;
                    $countedPositions[] = $index;
                }
            }
        }

        return $count;
    }

    /**
     * Get variations as formatted string for AI prompts
     * Useful when sending to AI Deep Analysis
     *
     * @param   string  $keyword  The focus keyword
     * @param   string  $lang     Optional language code
     *
     * @return  string  Comma-separated variations
     * @since   6.0.0
     */
    public static function getVariationsForAi(string $keyword, string $lang = ''): string
    {
        $variations = self::generate($keyword, $lang);
        return implode(', ', $variations);
    }

    /**
     * Clear the variations cache
     * Useful when configuration changes
     *
     * @return  void
     * @since   6.0.0
     */
    public static function clearCache(): void
    {
        self::$variationsCache = [];
        self::$languagePatternsCache = [];
    }
}
