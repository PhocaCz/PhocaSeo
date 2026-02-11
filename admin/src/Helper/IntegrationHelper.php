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

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;


class IntegrationHelper
{
    private static ?array $pluginCache = null;

    /**
     * Check if Phoca OpenGraph system plugin is installed and enabled
     *
     * @return  array  Status info ['installed' => bool, 'enabled' => bool, 'params' => Registry|null]
     *
     * @since   6.1.0
     */
    public static function getPhocaOpenGraphStatus(): array
    {
        $result = [
            'installed' => false,
            'enabled'   => false,
            'params'    => null,
            'score_bonus' => 0
        ];

        $pluginPath = JPATH_PLUGINS . '/system/phocaopengraph/phocaopengraph.php';
        $result['installed'] = file_exists($pluginPath);

        if (!$result['installed']) {
            return $result;
        }

        $plugin = PluginHelper::getPlugin('system', 'phocaopengraph');
        
        if ($plugin) {
            $result['enabled'] = true;
            $result['params'] = new Registry($plugin->params ?? '{}');
            
            $params = $result['params'];
            if ($params->get('twitter_enable', 0)) {
                $result['score_bonus'] += 3;
            }
            if ($params->get('find_image_content', 0)) {
                $result['score_bonus'] += 2;
            }
            $result['score_bonus'] += 5;
        }

        return $result;
    }

    /**
     * Check if Phoca OpenGraph content plugin is installed and enabled
     *
     * @return  array  Status info
     *
     * @since   6.1.0
     */
    public static function getPhocaOpenGraphContentStatus(): array
    {
        $result = [
            'installed' => false,
            'enabled'   => false,
            'params'    => null
        ];

        $pluginPath = JPATH_PLUGINS . '/content/phocaopengraph/phocaopengraph.php';
        $result['installed'] = file_exists($pluginPath);

        if (!$result['installed']) {
            return $result;
        }

        $plugin = PluginHelper::getPlugin('content', 'phocaopengraph');
        
        if ($plugin) {
            $result['enabled'] = true;
            $result['params'] = new Registry($plugin->params ?? '{}');
        }

        return $result;
    }

    /**
     * Check if Joomla Schema.org system plugin is configured
     *
     * @return  array  Status info
     *
     * @since   6.1.0
     */
    public static function getSchemaOrgStatus(): array
    {
        $result = [
            'installed' => false,
            'enabled'   => false,
            'params'    => null,
            'base_type' => null
        ];

        $pluginPath = JPATH_PLUGINS . '/system/schemaorg/src/Extension/Schemaorg.php';
        $result['installed'] = file_exists($pluginPath);

        if (!$result['installed']) {
            return $result;
        }

        $plugin = PluginHelper::getPlugin('system', 'schemaorg');
        
        if ($plugin) {
            $result['enabled'] = true;
            $result['params'] = new Registry($plugin->params ?? '{}');
            $result['base_type'] = $result['params']->get('baseType', '');
        }

        return $result;
    }

    /**
     * Check if an article has Schema.org data configured
     *
     * @param   int     $articleId  The article ID
     * @param   string  $context    The context (default: com_content.article)
     *
     * @return  array  Schema info ['configured' => bool, 'schema_type' => string, 'score_bonus' => int]
     *
     * @since   6.1.0
     */
    public static function getArticleSchemaStatus(int $articleId, string $context = 'com_content.article'): array
    {
        $result = [
            'configured'   => false,
            'schema_type'  => '',
            'has_required' => false,
            'score_bonus'  => 0
        ];

        if ($articleId <= 0) {
            return $result;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select(['schemaType', 'schema'])
                ->from($db->quoteName('#__schemaorg'))
                ->where($db->quoteName('itemId') . ' = :itemId')
                ->where($db->quoteName('context') . ' = :context')
                ->bind(':itemId', $articleId, ParameterType::INTEGER)
                ->bind(':context', $context);

            $db->setQuery($query);
            $row = $db->loadObject();

            if ($row && !empty($row->schemaType) && $row->schemaType !== 'None') {
                $result['configured'] = true;
                $result['schema_type'] = $row->schemaType;
                $result['score_bonus'] = 5;

                $schema = new Registry($row->schema ?? '{}');
                $schemaData = $schema->toArray();
                
                $mandatoryFields = self::getSchemaMandatoryFields($row->schemaType);
                $filledCount = 0;
                
                foreach ($mandatoryFields as $field) {
                    if (!empty($schemaData[$field])) {
                        $filledCount++;
                    }
                }

                if ($filledCount === count($mandatoryFields) && count($mandatoryFields) > 0) {
                    $result['has_required'] = true;
                    $result['score_bonus'] += 5;
                } elseif ($filledCount > 0) {
                    $result['score_bonus'] += 2;
                }
            }
        } catch (\Exception $e) {
            return $result;
        }

        return $result;
    }

