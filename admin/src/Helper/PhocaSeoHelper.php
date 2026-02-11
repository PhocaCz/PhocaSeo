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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Database\ParameterType;
use Joomla\CMS\Installer\Installer;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Phoca SEO Helper
 *
 * Bridges the component data with plugins and other extensions.
 *
 * @since  6.0.0
 */
class PhocaSeoHelper
{
    /**
     * Get SEO metadata for a specific item
     *
     * @param   string  $context  The context (e.g. com_content.article)
     * @param   int     $itemId   The item ID
     *
     * @return  object|null  Object with metadata properties or null if not found
     *
     * @since   6.0.0
     */
    public static function getMetadata(string $context, int $itemId): ?object
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__phocaseo_metadata'))
                ->where($db->quoteName('context') . ' = :context')
                ->where($db->quoteName('item_id') . ' = :itemId')
                ->bind(':context', $context)
                ->bind(':itemId', $itemId,ParameterType::INTEGER);

            $db->setQuery($query);

            return $db->loadObject();
        } catch (\Exception $e) {
            // Fail silently in helper if table doesn't exist or other DB error
            return null;
        }
    }

    /**
     * Get the effective OG Title (Override > Native > Default)
     *
     * @param   object|null  $metadata    The PhocaSEO metadata object
     * @param   string       $nativeTitle The title from the content/document
     *
     * @return  string
     *
     * @since   6.0.0
     */
    public static function getEffectiveOgTitle(?object $metadata, string $nativeTitle): string
    {
        if ($metadata && !empty($metadata->og_title)) {
            return $metadata->og_title;
        }

        // If extension has no native meta support, check our fallback meta_title
        if ($metadata && !empty($metadata->meta_title) && empty($nativeTitle)) {
             return $metadata->meta_title;
        }

        return $nativeTitle;
    }

    /**
     * Get the effective OG Description (Override > Native > Default)
     *
     * @param   object|null  $metadata          The PhocaSEO metadata object
     * @param   string       $nativeDescription The description from content/document
     *
     * @return  string
     *
     * @since   6.0.0
     */
    public static function getEffectiveOgDescription(?object $metadata, string $nativeDescription): string
    {
        if ($metadata && !empty($metadata->og_description)) {
            return $metadata->og_description;
        }

        // If extension has no native meta support, check our fallback meta_description
        if ($metadata && !empty($metadata->meta_description) && empty($nativeDescription)) {
             return $metadata->meta_description;
        }

        return $nativeDescription;
    }

    /**
     * Get the effective OG Image (Override > Native)
     *
     * @param   object|null  $metadata     The PhocaSEO metadata object
     * @param   string       $nativeImage  The image from content
     *
     * @return  string
     *
     * @since   6.0.0
     */
    public static function getEffectiveOgImage(?object $metadata, string $nativeImage): string
    {
        if ($metadata && !empty($metadata->og_image)) {
            return $metadata->og_image;
        }

        return $nativeImage;
    }

    /**
     * Get the best social image for an item following Phoca Open Graph flow
     *
     * @param   string  $context  Context
     * @param   int     $itemId   Item ID
     *
     * @return  string  Image URL or empty string
     *
     * @since   6.1.0
     */
    public static function getSocialImage(string $context, int $itemId, array $overrides = []): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $metadata = self::getMetadata($context, $itemId);

        // 1. PhocaSEO Metadata (Override)
        // If we have unsaved override in sidebar, prioritize it
        if (!empty($overrides['og_image'])) {
            return $overrides['og_image'];
        }
        if ($metadata && !empty($metadata->og_image)) {
            return $metadata->og_image;
        }

        // 2 & 3. Article Images (Intro/Full)
        if ($context === 'com_content.article') {
            $images = null;
            if (isset($overrides['images'])) {
                $images = (object)$overrides['images'];
            } else {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('images'))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $itemId, ParameterType::INTEGER);
                $db->setQuery($query);
                $imagesStr = $db->loadResult();
                $images = json_decode($imagesStr ?: '{}');
            }

            if (!empty($images->image_intro)) {
                return $images->image_intro;
            }
            if (!empty($images->image_fulltext)) {
                return $images->image_fulltext;
            }

            // 4. Image in Content
            $content = '';
            if (isset($overrides['content'])) {
                $content = $overrides['content'];
            } else {
                $query = $db->getQuery(true)
                    ->select([$db->quoteName('introtext'), $db->quoteName('fulltext')])
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $itemId, ParameterType::INTEGER);
                $db->setQuery($query);
                $row = $db->loadObject();
                if ($row) {
                    $content = ($row->introtext ?? '') . ($row->fulltext ?? '');
                }
            }

            if (!empty($content)) {
                preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $content, $src);
                if (isset($src[1]) && !empty($src[1])) {
                    return $src[1];
                }
            }

            // 5. Category Image
            $query = $db->getQuery(true)
                ->select($db->quoteName('c.params'))
                ->from($db->quoteName('#__categories', 'c'))
                ->join('INNER', $db->quoteName('#__content', 'a') . ' ON a.catid = c.id')
                ->where($db->quoteName('a.id') . ' = :id')
                ->bind(':id', $itemId, ParameterType::INTEGER);
            $db->setQuery($query);
            $catParamsStr = $db->loadResult();
            $catParams = json_decode($catParamsStr ?: '{}');
            if (!empty($catParams->image)) {
                return $catParams->image;
            }

        } elseif ($context === 'com_content.category') {
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $itemId, ParameterType::INTEGER);
            $db->setQuery($query);
            $paramsStr = $db->loadResult();
            $params = json_decode($paramsStr ?: '{}');
            if (!empty($params->image)) {
                return $params->image;
            }
        }

        return '';
    }
    /**
     * Get list of possible canonical routes for an article
     *
     * @param   int  $articleId  Article ID
     *
     * @return  array  List of routes ['url' => string, 'note' => string]
     *
     * @since   6.1.1
     */
    public static function getCanonicalRoutes(int $articleId): array
    {
        if ($articleId <= 0) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $routes = [];

        // Get Article CatID and Language
        $query = $db->getQuery(true)
            ->select(['catid', 'language'])
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $articleId, ParameterType::INTEGER);
        $db->setQuery($query);
        $article = $db->loadObject();

        if (!$article) {
            return [];
        }

        // 1. Find Menu Items directly for this Article
        $query = $db->getQuery(true)
            ->select(['id', 'title', 'path', 'language'])
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_content%view=article%id=' . $articleId . '%'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('client_id') . ' = 0');

        $db->setQuery($query);
        $menuItems = $db->loadObjectList();

        foreach ($menuItems as $item) {
            $url = Route::link('site', 'index.php?option=com_content&view=article&id=' . $articleId . '&Itemid=' . $item->id);

            $uri = Uri::getInstance($url);
            $fullUrl = $uri->toString(['scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment']);


            $routes[] = [
                'url' => $url,
                'note' => Text::_('COM_PHOCASEO_MENU_ITEM') . ': ' . $item->title . ' (' . $item->language . ')'
            ];
        }

        // 2. Find Menu Items for Category (Category Blog/List)
        // Article URL via Category Menu Item
        $catId = (int) $article->catid;
        if ($catId > 0) {
            $query = $db->getQuery(true)
                ->select(['id', 'title', 'path', 'language'])
                ->from($db->quoteName('#__menu'))
                ->where('(' .
                    $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_content%view=category%id=' . $catId . '%') .
                    ' OR ' . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_content%view=blog%id=' . $catId . '%') .
                ')')
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('client_id') . ' = 0');

            $db->setQuery($query);
            $catItems = $db->loadObjectList();

            foreach ($catItems as $item) {
                $url = Route::link('site', 'index.php?option=com_content&view=article&id=' . $articleId . '&catid=' . $catId . '&Itemid=' . $item->id);
                $routes[] = [
                    'url' => $url,
                    'note' => Text::_('COM_PHOCASEO_MENU_ITEM') . ': ' . $item->title . ' (' . $item->language . ')'
                ];
            }
        }

        // 3. Fallback / Default Route (no explicit Itemid, let Router decide)
        // This usually picks the "best" one, which might duplicate one of the above.
        // We can add it as "Router Default"
        $defaultUrl = Route::link('site', 'index.php?option=com_content&view=article&id=' . $articleId . ($catId ? '&catid='.$catId : ''));

        // Check if unique
        $found = false;
        foreach ($routes as $r) {
            if ($r['url'] === $defaultUrl) {
                $found = true;
                if (strpos($r['note'], 'Default') === false) $r['note'] .= ' (Default)';
                break;
            }
        }
        if (!$found) {
             $routes[] = ['url' => $defaultUrl, 'note' => Text::_('COM_PHOCASEO_ROUTER_DEFAULT')];
        }

        return $routes;
    }

    public static function checkSitemap(): array {
        $result = [
            'root_sitemap' => false,
            'robots_link'  => false,
            'robots_valid' => false,
            'link'         => ''
        ];

        // 1. Check root sitemap.xml
        $rootSitemap = JPATH_ROOT . '/sitemap.xml';
        if (file_exists($rootSitemap)) {
            $result['root_sitemap'] = true;
        }

        // 2. Check robots.txt for Sitemap directive
        $robotsPath = JPATH_ROOT . '/robots.txt';
        if (file_exists($robotsPath)) {
            $robotsContent = file_get_contents($robotsPath);
            if (preg_match('/Sitemap:\s*(.+)/i', $robotsContent, $matches)) {
                $result['robots_link'] = true;
                $sitemapUrl = trim($matches[1]);
                $result['link'] = $sitemapUrl;

                // 3. Validate the link (simple availability check)
                // Use default_socket_timeout to prevent long hangs
                $context = stream_context_create(['http' => ['timeout' => 5]]);
                $headers = @get_headers($sitemapUrl, false, $context);

                if ($headers && strpos($headers[0], '200') !== false) {
                    $result['robots_valid'] = true;
                }
            }
        }

        return $result;
    }

    public static function getPhocaVersion($component = 'com_phocaseo') {
		$component = 'com_phocaseo';
		$folder    = JPATH_ADMINISTRATOR . '/components' . '/' . $component;

		if (is_dir($folder)) {
			$xmlFilesInDir = Folder::files($folder, '.xml$');
		} else {
			$folder = JPATH_SITE . '/components' . '/' . $component;
			if (is_dir($folder)) {
				$xmlFilesInDir = Folder::files($folder, '.xml$');
			} else {
				$xmlFilesInDir = null;
			}
		}

		$xml_items = array();
		if (count($xmlFilesInDir)) {
			foreach ($xmlFilesInDir as $xmlfile) {
				if ($data = Installer::parseXMLInstallFile($folder . '/' . $xmlfile)) {
					foreach ($data as $key => $value) {
						$xml_items[$key] = $value;
					}
				}
			}
		}

		if (isset($xml_items['version']) && $xml_items['version'] != '') {
			return $xml_items['version'];
		} else {
			return '';
		}
	}


    public static function renderSvg($title) {

        $svg = '';
        switch($title) {

            case 'edit':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M200,88l-72,72H96V128l72-72Z" opacity="0.2"/><path d="M229.66,58.34l-32-32a8,8,0,0,0-11.32,0l-96,96A8,8,0,0,0,88,128v32a8,8,0,0,0,8,8h32a8,8,0,0,0,5.66-2.34l96-96A8,8,0,0,0,229.66,58.34ZM124.69,152H104V131.31l64-64L188.69,88ZM200,76.69,179.31,56,192,43.31,212.69,64ZM224,128v80a16,16,0,0,1-16,16H48a16,16,0,0,1-16-16V48A16,16,0,0,1,48,32h80a8,8,0,0,1,0,16H48V208H208V128a8,8,0,0,1,16,0Z"/></svg>';
            break;
            case 'publish':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';
            break;
            case 'unpublish':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M165.66,101.66,139.31,128l26.35,26.34a8,8,0,0,1-11.32,11.32L128,139.31l-26.34,26.35a8,8,0,0,1-11.32-11.32L116.69,128,90.34,101.66a8,8,0,0,1,11.32-11.32L128,116.69l26.34-26.35a8,8,0,0,1,11.32,11.32ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';
            break;
            case 'trash':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M200,56V208a8,8,0,0,1-8,8H64a8,8,0,0,1-8-8V56Z" opacity="0.2"/><path d="M216,48H176V40a24,24,0,0,0-24-24H104A24,24,0,0,0,80,40v8H40a8,8,0,0,0,0,16h8V208a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V64h8a8,8,0,0,0,0-16ZM96,40a8,8,0,0,1,8-8h48a8,8,0,0,1,8,8v8H96Zm96,168H64V64H192ZM112,104v64a8,8,0,0,1-16,0V104a8,8,0,0,1,16,0Zm48,0v64a8,8,0,0,1-16,0V104a8,8,0,0,1,16,0Z"/></svg>';
            break;

            case 'broken':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M204.28,108.28l-96,96a40,40,0,0,1-56.56-56.56l96-96a40,40,0,0,1,56.56,56.56Z" opacity="0.2"/><path d="M198.63,57.37a32,32,0,0,0-45.19-.06L141.79,69.52a8,8,0,0,1-11.58-11l11.72-12.29a1.59,1.59,0,0,1,.13-.13,48,48,0,0,1,67.88,67.88,1.59,1.59,0,0,1-.13.13l-12.29,11.72a8,8,0,0,1-11-11.58l12.21-11.65A32,32,0,0,0,198.63,57.37ZM114.21,186.48l-11.65,12.21a32,32,0,0,1-45.25-45.25l12.21-11.65a8,8,0,0,0-11-11.58L46.19,141.93a1.59,1.59,0,0,0-.13.13,48,48,0,0,0,67.88,67.88,1.59,1.59,0,0,0,.13-.13l11.72-12.29a8,8,0,1,0-11.58-11ZM216,152H192a8,8,0,0,0,0,16h24a8,8,0,0,0,0-16ZM40,104H64a8,8,0,0,0,0-16H40a8,8,0,0,0,0,16Zm120,80a8,8,0,0,0-8,8v24a8,8,0,0,0,16,0V192A8,8,0,0,0,160,184ZM96,72a8,8,0,0,0,8-8V40a8,8,0,0,0-16,0V64A8,8,0,0,0,96,72Z"/></svg>';
            break;

            case 'options':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M207.86,123.18l16.78-21a99.14,99.14,0,0,0-10.07-24.29l-26.7-3a81,81,0,0,0-6.81-6.81l-3-26.71a99.43,99.43,0,0,0-24.3-10l-21,16.77a81.59,81.59,0,0,0-9.64,0l-21-16.78A99.14,99.14,0,0,0,77.91,41.43l-3,26.7a81,81,0,0,0-6.81,6.81l-26.71,3a99.43,99.43,0,0,0-10,24.3l16.77,21a81.59,81.59,0,0,0,0,9.64l-16.78,21a99.14,99.14,0,0,0,10.07,24.29l26.7,3a81,81,0,0,0,6.81,6.81l3,26.71a99.43,99.43,0,0,0,24.3,10l21-16.77a81.59,81.59,0,0,0,9.64,0l21,16.78a99.14,99.14,0,0,0,24.29-10.07l3-26.7a81,81,0,0,0,6.81-6.81l26.71-3a99.43,99.43,0,0,0,10-24.3l-16.77-21A81.59,81.59,0,0,0,207.86,123.18ZM128,168a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z" opacity="0.2"/><path d="M128,80a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Zm88-29.84q.06-2.16,0-4.32l14.92-18.64a8,8,0,0,0,1.48-7.06,107.6,107.6,0,0,0-10.88-26.25,8,8,0,0,0-6-3.93l-23.72-2.64q-1.48-1.56-3-3L186,40.54a8,8,0,0,0-3.94-6,107.29,107.29,0,0,0-26.25-10.86,8,8,0,0,0-7.06,1.48L130.16,40Q128,40,125.84,40L107.2,25.11a8,8,0,0,0-7.06-1.48A107.6,107.6,0,0,0,73.89,34.51a8,8,0,0,0-3.93,6L67.32,64.27q-1.56,1.49-3,3L40.54,70a8,8,0,0,0-6,3.94,107.71,107.71,0,0,0-10.87,26.25,8,8,0,0,0,1.49,7.06L40,125.84Q40,128,40,130.16L25.11,148.8a8,8,0,0,0-1.48,7.06,107.6,107.6,0,0,0,10.88,26.25,8,8,0,0,0,6,3.93l23.72,2.64q1.49,1.56,3,3L70,215.46a8,8,0,0,0,3.94,6,107.71,107.71,0,0,0,26.25,10.87,8,8,0,0,0,7.06-1.49L125.84,216q2.16.06,4.32,0l18.64,14.92a8,8,0,0,0,7.06,1.48,107.21,107.21,0,0,0,26.25-10.88,8,8,0,0,0,3.93-6l2.64-23.72q1.56-1.48,3-3L215.46,186a8,8,0,0,0,6-3.94,107.71,107.71,0,0,0,10.87-26.25,8,8,0,0,0-1.49-7.06Zm-16.1-6.5a73.93,73.93,0,0,1,0,8.68,8,8,0,0,0,1.74,5.48l14.19,17.73a91.57,91.57,0,0,1-6.23,15L187,173.11a8,8,0,0,0-5.1,2.64,74.11,74.11,0,0,1-6.14,6.14,8,8,0,0,0-2.64,5.1l-2.51,22.58a91.32,91.32,0,0,1-15,6.23l-17.74-14.19a8,8,0,0,0-5-1.75h-.48a73.93,73.93,0,0,1-8.68,0,8.06,8.06,0,0,0-5.48,1.74L100.45,215.8a91.57,91.57,0,0,1-15-6.23L82.89,187a8,8,0,0,0-2.64-5.1,74.11,74.11,0,0,1-6.14-6.14,8,8,0,0,0-5.1-2.64L46.43,170.6a91.32,91.32,0,0,1-6.23-15l14.19-17.74a8,8,0,0,0,1.74-5.48,73.93,73.93,0,0,1,0-8.68,8,8,0,0,0-1.74-5.48L40.2,100.45a91.57,91.57,0,0,1,6.23-15L69,82.89a8,8,0,0,0,5.1-2.64,74.11,74.11,0,0,1,6.14-6.14A8,8,0,0,0,82.89,69L85.4,46.43a91.32,91.32,0,0,1,15-6.23l17.74,14.19a8,8,0,0,0,5.48,1.74,73.93,73.93,0,0,1,8.68,0,8.06,8.06,0,0,0,5.48-1.74L155.55,40.2a91.57,91.57,0,0,1,15,6.23L173.11,69a8,8,0,0,0,2.64,5.1,74.11,74.11,0,0,1,6.14,6.14,8,8,0,0,0,5.1,2.64l22.58,2.51a91.32,91.32,0,0,1,6.23,15l-14.19,17.74A8,8,0,0,0,199.87,123.66Z"/></svg>';
            break;
            case 'pages':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M208,88H152V32Z" opacity="0.2"/><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-45.54-48.85a36.05,36.05,0,1,0-11.31,11.31l11.19,11.2a8,8,0,0,0,11.32-11.32ZM104,148a20,20,0,1,1,20,20A20,20,0,0,1,104,148Z"/></svg>';
            break;
            case 'info':
                $svg =  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M144,176a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176Zm88-48A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128ZM124,96a12,12,0,1,0-12-12A12,12,0,0,0,124,96Z"/></svg>';
            break;
            case 'links':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M218.34,119.6,183.6,154.34a46.58,46.58,0,0,1-44.31,12.26c-.31.34-.62.67-.95,1L103.6,202.34A46.63,46.63,0,1,1,37.66,136.4L72.4,101.66A46.6,46.6,0,0,1,116.71,89.4c.31-.34.62-.67,1-1L152.4,53.66a46.63,46.63,0,0,1,65.94,65.94Z" opacity="0.2"/><path d="M240,88.23a54.43,54.43,0,0,1-16,37L189.25,160a54.27,54.27,0,0,1-38.63,16h-.05A54.63,54.63,0,0,1,96,119.84a8,8,0,0,1,16,.45A38.62,38.62,0,0,0,150.58,160h0a38.39,38.39,0,0,0,27.31-11.31l34.75-34.75a38.63,38.63,0,0,0-54.63-54.63l-11,11A8,8,0,0,1,135.7,59l11-11A54.65,54.65,0,0,1,224,48,54.86,54.86,0,0,1,240,88.23ZM109,185.66l-11,11A38.41,38.41,0,0,1,70.6,208h0a38.63,38.63,0,0,1-27.29-65.94L78,107.31A38.63,38.63,0,0,1,144,135.71a8,8,0,0,0,7.78,8.22H152a8,8,0,0,0,8-7.78A54.86,54.86,0,0,0,144,96a54.65,54.65,0,0,0-77.27,0L32,130.75A54.62,54.62,0,0,0,70.56,224h0a54.28,54.28,0,0,0,38.64-16l11-11A8,8,0,0,0,109,185.66Z"/></svg>';
            break;
            default:
            case 'items':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M240,104,128,168,16,104,128,40Z" opacity="0.2"/><path d="M12,111l112,64a8,8,0,0,0,7.94,0l112-64a8,8,0,0,0,0-13.9l-112-64a8,8,0,0,0-7.94,0l-112,64A8,8,0,0,0,12,111ZM128,49.21,223.87,104,128,158.79,32.13,104ZM247,140A8,8,0,0,1,244,151L132,215a8,8,0,0,1-7.94,0L12,151A8,8,0,1,1,20,137.05l108,61.74,108-61.74A8,8,0,0,1,247,140Z"/></svg>';
            break;

        }

        return $svg;

    }
}
