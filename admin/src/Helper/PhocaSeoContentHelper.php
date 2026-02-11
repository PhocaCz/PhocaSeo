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
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Phoca SEO Content Helper
 *
 * Retrieves content from various contexts for analysis.
 *
 * @since  6.0.0
 */
class PhocaSeoContentHelper
{
    /**
     * Get clean text content for a specific item
     *
     * @param   string  $context  The context (e.g. com_content.article)
     * @param   int     $itemId   The item ID
     *
     * @return  string  Clean text content
     *
     * @since   6.0.0
     */
    public static function getContent(string $context, int $itemId): string
    {
        $content = '';
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        switch ($context) {
            case 'com_content.article':
                $query = $db->getQuery(true)
                    ->select($db->quoteName(['title', 'introtext', 'fulltext']))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $itemId, ParameterType::INTEGER);
                
                $db->setQuery($query);
                $row = $db->loadObject();
                
                if ($row) {
                    // Combine Title + Content for better context
                    $content = $row->title . "\n\n" . $row->introtext . ' ' . $row->fulltext;
                }
                break;
                
            case 'com_content.category':
                $query = $db->getQuery(true)
                    ->select($db->quoteName(['title', 'description']))
                    ->from($db->quoteName('#__categories'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $itemId, ParameterType::INTEGER);
                
                $db->setQuery($query);
                $row = $db->loadObject();
                
                if ($row) {
                    $content = $row->title . "\n\n" . $row->description;
                }
                break;
                
            // Future: Support other contexts via plugin event
        }
        
        // Strip tags and decode entities
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Collapse whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        return trim($content);
    }
}
