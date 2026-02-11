<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Helper;

defined('_JEXEC') or die;

class JsonFixerHelper
{
    public static function fix(string $json): string
{
    $json = trim($json);
    if (empty($json)) {
        return '[]';
    }

    $processedJson = $json;

    // 1. Handle cases where the opening bracket/brace is missing
    // If it doesn't start with { or [, find the first " (the start of a key)
    if (!preg_match('/^[\[\{]/', $processedJson)) {
        $firstQuote = strpos($processedJson, '"');
        $firstBrace = strpos($processedJson, '{');
        $firstBracket = strpos($processedJson, '[');

        // Find which one comes first to determine where the real JSON starts
        $starts = array_filter([$firstQuote, $firstBrace, $firstBracket], function($v) { return $v !== false; });
        
        if (!empty($starts)) {
            $startPos = min($starts);
            $processedJson = substr($processedJson, $startPos);
            
            // If we started at a quote and it looks like a key-value pair, prepend the brace
            if ($startPos === $firstQuote && strpos($processedJson, ':') !== false) {
                $processedJson = '{' . $processedJson;
            }
        }
    }

    // 2. Fix trailing punctuation
    $processedJson = trim($processedJson, " ,.:;");

    // 3. Close unclosed quotes
    $quoteCount = preg_match_all('/(?<!\\\\)"/', $processedJson);
    if ($quoteCount % 2 !== 0) {
        $processedJson .= '"';
    }

    // 4. Close unclosed brackets/braces (The Stack Logic)
    $brackets = ['[' => ']', '{' => '}'];
    $stack = [];
    $inQuotes = false;

    for ($i = 0; $i < strlen($processedJson); $i++) {
        $char = $processedJson[$i];
        // Handle escaped quotes
        if ($char === '"' && ($i === 0 || $processedJson[$i - 1] !== '\\')) {
            $inQuotes = !$inQuotes;
        }

        if (!$inQuotes) {
            if (isset($brackets[$char])) {
                $stack[] = $brackets[$char];
            } elseif (in_array($char, $brackets)) {
                if (!empty($stack) && end($stack) === $char) {
                    array_pop($stack);
                }
            }
        }
    }

    if (!empty($stack)) {
        $processedJson .= implode('', array_reverse($stack));
    }

    // 5. Remove trailing commas before closing braces
    $processedJson = preg_replace('/,\s*([\]\}])/', '$1', $processedJson);

    // 6. Final Validation
    json_decode($processedJson);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Log the error here if you need to debug why it still fails
        // error_log("JSON Fix Failed: " . json_last_error_msg() . " String: " . $processedJson);
        return '[]';
    }

    return (string)$processedJson;
}
}
