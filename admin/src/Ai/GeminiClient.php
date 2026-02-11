<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Ai;

use Joomla\CMS\Http\HttpFactory;

// phpcs:disable PSR1.Files.SideEffects
// use Phoca\Component\PhocaSeo\Administrator\Helper\JsonFixerHelper;

\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


class GeminiClient implements AiClientInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gemini-1.5-flash')
    {
        $this->apiKey = $apiKey;
        $this->model  = $model;
    }

    public function generateMetaTitle(string $content, string $keyword, array $constraints = []): string
    {
        $min = $constraints['min_length'] ?? 30;
        $max = $constraints['max_length'] ?? 60;
        $prompt = "Generate an SEO-optimized meta title. Focus keyword: $keyword. Length: $min-$max characters. Content:\n$content\n\nReturn ONLY the title text. DO NOT USE QUOTES. NO LABELS.";
        return $this->sendRequest($prompt);
    }

    public function generateMetaDescription(string $content, string $keyword, array $constraints = []): string
    {
        $min = $constraints['min_length'] ?? 120;
        $max = $constraints['max_length'] ?? 160;
        $prompt = "Generate an SEO-optimized meta description. Focus keyword: $keyword. Length: $min-$max characters. Content:\n$content\n\nReturn ONLY the description text. DO NOT USE QUOTES. NO LABELS.";
        return $this->sendRequest($prompt);
    }

    public function generateMetaKeywords(string $content, string $keyword, array $constraints = []): string
    {
        $maxCount = $constraints['max_count'] ?? 10;
        $prompt = "Generate up to $maxCount SEO keywords, comma-separated. Focus keyword: $keyword. Content:\n$content\n\nReturn ONLY the keywords. DO NOT USE QUOTES. NO LABELS.";
        return $this->sendRequest($prompt);
    }


    public function generateSchemaField(string $content, string $field, string $type, string $source = ''): string
    {
        $prompt = "Based on the following content, suggest the most appropriate value for the Schema.org field '$field' within the '$type' structured data type.";

        if ($field === 'image_alt' && !empty($source)) {
            $prompt .= "\nThe specific item being described is an image with the source: '$source'. Use this to create descriptive ALT text.";
        }

        $prompt .= " Return ONLY the suggested value text, no commentary, no quotes, no labels.\n\nContent:\n$content";
        return $this->sendRequest($prompt);
    }


    public function analyzeContent(string $content, string $keyword, array $constraints = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Gemini AI is not configured.');
        }

        $prompt = <<<PROMPT
Analyze the following content for SEO quality.
Focus keyword: {$keyword}

Return ONLY a valid JSON object with the following structure:
{
  "score": (int 0-100),
  "suggestions": (array of strings),
  "keyword_density": (float),
  "readability": (string "easy", "moderate", or "difficult"),
  "issues": (array of strings)
}

Content:
{$content}

Do not include any markdown formatting, explanations, or other text outside the JSON object.
PROMPT;

        $response = $this->sendRequest($prompt, true);
        if (empty($response)) {
            throw new \Exception('Gemini AI returned empty response.');
        }

        // Find the first { and last } to extract JSON
        $start = strpos($response, '{');
        $end = strrpos($response, '}');

        if ($start === false || $end === false) {
            throw new \Exception('No JSON object found in response: ' . $response);
        }

        $json = substr($response, $start, $end - $start + 1);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return [
            'score'           => (int) ($data['score'] ?? 0),
            'suggestions'     => (array) ($data['suggestions'] ?? []),
            'keyword_density' => (float) ($data['keyword_density'] ?? 0.0),
            'readability'     => (string) ($data['readability'] ?? 'moderate'),
            'issues'          => (array) ($data['issues'] ?? []),
        ];
    }


    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && \strlen($this->apiKey) > 20;
    }


    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function sendRequest(string $prompt, bool $json = false): string
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Gemini AI is not configured. Missing API Key.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
        $http = HttpFactory::getHttp();
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1000,
            ]
        ];

        if ($json) {
            $data['generationConfig']['response_mime_type'] = 'application/json';
        }

        try {
            $response = $http->post($url, json_encode($data, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json'], 30);

            $code = (int) ($response->code ?? 0);
            if ($code !== 200) {
                $errorBody = json_decode((string) ($response->body ?? '{}'), true);
                $errorMsg = $errorBody['error']['message'] ?? 'Unknown Gemini API Error';
                throw new \Exception("Gemini API Error (Code $code): $errorMsg");
            }

            $body = json_decode((string) ($response->body ?? '{}'), true, 512, JSON_THROW_ON_ERROR);

            $content = trim($body['candidates'][0]['content']['parts'][0]['text'] ?? '');

            if (empty($content)) {
                throw new \Exception('Gemini AI returned an empty response.');
            }

            // Clean up potentially leaked labels or quotes
            $content = trim($content, " \t\n\r\0\x0B\"'");

            // Remove common AI markers if they leaked in
            $content = preg_replace('/^(JSON|Title|Description|Keywords|Alt|Value):\s*/i', '', $content);

            // Remove markdown code blocks if present
            if (str_starts_with($content, '```')) {
                $content = preg_replace('/^```[a-z]*\n/i', '', $content);
                $content = preg_replace('/\n```$/', '', $content);
            }

            return trim($content);
        } catch (\Exception $e) {
            throw new \Exception('Gemini Request Failed: ' . $e->getMessage());
        }
    }
}
