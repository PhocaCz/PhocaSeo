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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

defined('_JEXEC') or die;

/**
 * Link Suggestion Helper
 *
 * Provides internal linking suggestions based on focus keywords.
 *
 * @since  6.1.0
 */
class LinkSuggestionHelper
{
    /**
     * Find content matching a keyword for internal linking suggestions
     *
     * @param   string  $keyword   The keyword to search for
     * @param   int     $excludeId Item ID to exclude from results
     * @param   string  $context   Current context
     * @param   int     $limit     Maximum results
     *
     * @return  array   Array of suggestions with title, url, context
     *
     * @since   6.1.0
     */
    public static function getSuggestions(string $keyword, int $excludeId = 0, string $context = '', int $limit = 10): array
    {
        if (strlen($keyword) < 3) {
            return [];
        }

        $suggestions = [];
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $keyword = trim($keyword);
        $keywordLike = '%' . $db->escape($keyword, true) . '%';

        $articles = self::findMatchingArticles($db, $keywordLike, $excludeId, $context, $limit);
        $suggestions = array_merge($suggestions, $articles);

        if (count($suggestions) < $limit) {
            $categories = self::findMatchingCategories($db, $keywordLike, $excludeId, $context, $limit - count($suggestions));
            $suggestions = array_merge($suggestions, $categories);
        }

        usort($suggestions, function($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Find matching articles
     *
     * @param   DatabaseInterface  $db          Database
     * @param   string             $keywordLike Keyword pattern
     * @param   int                $excludeId   Exclude ID
     * @param   string             $context     Current context
     * @param   int                $limit       Max results
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected static function findMatchingArticles(DatabaseInterface $db, string $keywordLike, int $excludeId, string $context, int $limit): array
    {
        $results = [];

        $query = $db->getQuery(true)
            ->select([
                'a.id',
                'a.title',
                'a.alias',
                'a.catid',
                'c.title AS cat_title',
                'c.alias AS cat_alias'
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where('(' . 
                $db->quoteName('a.title') . ' LIKE ' . $db->quote($keywordLike) . ' OR ' .
                $db->quoteName('a.introtext') . ' LIKE ' . $db->quote($keywordLike) . ' OR ' .
                $db->quoteName('a.metakey') . ' LIKE ' . $db->quote($keywordLike) .
            ')')
            ->order('a.hits DESC')
            ->setLimit($limit);

        if ($excludeId > 0 && $context === 'com_content.article') {
            $query->where($db->quoteName('a.id') . ' != :excludeId')
                ->bind(':excludeId', $excludeId, ParameterType::INTEGER);
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        foreach ($rows as $row) {
            $url = self::getArticleSefUrl((int) $row->id, (int) $row->catid);
            
            $relevance = 50;
            if (stripos($row->title, trim($keywordLike, '%')) !== false) {
                $relevance += 30;
            }

            $results[] = [
                'id' => (int) $row->id,
                'title' => $row->title,
                'url' => $url,
                'context' => 'com_content.article',
                'catid' => (int) $row->catid,
                'cat_title' => $row->cat_title ?? '',
                'relevance' => $relevance
            ];
        }

        return $results;
    }

    /**
     * Find matching categories
     *
     * @param   DatabaseInterface  $db          Database
     * @param   string             $keywordLike Keyword pattern
     * @param   int                $excludeId   Exclude ID
     * @param   string             $context     Current context
     * @param   int                $limit       Max results
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected static function findMatchingCategories(DatabaseInterface $db, string $keywordLike, int $excludeId, string $context, int $limit): array
    {
        $results = [];

        $query = $db->getQuery(true)
            ->select([
                'c.id',
                'c.title',
                'c.alias'
            ])
            ->from($db->quoteName('#__categories', 'c'))
            ->where($db->quoteName('c.extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('c.published') . ' = 1')
            ->where('(' . 
                $db->quoteName('c.title') . ' LIKE ' . $db->quote($keywordLike) . ' OR ' .
                $db->quoteName('c.description') . ' LIKE ' . $db->quote($keywordLike) . ' OR ' .
                $db->quoteName('c.metakey') . ' LIKE ' . $db->quote($keywordLike) .
            ')')
            ->setLimit($limit);

        if ($excludeId > 0 && $context === 'com_content.category') {
            $query->where($db->quoteName('c.id') . ' != :excludeId')
                ->bind(':excludeId', $excludeId, ParameterType::INTEGER);
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        foreach ($rows as $row) {
            $url = self::getCategorySefUrl((int) $row->id);
            
            $relevance = 40;
            if (stripos($row->title, trim($keywordLike, '%')) !== false) {
                $relevance += 25;
            }

            $results[] = [
                'id' => (int) $row->id,
                'title' => $row->title,
                'url' => $url,
                'context' => 'com_content.category',
                'relevance' => $relevance
            ];
        }

        return $results;
    }

    /**
     * Get SEF URL for an article using Joomla's router
     *
     * @param   int  $articleId   Article ID
     * @param   int  $categoryId  Category ID
     *
     * @return  string
     *
     * @since   6.1.0
     */
    public static function getArticleSefUrl(int $articleId, int $categoryId): string
    {
        try {
            $menuItemId = self::findMenuItemForArticle($articleId, $categoryId);
            
            $url = 'index.php?option=com_content&view=article&id=' . $articleId;
            if ($categoryId > 0) {
                $url .= '&catid=' . $categoryId;
            }
            if ($menuItemId > 0) {
                $url .= '&Itemid=' . $menuItemId;
            }

            return Route::link('site', $url);
        } catch (\Exception $e) {
            return 'index.php?option=com_content&view=article&id=' . $articleId;
        }
    }

    /**
     * Get SEF URL for a category
     *
     * @param   int  $categoryId  Category ID
     *
     * @return  string
     *
     * @since   6.1.0
     */
    public static function getCategorySefUrl(int $categoryId): string
    {
        try {
            $menuItemId = self::findMenuItemForCategory($categoryId);
            
            $url = 'index.php?option=com_content&view=category&id=' . $categoryId;
            if ($menuItemId > 0) {
                $url .= '&Itemid=' . $menuItemId;
            }

            return Route::link('site', $url);
        } catch (\Exception $e) {
            return 'index.php?option=com_content&view=category&id=' . $categoryId;
        }
    }

    /**
     * Find menu item for an article
     *
     * @param   int  $articleId   Article ID
     * @param   int  $categoryId  Category ID
     *
     * @return  int  Menu item ID or 0
     *
     * @since   6.1.0
     */
    protected static function findMenuItemForArticle(int $articleId, int $categoryId): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_content%view=article%id=' . $articleId . '%'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('client_id') . ' = 0')
            ->setLimit(1);

        $db->setQuery($query);
        $result = (int) $db->loadResult();

        if ($result > 0) {
            return $result;
        }

        if ($categoryId > 0) {
            return self::findMenuItemForCategory($categoryId);
        }

        return 0;
    }

    /**
     * Find menu item for a category
     *
     * @param   int  $categoryId  Category ID
     *
     * @return  int  Menu item ID or 0
     *
     * @since   6.1.0
     */
    protected static function findMenuItemForCategory(int $categoryId): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__menu'))
            ->where('(' .
                $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_content%view=category%id=' . $categoryId . '%') .
                ' OR ' . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_content%view=blog%id=' . $categoryId . '%') .
            ')')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('client_id') . ' = 0')
            ->order('lft ASC')
            ->setLimit(1);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Count inbound links to an item
     *
     * @param   int     $itemId   Item ID
     * @param   string  $context  Context
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function countInboundLinks(int $itemId, string $context): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__phocaseo_links'))
            ->where($db->quoteName('link_type') . ' = ' . $db->quote('internal'));

        if ($context === 'com_content.article') {
            $query->where($db->quoteName('target_option') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('target_view') . ' = ' . $db->quote('article'))
                ->where($db->quoteName('target_id') . ' = :itemId')
                ->bind(':itemId', $itemId, ParameterType::INTEGER);
        } elseif ($context === 'com_content.category') {
            $query->where($db->quoteName('target_option') . ' = ' . $db->quote('com_content'))
                ->where('(' . $db->quoteName('target_view') . ' = ' . $db->quote('category') . ' OR ' . $db->quoteName('target_view') . ' = ' . $db->quote('blog') . ')')
                ->where($db->quoteName('target_id') . ' = :itemId')
                ->bind(':itemId', $itemId, ParameterType::INTEGER);
        } elseif ($context === 'com_menus.item') {
            $query->where($db->quoteName('target_id') . ' = :itemId')
                ->bind(':itemId', $itemId, ParameterType::INTEGER);
        } else {
             return 0;
        }

        $db->setQuery($query);

        return (int) $db->loadResult();
    }
}
