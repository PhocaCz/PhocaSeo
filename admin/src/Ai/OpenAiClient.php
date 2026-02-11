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
// use Joomla\Http\Response;
// use Phoca\Component\PhocaSeo\Administrator\Helper\JsonFixerHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


class OpenAiClient implements AiClientInterface
{
    /**
     * API endpoint
     */
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini')
    {
        $this->apiKey = $apiKey;
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

        if (!$this->isConfigured()) {
            throw new \Exception('AI Provider is not configured.');
        }

        $response = $this->sendRequest($prompt, true);
        if (empty($response)) {
            throw new \Exception('AI returned empty response.');
        }

        // Find the first { and last } to extract JSON
        $start = strpos($response, '{');
        $end = strrpos($response, '}');

        if ($start === false || $end === false) {
            throw new \JsonException('No JSON object found in response: ' . $response);
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
        return 'openai';
    }

    public function sendRequest(string $prompt, bool $json = false): string
    {
        if (!$this->isConfigured()) {
            throw new \Exception('AI Provider is not configured. Please check your API Key in Phoca SEO configuration.');
        }

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
            $response = $http->post(self::API_URL, json_encode($data, JSON_THROW_ON_ERROR), [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ], 30);

            $code = (int) ($response->code ?? 0);
            if ($code !== 200) {
                $errorBody = json_decode((string) ($response->body ?? '{}'), true);
                $errorMsg = $errorBody['error']['message'] ?? 'Unknown API Error';
                throw new \Exception("OpenAI API Error (Code $code): $errorMsg");
            }

            $body = json_decode((string) ($response->body ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            $content = trim($body['choices'][0]['message']['content'] ?? '');

            if (empty($content)) {
                throw new \Exception('OpenAI returned an empty response.');
            }

            return $content;
        } catch (\Exception $e) {
            throw new \Exception('AI Request Failed: ' . $e->getMessage());
        }
    }
}
