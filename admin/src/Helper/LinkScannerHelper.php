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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

defined('_JEXEC') or die;

/**
 * Link Scanner Helper
 *
 * Scans content for links, checks HTTP status codes, and stores results.
 *
 * @since  6.1.0
 */
class LinkScannerHelper
{
    protected static $httpClient = null;
    protected static $currentHost = null;

    /**
     * Get total count of items that can be scanned (articles + categories)
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function getTotalScanItemsCount(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Count Articles
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        $articles = (int) $db->loadResult();

        // Count Categories
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        $categories = (int) $db->loadResult();

        return $articles + $categories;
    }

    /**
     * Scan a batch of items (articles and categories)
     *
     * @param   int  $offset  Offset to start from
     * @param   int  $limit   Number of items to scan
     *
     * @return  array Summary of found links
     *
     * @since   6.1.0
     */
    public static function scanBatchItems(int $offset, int $limit): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $results = ['internal' => 0, 'external' => 0, 'total' => 0, 'items_processed' => 0];

        // 1. Get total article count to know if we should scan articles or categories
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        $articleCount = (int) $db->loadResult();

        // 2. Scan Articles if offset is within article range
        if ($offset < $articleCount) {
            $currentLimit = min($limit, $articleCount - $offset);

            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'introtext', 'fulltext']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1');

            $db->setQuery($query, $offset, $currentLimit);
            $articles = $db->loadObjectList();

            foreach ($articles as $article) {
                $content = ($article->introtext ?? '') . ' ' . ($article->fulltext ?? '');
                $res = self::scanContent('com_content.article', (int) $article->id, $content);
                $results['internal'] += $res['internal'];
                $results['external'] += $res['external'];
                $results['total'] += $res['total'];
                $results['items_processed']++;
            }