    /**
     * Get mandatory fields for a schema type
     *
     * @param   string  $schemaType  The schema type
     *
     * @return  array  List of mandatory field names
     *
     * @since   6.1.0
     */
    public static function getSchemaMandatoryFields(string $schemaType): array
    {
        $mandatoryFields = [
            'Article'     => ['headline', 'image', 'author', 'datePublished'],
            'NewsArticle' => ['headline', 'image', 'author', 'datePublished'],
            'BlogPosting' => ['headline', 'image', 'author', 'datePublished'],
            'Recipe'      => ['name', 'image', 'author', 'recipeInstructions', 'recipeIngredient'],
            'Product'     => ['name', 'image', 'offers'],
            'Event'       => ['name', 'startDate', 'location'],
            'Organization'=> ['name', 'url'],
            'Person'      => ['name'],
            'FAQPage'     => ['mainEntity'],
            'HowTo'       => ['name', 'step'],
        ];

        return $mandatoryFields[$schemaType] ?? [];
    }

    /**
     * Calculate integration score bonus for an item
     *
     * @param   int     $itemId   The item ID
     * @param   string  $context  The context
     *
     * @return  int  Total score bonus from integrations
     *
     * @since   6.1.0
     */
    public static function calculateIntegrationBonus(int $itemId, string $context): int
    {
        $bonus = 0;

        $ogStatus = self::getPhocaOpenGraphStatus();
        if ($ogStatus['enabled']) {
            $bonus += $ogStatus['score_bonus'];
        }

        if (strpos($context, 'com_content.') === 0) {
            $schemaStatus = self::getArticleSchemaStatus($itemId, $context);
            if ($schemaStatus['configured']) {
                $bonus += $schemaStatus['score_bonus'];
            }
        }

        return $bonus;
    }

    /**
     * Get all integration statuses as summary
     *
     * @param   int     $itemId   The item ID
     * @param   string  $context  The context
     *
     * @return  array  Complete integration summary
     *
     * @since   6.1.0
     */
    public static function getIntegrationSummary(int $itemId, string $context): array
    {
        return [
            'phoca_opengraph'         => self::getPhocaOpenGraphStatus(),
            'phoca_opengraph_content' => self::getPhocaOpenGraphContentStatus(),
            'schemaorg'               => self::getSchemaOrgStatus(),
            'article_schema'          => self::getArticleSchemaStatus($itemId, $context),
            'total_bonus'             => self::calculateIntegrationBonus($itemId, $context)
        ];
    }

    /**
     * Check if redirect component is available for 404 fixing
     *
     * @return  bool
     *
     * @since   6.1.0
     */
    public static function isRedirectComponentAvailable(): bool
    {
        return file_exists(JPATH_ADMINISTRATOR . '/components/com_redirect/redirect.php') 
            || file_exists(JPATH_ADMINISTRATOR . '/components/com_redirect/src/Extension/RedirectComponent.php');
    }

    /**
     * Get the URL for creating a new redirect
     *
     * @param   string  $oldUrl  The old/broken URL
     *
     * @return  string  The admin URL for com_redirect
     *
     * @since   6.1.0
     */
    public static function getRedirectCreateUrl(string $oldUrl = ''): string
    {
        $url = 'index.php?option=com_redirect&task=link.add';
        
        if ($oldUrl) {
            $url .= '&old_url=' . urlencode($oldUrl);
        }

        return $url;
    }
}
