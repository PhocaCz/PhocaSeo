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
use Phoca\Component\PhocaSeo\Administrator\Helper\JsonFixerHelper;
// use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


class LocalAiClient implements AiClientInterface
{
    private string $apiUrl;
    private string $model;

    public function __construct(string $apiUrl, string $model = 'llama3.2')
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        // Ensure /chat/completions suffix if not present
        if (!str_ends_with($this->apiUrl, '/chat/completions')) {
            $this->apiUrl .= '/chat/completions';
        }
        $this->model  = $model;
    }


    public function generateMetaTitle(string $content, string $keyword, array $constraints = []): string
    {
        $min = $constraints['min_length'] ?? 30;
        $max = $constraints['max_length'] ?? 60;
        $ideal = $constraints['ideal_length'] ?? 55;

        $prompt = <<<PROMPT
Generate an SEO-optimized meta title for the provided content.
Focus keyword: {$keyword}

Requirements:
- Length: Between {$min} and {$max} characters (Ideal: {$ideal} characters)
- Include the focus keyword naturally
- Make it compelling and click-worthy
- DO NOT USE QUOTES around the title.
- DO NOT INCLUDE ANY EXTRA TEXT OR LABELS.

Content:
{$content}

Return ONLY the meta title text.
PROMPT;

        return $this->sendRequest($prompt);
    }


    public function generateMetaDescription(string $content, string $keyword, array $constraints = []): string
    {
        $min = $constraints['min_length'] ?? 120;
        $max = $constraints['max_length'] ?? 160;
        $ideal = $constraints['ideal_length'] ?? 155;

        $prompt = <<<PROMPT
Generate an SEO-optimized meta description for the provided content.
Focus keyword: {$keyword}

Requirements:
- Length: Between {$min} and {$max} characters (Ideal: {$ideal} characters)
- Include the focus keyword naturally
- Include a call to action
- Make it compelling
- DO NOT USE QUOTES around the description.
- DO NOT INCLUDE ANY EXTRA TEXT OR LABELS.

Content:
{$content}

Return ONLY the meta description text.
PROMPT;

        return $this->sendRequest($prompt);
    }


    public function generateMetaKeywords(string $content, string $keyword, array $constraints = []): string
    {
        $maxCount = $constraints['max_count'] ?? 10;
        $maxLength = $constraints['max_length'] ?? 255;

        $prompt = <<<PROMPT
Generate SEO-optimized meta keywords for the provided content.
Focus keyword: {$keyword}

Requirements:
- Provide up to {$maxCount} keywords
- Total length must not exceed {$maxLength} characters
- Keywords must be separated by commas
- Keywords should be relevant to both the content and the focus keyword
- DO NOT USE QUOTES.
- DO NOT INCLUDE ANY EXTRA TEXT OR LABELS.

Content:
{$content}

Return ONLY the comma-separated keywords.
PROMPT;

        return $this->sendRequest($prompt);
    }


    public function generateSchemaField(string $content, string $field, string $type, string $source = ''): string
    {
        $prompt = "Suggest the most appropriate value for the Schema.org field '$field' within the '$type' structured data type for the provided content.";

        if ($field === 'image_alt' && !empty($source)) {
            $prompt .= "\nThe specific item being described is an image with the following source/filename: '$source'. Please use this filename as context to generate an accurate, descriptive ALT text.";
        }

        $prompt .= "\n\nReturn ONLY the value text, no quotes, no labels.\n\nContent:\n$content";
        return $this->sendRequest($prompt);
    }


    public function analyzeContent(string $content, string $keyword, array $constraints = []): array
    {
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
            throw new \Exception('Local AI returned empty response.');
        }

        $response = JsonFixerHelper::fix($response);

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
        return !empty($this->apiUrl);
    }


    public function getProviderName(): string
    {
        return 'local';
    }

    public function sendRequest(string $prompt, bool $json = false): string
    {
        $http = HttpFactory::getHttp();
        $data = [
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'system', 'content' => 'You are an SEO expert assistant.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens'  => 1000,
        ];

        if ($json) {
            $data['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $http->post($this->apiUrl, json_encode($data, JSON_THROW_ON_ERROR), [
                'Content-Type' => 'application/json'
            ], 60); // Longer timeout for local models

            $code = (int) ($response->code ?? 0);
            if ($code !== 200) {
                $errorBody = json_decode((string) ($response->body ?? '{}'), true);
                $errorMsg = $errorBody['error']['message'] ?? 'Unknown Local AI Error';
                throw new \Exception("Local AI Error (Code $code): $errorMsg");
            }

            $body = json_decode((string) ($response->body ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            $content = trim($body['choices'][0]['message']['content'] ?? '');

            if (empty($content)) {
                throw new \Exception('Local AI returned an empty response.');
            }
            
            return $content;
        } catch (\Exception $e) {
            throw new \Exception('Local AI Request Failed: ' . $e->getMessage());
        }
    }
}