            $limit -= $currentLimit;
            $offset = 0; // Reset offset for categories if we move to them
        } else {
            $offset -= $articleCount; // Adjust offset for categories
        }

        // 3. Scan Categories if we still have limit left and items to process
        if ($limit > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'description']))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('published') . ' = 1');

            $db->setQuery($query, $offset, $limit);
            $categories = $db->loadObjectList();

            foreach ($categories as $category) {
                $content = (string) ($category->description ?? '');
                $res = self::scanContent('com_content.category', (int) $category->id, $content);
                $results['internal'] += $res['internal'];
                $results['external'] += $res['external'];
                $results['total'] += $res['total'];
                $results['items_processed']++;
            }
        }

        return $results;
    }

    /**
     * Scan an item's content for links
     *
     * @param   string  $context  Content context
     * @param   int     $itemId   Item ID
     * @param   string  $content  HTML content
     *
     * @return  array   Summary of found links
     *
     * @since   6.1.0
     */
    public static function scanContent(string $context, int $itemId, string $content): array
    {
        if (empty($content)) {
            return ['internal' => 0, 'external' => 0, 'total' => 0];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        self::$currentHost = Uri::getInstance()->getHost();

        self::clearItemLinks($db, $context, $itemId);

        $links = self::extractLinks($content);

        $internalCount = 0;
        $externalCount = 0;

        foreach ($links as $link) {
            $linkData = self::analyzeLink($link['href']);

            if ($linkData['link_type'] === 'internal') {
                $internalCount++;
            } else {
                $externalCount++;
            }

            self::storeLink($db, $context, $itemId, $link, $linkData);
        }

        return [
            'internal' => $internalCount,
            'external' => $externalCount,
            'total' => count($links)
        ];
    }

    /**
     * Extract all links from HTML content
     *
     * @param   string  $html  HTML content
     *
     * @return  array   Array of link data
     *
     * @since   6.1.0
     */
    public static function extractLinks(string $html): array
    {
        $links = [];

        if (empty($html)) {
            return $links;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $anchors = $dom->getElementsByTagName('a');

        foreach ($anchors as $anchor) {
            if (!($anchor instanceof \DOMElement)) {
                continue;
            }

            $href = $anchor->getAttribute('href');

            if (empty($href) || $href === '#') {
                continue;
            }

            if (strpos($href, 'javascript:') === 0 || strpos($href, 'mailto:') === 0 || strpos($href, 'tel:') === 0) {
                continue;
            }

            $text = trim($anchor->textContent);
            $rel = $anchor->getAttribute('rel');

            $links[] = [
                'href' => $href,
                'text' => substr($text, 0, 500),
                'rel' => $rel,
                'is_nofollow' => strpos($rel, 'nofollow') !== false,
                'is_sponsored' => strpos($rel, 'sponsored') !== false,
                'is_ugc' => strpos($rel, 'ugc') !== false
            ];
        }

        return $links;
    }

    /**
     * Analyze a link URL to determine type and normalize
     *
     * @param   string  $href  Link URL
     *
     * @return  array   Analysis data
     *
     * @since   6.1.0
     */
    public static function analyzeLink(string $href): array
    {
        $result = [
            'original_url'   => $href,
            'normalized_url' => $href,
            'link_type'      => 'internal',
            'target_option'  => null,
            'target_view'    => null,
            'target_id'      => null
        ];

        if (strpos($href, '//') === 0) {
            $href = 'https:' . $href;
        }

        $uri = Uri::getInstance($href);
        $root = Uri::root();

        // Check if external
        // If it starts with http... but NOT with our root, it's external
        if (strpos($href, 'http') === 0 && strpos($href, $root) !== 0) {
            $result['link_type'] = 'external';
            $result['normalized_url'] = $href;
            return $result;
        }

        // If it's a relative link, it's internal
        if (strpos($href, 'http') !== 0 && strpos($href, '//') !== 0) {
            $result['link_type'] = 'internal';
        }

        // Normalize internal link
        $internalUrl = $href;
        if (strpos($href, $root) === 0) {
            $internalUrl = substr($href, strlen($root));
        }
        $internalUrl = ltrim($internalUrl, '/');

        $result['normalized_url'] = $root . $internalUrl;

        // Resolve internal parameters
        $params = self::resolveInternalParams($internalUrl);
        $result['target_option'] = $params['option'] ?? null;
        $result['target_view']   = $params['view'] ?? null;
        $result['target_id']     = $params['id'] ?? null;

        return $result;
    }

    /**
     * Resolve internal URL parameters (option, view, id)
     *
     * @param   string  $url  Internal URL (relative to root)
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected static function resolveInternalParams(string $url): array
    {
        $params = [];

        // Case 1: Non-SEF (index.php?option=...)
        if (strpos($url, 'index.php') !== false) {
            $query = parse_url($url, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
            }
            
            if (!empty($params)) {
                return $params;
            }
        }

        // Case 2: SEF URL - Try to search for patterns
        // This is a simplified resolver as full Joomla routing in backend is complex

        // Simple Article pattern: alias (if no id) or id-alias
        if (preg_match('/^([0-9]+)-/', $url, $matches)) {
            return ['option' => 'com_content', 'view' => 'article', 'id' => $matches[1]];
        }

        // Search alias in database for articles
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $alias = $url;
        if (strpos($url, '/') !== false) {
            $parts = explode('/', $url);
            $alias = end($parts);
            if (empty($alias)) $alias = $parts[count($parts)-2];
        }

        // Remove .html if present
        if (strpos($alias, '.html') !== false) {
            $alias = str_replace('.html', '', $alias);
        }

        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('alias') . ' = :alias')
            ->bind(':alias', $alias);
        $db->setQuery($query);
        $id = $db->loadResult();

        if ($id) {
            return ['option' => 'com_content', 'view' => 'article', 'id' => $id];
        }

        // Search category alias
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('alias') . ' = :alias')
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->bind(':alias', $alias);
        $db->setQuery($query);
        $id = $db->loadResult();

        if ($id) {
            return ['option' => 'com_content', 'view' => 'category', 'id' => $id];
        }

        return $params;
    }

    /**
     * Check if a host is subdomain of another
     *
     * @param   string  $host    Host to check
     * @param   string  $parent  Parent domain
     *
     * @return  bool
     *
     * @since   6.1.0
     */
    protected static function isSubdomain(string $host, string $parent): bool
    {
        $host = strtolower($host);
        $parent = strtolower($parent);

        return substr($host, -strlen($parent) - 1) === '.' . $parent;
    }

    /**
     * Clear existing links for an item
     *
     * @param   DatabaseInterface  $db       Database
     * @param   string             $context  Context
     * @param   int                $itemId   Item ID
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected static function clearItemLinks(DatabaseInterface $db, string $context, int $itemId): void
    {
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__phocaseo_links'))
            ->where($db->quoteName('source_context') . ' = :context')
            ->where($db->quoteName('source_id') . ' = :itemId')
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Store a link in the database
     *
     * @param   DatabaseInterface  $db        Database
     * @param   string             $context   Context
     * @param   int                $itemId    Item ID
     * @param   array              $link      Link data from extraction
     * @param   array              $linkData  Analyzed link data
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected static function storeLink(DatabaseInterface $db, string $context, int $itemId, array $link, array $linkData): void
    {
        $now = Factory::getDate()->toSql();

        $object = (object) [
            'source_context'  => $context,
            'source_id'       => $itemId,
            'source_url'      => '',
            'target_url'      => $linkData['normalized_url'],
            'target_url_hash' => md5($linkData['normalized_url']),
            'link_text'       => $link['text'],
            'link_type'       => $linkData['link_type'],
            'target_option'   => $linkData['target_option'] ?? '',
            'target_view'     => $linkData['target_view'] ?? '',
            'target_id'       => (isset($linkData['target_id']) && is_numeric($linkData['target_id'])) ? (int) $linkData['target_id'] : null,
            'is_nofollow'     => $link['is_nofollow'] ? 1 : 0,
            'is_sponsored'    => $link['is_sponsored'] ? 1 : 0,
            'is_ugc'          => $link['is_ugc'] ? 1 : 0,
            'status_code'     => null,
            'status_checked'  => null,
            'created'         => $now
        ];

        $db->insertObject('#__phocaseo_links', $object);
    }

    /**
     * Check HTTP status for unchecked links
     *
     * @param   int   $limit    Maximum links to check
     * @param   int   $timeout  Request timeout in seconds
     *
     * @return  array Summary of checked links
     *
     * @since   6.1.0
     */
    public static function checkLinkStatuses(int $limit = 50, int $timeout = 5): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select(['id', 'target_url'])
            ->from($db->quoteName('#__phocaseo_links'))
            ->where('(' .
                $db->quoteName('status_checked') . ' IS NULL OR ' .
                $db->quoteName('status_checked') . ' < DATE_SUB(NOW(), INTERVAL 24 HOUR)' .
            ')');

        $db->setQuery($query, 0, $limit);
        $links = $db->loadObjectList();

        $checked = 0;
        $errors = 0;

        foreach ($links as $link) {
            $status = self::checkUrl($link->target_url, $timeout);

            $update = (object) [
                'id' => $link->id,
                'status_code' => $status['code'],
                'status_checked' => Factory::getDate()->toSql()
            ];

            $db->updateObject('#__phocaseo_links', $update, 'id');

            $checked++;
            if ($status['code'] >= 400) {
                $errors++;
            }

            usleep(100000);
        }

        return [
            'checked' => $checked,
            'errors' => $errors
        ];
    }

    /**
     * Get count of links that need checking
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function getUncheckedLinksCount(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__phocaseo_links'))
            ->where('(' .
                $db->quoteName('status_checked') . ' IS NULL OR ' .
                $db->quoteName('status_checked') . ' < DATE_SUB(NOW(), INTERVAL 24 HOUR)' .
            ')');

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Check HTTP status of a URL
     *
     * @param   string  $url      URL to check
     * @param   int     $timeout  Timeout in seconds
     *
     * @return  array   Status info
     *
     * @since   6.1.0
     */
    public static function checkUrl(string $url, int $timeout = 5): array
    {
        $result = [
            'code' => 0,
            'message' => '',
            'redirect_url' => null
        ];

        if (empty($url)) {
            $result['code'] = 0;
            $result['message'] = 'Empty URL';
            return $result;
        }

        try {
            $http = HttpFactory::getHttp();
            $response = $http->head($url, [], $timeout);

            $statusCode = $response->getStatusCode();
            $result['code'] = $statusCode;

            if ($statusCode >= 300 && $statusCode < 400) {
                $headers = $response->getHeaders();
                $location = $headers['Location'][0] ?? $headers['location'][0] ?? '';
                if ($location) {
                    $result['redirect_url'] = $location;
                }
            }

            $result['message'] = self::getStatusMessage($statusCode);
        } catch (\Exception $e) {
            $result['code'] = 0;
            $result['message'] = 'Connection failed: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Get human-readable status message
     *
     * @param   int  $code  HTTP status code
     *
     * @return  string
     *
     * @since   6.1.0
     */
    protected static function getStatusMessage(int $code): string
    {
        $messages = [
            200 => 'OK',
            201 => 'Created',
            301 => 'Moved Permanently',
            302 => 'Found (Redirect)',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            410 => 'Gone',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];

        return $messages[$code] ?? 'Unknown Status';
    }

    public static function getStatusCssClass(?int $code): string {
        
        if ($code >= 200 && $code < 300) {
            return 'bg-success';
        }

        if ($code >= 300 && $code < 400) {
            return 'bg-info';
        }

        if ($code >= 400 && $code < 500) {
            return 'bg-danger';
        }

        if ($code >= 500) {
            return 'bg-danger';
        }

        return 'bg-secondary';
    }

    public static function getLinkCssClass(?int $linkCount): string {
        
        if ((int)$linkCount > 0) {
            return 'bg-success';
        }

        return 'bg-primary';
    }

    /**
     * Get flat list of all links
     *
     * @param   int     $limit      Max results
     * @param   int     $offset     Offset
     * @param   string  $ordering   Column to order by
     * @param   string  $direction  Order direction (asc or desc)
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public static function getLinks(int $limit = 50, int $offset = 0, string $ordering = 'l.id', string $direction = 'desc'): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                'l.*',
                'c.title as source_title'
            ])
            ->from($db->quoteName('#__phocaseo_links', 'l'))
            ->join('LEFT', $db->quoteName('#__content', 'c') . ' ON c.id = l.source_id AND l.source_context = ' . $db->quote('com_content.article'))
            ->order($db->escape(self::sanitizeLinksOrdering($ordering) . ' ' . self::sanitizeDirection($direction)));

        $db->setQuery($query, (int) $offset, (int) $limit);

        return $db->loadObjectList();
    }

    /**
     * Get total count of links
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function getLinksCount(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__phocaseo_links', 'l'));

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get link analysis summary for a context
     *
     * @param   string  $context  Context to analyze
     *
     * @return  array   Analysis summary
     *
     * @since   6.1.0
     */
    public static function getAnalysisSummary(string $context = ''): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true);
        $query->select([
            'link_type',
            'COUNT(*) as total',
            'SUM(CASE WHEN status_code = 200 THEN 1 ELSE 0 END) as status_200',
            'SUM(CASE WHEN status_code BETWEEN 300 AND 399 THEN 1 ELSE 0 END) as status_3xx',
            'SUM(CASE WHEN status_code = 404 THEN 1 ELSE 0 END) as status_404',
            'SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as status_5xx',
            'SUM(CASE WHEN status_code IS NULL THEN 1 ELSE 0 END) as unchecked'
        ])
        ->from($db->quoteName('#__phocaseo_links'))
        ->group('link_type');

        if ($context) {
            $query->where($db->quoteName('source_context') . ' = :context')
                ->bind(':context', $context);
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList('link_type');

        $defaultInternal =  [
            'link_type' => 'external',
            'total'  => 8,
            'status_200' => '0',
            'status_3xx' => '0',
            'status_404' => '0',
            'status_5xx' => '0',
            'unchecked' => '0'
        ];
        $defaultExternal =  [
            'link_type' => 'external',
            'total'  => 8,
            'status_200' => '0',
            'status_3xx' => '0',
            'status_404' => '0',
            'status_5xx' => '0',
            'unchecked' => '0'
        ];

        return [
            'internal' => $rows['internal'] ?? (object) $defaultInternal,
            'external' => $rows['external'] ?? (object) $defaultExternal
        ];
    }

    /**
     * Get top linked pages
     *
     * @param   int     $limit      Max results
     * @param   int     $offset     Offset
     * @param   string  $search     Search filter
     * @param   string  $ordering   Column to order by
     * @param   string  $direction  Order direction (asc or desc)
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public static function getTopLinkedPages(int $limit = 20, int $offset = 0, string $search = '', string $ordering = 'link_count', string $direction = 'desc'): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                'a.id',
                'a.title',
                'a.alias',
                'a.created',
                'a.hits',
                'l.link_type',
                'COUNT(l.id) as link_count'
            ])
            ->from($db->quoteName('#__phocaseo_links', 'l'))
            ->join('INNER', $db->quoteName('#__content', 'a') . ' ON l.target_id = a.id AND l.target_option = ' . $db->quote('com_content') . ' AND l.target_view = ' . $db->quote('article'))
            ->where($db->quoteName('l.link_type') . ' = ' . $db->quote('internal'))
            ->group('a.id, a.title, a.alias, a.created, a.hits, l.link_type')
            ->order($db->escape(self::sanitizePagesOrdering($ordering) . ' ' . self::sanitizeDirection($direction)));

        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' . $db->quoteName('a.alias') . ' LIKE :search)')
                ->bind(':search', $search);
        }

        $db->setQuery($query, (int) $offset, (int) $limit);

        return $db->loadObjectList();
    }

    /**
     * Get count of top linked pages
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function getTopLinkedPagesCount(string $search = ''): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT a.id)')
            ->from($db->quoteName('#__phocaseo_links', 'l'))
            ->join('INNER', $db->quoteName('#__content', 'a') . ' ON l.target_id = a.id AND l.target_option = ' . $db->quote('com_content') . ' AND l.target_view = ' . $db->quote('article'))
            ->where($db->quoteName('l.link_type') . ' = ' . $db->quote('internal'));

        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' . $db->quoteName('a.alias') . ' LIKE :search)')
                ->bind(':search', $search);
        }

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get broken links (404s)
     *
     * @param   int     $limit      Max results
     * @param   int     $offset     Offset
     * @param   string  $ordering   Column to order by
     * @param   string  $direction  Order direction (asc or desc)
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public static function getBrokenLinks(int $limit = 50, int $offset = 0, string $ordering = 'l.status_checked', string $direction = 'desc'): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                'l.*',
                'c.title as source_title'
            ])
            ->from($db->quoteName('#__phocaseo_links', 'l'))
            ->join('LEFT', $db->quoteName('#__content', 'c') . ' ON c.id = l.source_id AND l.source_context = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('l.status_code') . ' = 404')
            ->order($db->escape(self::sanitizeLinksOrdering($ordering) . ' ' . self::sanitizeDirection($direction)));

        $db->setQuery($query, (int) $offset, (int) $limit);

        return $db->loadObjectList();
    }

    /**
     * Get count of broken links (404s)
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function getBrokenLinksCount(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__phocaseo_links', 'l'))
            ->where($db->quoteName('l.status_code') . ' = 404');

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Find orphan pages (no inbound links)
     *
     * @param   int     $limit      Max results
     * @param   int     $offset     Offset
     * @param   string  $search     Search filter
     * @param   string  $ordering   Column to order by
     * @param   string  $direction  Order direction (asc or desc)
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public static function getOrphanPages(int $limit = 50, int $offset = 0, string $search = '', string $ordering = 'a.created', string $direction = 'desc'): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                'a.id',
                'a.title',
                'a.alias',
                'a.created',
                'a.hits'
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->where('NOT EXISTS (' .
                'SELECT 1 FROM ' . $db->quoteName('#__phocaseo_links', 'l') .
                ' WHERE l.target_option = ' . $db->quote('com_content') .
                ' AND l.target_view = ' . $db->quote('article') .
                ' AND l.target_id = a.id' .
                ' AND l.link_type = ' . $db->quote('internal') .
            ')');

        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' . $db->quoteName('a.alias') . ' LIKE :search)')
                ->bind(':search', $search);
        }

        $query->order($db->escape(self::sanitizePagesOrdering($ordering) . ' ' . self::sanitizeDirection($direction)));

        $db->setQuery($query, (int) $offset, (int) $limit);

        return $db->loadObjectList();
    }

    /**
     * Get count of orphan pages (no inbound links)
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function getOrphanPagesCount(string $search = ''): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content', 'a'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->where('NOT EXISTS (' .
                'SELECT 1 FROM ' . $db->quoteName('#__phocaseo_links', 'l') .
                ' WHERE l.target_option = ' . $db->quote('com_content') .
                ' AND l.target_view = ' . $db->quote('article') .
                ' AND l.target_id = a.id' .
                ' AND l.link_type = ' . $db->quote('internal') .
            ')');

        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' . $db->quoteName('a.alias') . ' LIKE :search)')
                ->bind(':search', $search);
        }

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get total count of unique linked pages
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function getTotalUniqueLinkedPagesCount(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT target_url_hash)')
            ->from($db->quoteName('#__phocaseo_links'));

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get all pages with inbound link counts
     *
     * @param   int     $limit      Limit
     * @param   int     $offset     Offset
     * @param   string  $search     Search filter
     * @param   string  $ordering   Column to order by
     * @param   string  $direction  Order direction (asc or desc)
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public static function getAllPages(int $limit = 50, int $offset = 0, string $search = '', string $ordering = 'a.id', string $direction = 'desc'): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                'a.id',
                'a.title',
                'a.alias',
                'a.created',
                'a.hits',
                'COUNT(l.id) as link_count'
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__phocaseo_links', 'l') . ' ON l.target_id = a.id AND l.target_option = ' . $db->quote('com_content') . ' AND l.target_view = ' . $db->quote('article') . ' AND l.link_type = ' . $db->quote('internal'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->group('a.id, a.title, a.alias, a.created, a.hits')
            ->order($db->escape(self::sanitizePagesOrdering($ordering) . ' ' . self::sanitizeDirection($direction)));

        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' . $db->quoteName('a.alias') . ' LIKE :search)')
                ->bind(':search', $search);
        }

        $db->setQuery($query, (int) $offset, (int) $limit);
        return $db->loadObjectList();
    }

    /**
     * Get total count of pages
     *
     * @param   string  $search  Search filter
     *
     * @return  int
     *
     * @since   6.1.0
     */
    public static function getAllPagesCount(string $search = ''): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content', 'a'))
            ->where($db->quoteName('a.state') . ' = 1');

        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' . $db->quoteName('a.alias') . ' LIKE :search)')
                ->bind(':search', $search);
        }

        $db->setQuery($query);
        return (int) $db->loadResult();
    }

    /**
     * Sanitize ordering column for links queries
     *
     * @param   string  $ordering  Requested ordering column
     *
     * @return  string  Safe ordering column
     *
     * @since   6.1.0
     */
    protected static function sanitizeLinksOrdering(string $ordering): string
    {
        $allowed = ['l.id', 'l.target_url', 'l.status_code', 'l.status_checked', 'l.created', 'l.link_type'];

        return in_array($ordering, $allowed, true) ? $ordering : 'l.id';
    }

    /**
     * Sanitize ordering column for pages queries
     *
     * @param   string  $ordering  Requested ordering column
     *
     * @return  string  Safe ordering column
     *
     * @since   6.1.0
     */
    protected static function sanitizePagesOrdering(string $ordering): string
    {
        $allowed = ['a.id', 'a.title', 'a.alias', 'a.created', 'a.hits', 'link_count'];

        return in_array($ordering, $allowed, true) ? $ordering : 'a.id';
    }

    /**
     * Sanitize direction for ordering
     *
     * @param   string  $direction  Requested direction
     *
     * @return  string  Safe direction
     *
     * @since   6.1.0
     */
    protected static function sanitizeDirection(string $direction): string
    {
        return strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    }
}
