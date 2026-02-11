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

use Joomla\CMS\Component\ComponentHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class AiClientFactory
{

    public static function create(?string $provider = null): AiClientInterface{
        $params = ComponentHelper::getParams('com_phocaseo');

        $provider = $provider ?? $params->get('ai_provider', 'openai');

        return match ($provider) {
            'gemini' => new GeminiClient(
                (string) $params->get('gemini_api_key', ''),
                (string) $params->get('gemini_model', 'gemini-1.5-flash')
            ),
            'claude' => new ClaudeClient(
                (string) $params->get('claude_api_key', ''),
                (string) $params->get('claude_model', 'claude-3-5-sonnet-20240620')
            ),
            'local' => new LocalAiClient(
                (string) $params->get('local_ai_url', 'http://localhost:5000/v1'),
                (string) $params->get('local_ai_model', 'llama3.2')
            ),
            default => new OpenAiClient(
                (string) $params->get('openai_api_key', ''),
                (string) $params->get('openai_model', 'gpt-4o-mini')
            ),
        };
    }


    public static function getProviders(): array
    {
        return [
            'openai' => 'OpenAI (GPT)',
            'gemini' => 'Google Gemini',
            'claude' => 'Anthropic Claude',
            'local'  => 'Local AI (Custom)',
        ];
    }

    public static function isAnyProviderConfigured(): bool
    {
        $params = ComponentHelper::getParams('com_phocaseo');

        return !empty($params->get('openai_api_key', ''))
            || !empty($params->get('gemini_api_key', ''))
            || !empty($params->get('claude_api_key', ''))
            || !empty($params->get('local_ai_url', ''));
    }
}
