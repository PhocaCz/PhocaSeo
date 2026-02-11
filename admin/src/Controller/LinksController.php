<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Controller;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\CMS\Component\ComponentHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper;

defined('_JEXEC') or die;


class LinksController extends BaseController
{
    /**
     * Scan all published content for links
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function scanAll(): void
    {
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

        $user = Factory::getUser();
        if (!$user->authorise('core.edit', 'com_phocaseo')) {
            $this->setRedirect(
                Route::_('index.php?option=com_phocaseo&view=links', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('introtext'),
                $db->quoteName('fulltext')
            ])
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');

        $db->setQuery($query);
        $articles = $db->loadObjectList();

        $totalLinks = 0;
        $totalInternal = 0;
        $totalExternal = 0;

        foreach ($articles as $article) {
            $content = ($article->introtext ?? '') . ' ' . ($article->fulltext ?? '');
            if (empty(trim($content))) {
                continue;
            }

            $result = LinkScannerHelper::scanContent('com_content.article', (int) $article->id, $content);
            $totalLinks += $result['total'];
            $totalInternal += $result['internal'];
            $totalExternal += $result['external'];
        }

        $catQuery = $db->getQuery(true)
            ->select(['id', 'description'])
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' = 1');

        $db->setQuery($catQuery);
        $categories = $db->loadObjectList();

        foreach ($categories as $category) {
            if (empty(trim($category->description ?? ''))) {
                continue;
            }

            $result = LinkScannerHelper::scanContent('com_content.category', (int) $category->id, $category->description);
            $totalLinks += $result['total'];
            $totalInternal += $result['internal'];
            $totalExternal += $result['external'];
        }

        $message = Text::sprintf('COM_PHOCASEO_SCAN_COMPLETE', $totalLinks, $totalInternal, $totalExternal);

        $this->setRedirect(
            Route::_('index.php?option=com_phocaseo&view=links', false),
            $message,
            'success'
        );
    }

    /**
     * Check HTTP status codes for unchecked links
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function checkStatuses(): void
    {
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

        $user = Factory::getUser();
        if (!$user->authorise('core.edit', 'com_phocaseo')) {
            $this->setRedirect(
                Route::_('index.php?option=com_phocaseo&view=links', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );
            return;
        }

        $result = LinkScannerHelper::checkLinkStatuses(50, 5);

        $message = Text::sprintf('COM_PHOCASEO_CHECK_COMPLETE', $result['checked'], $result['errors']);

        $this->setRedirect(
            Route::_('index.php?option=com_phocaseo&view=links', false),
            $message,
            $result['errors'] > 0 ? 'warning' : 'success'
        );
    }

    /**
     * Check a batch of links (AJAX)
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function checkBatch(): void
    {
        Session::checkToken('get') or Session::checkToken() or die(json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]));

        $app = Factory::getApplication();
        $user = Factory::getUser();

        if (!$user->authorise('core.edit', 'com_phocaseo')) {
            echo json_encode(['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
            $app->close();
            return;
        }

        $params = ComponentHelper::getParams('com_phocaseo');
        $batchSize = (int) $params->get('batch_size', 20);

        $result = LinkScannerHelper::checkLinkStatuses($batchSize, 5);
        $remaining = LinkScannerHelper::getUncheckedLinksCount();

        echo json_encode([
            'success'   => true,
            'checked'   => $result['checked'],
            'errors'    => $result['errors'],
            'remaining' => $remaining
        ]);

        $app->close();
    }

    /**
     * Scan a batch of content (AJAX)
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function scanBatch(): void
    {
        Session::checkToken('get') or Session::checkToken() or die(json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]));

        $app = Factory::getApplication();
        $user = Factory::getUser();

        if (!$user->authorise('core.edit', 'com_phocaseo')) {
            echo json_encode(['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
            $app->close();
            return;
        }

        $params = ComponentHelper::getParams('com_phocaseo');
        $batchSize = (int) $params->get('batch_size', 20);
        $offset = $app->input->getInt('offset', 0);

        $result = LinkScannerHelper::scanBatchItems($offset, $batchSize);
        $totalItems = LinkScannerHelper::getTotalScanItemsCount();
        $newOffset = $offset + $result['items_processed'];
        $remaining = $totalItems - $newOffset;

        echo json_encode([
            'success'   => true,
            'processed' => $result['items_processed'],
            'total'     => $result['total'],
            'internal'  => $result['internal'],
            'external'  => $result['external'],
            'remaining' => $remaining,
            'offset'    => $newOffset
        ]);

        $app->close();
    }

    /**
     * Scan a single item's content (AJAX)
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function scanItem(): void
    {
        Session::checkToken('get') or Session::checkToken() or die(json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]));

        $app = Factory::getApplication();
        
        $itemId = $app->input->getInt('item_id', 0);
        $context = $app->input->getString('context', 'com_content.article');
        $content = $app->input->get('content', '', 'raw');

        if ($itemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
            $app->close();
            return;
        }

        if (empty($content)) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            
            switch ($context) {
                case 'com_content.article':
                    $query = $db->getQuery(true)
                        ->select([
                            $db->quoteName('introtext'),
                            $db->quoteName('fulltext')
                        ])
                        ->from($db->quoteName('#__content'))
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':id', $itemId, ParameterType::INTEGER);
                        
                    $db->setQuery($query);
                    $article = $db->loadObject();
                    $content = ($article->introtext ?? '') . ' ' . ($article->fulltext ?? '');
                    break;

                case 'com_content.category':
                    $query = $db->getQuery(true)
                        ->select('description')
                        ->from($db->quoteName('#__categories'))
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':id', $itemId, ParameterType::INTEGER);
                    $db->setQuery($query);
                    $content = (string) $db->loadResult();
                    break;
            }
        }

        $result = LinkScannerHelper::scanContent($context, $itemId, $content);

        echo json_encode([
            'success' => true,
            'data' => $result
        ]);

        $app->close();
    }
}
