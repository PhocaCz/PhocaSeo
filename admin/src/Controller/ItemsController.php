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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\CMS\Router\Route;

defined('_JEXEC') or die;


class ItemsController extends BaseController
{
    /**
     * AJAX handler for inline field saving
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function inlineSave()
    {
        Session::checkToken() or die(json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]));

        $app = Factory::getApplication();
        $user = Factory::getUser();

        if (!$user->authorise('core.edit', 'com_phocaseo')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $app->close();
            return;
        }

        $input = $app->input;
        $itemId = $input->getInt('item_id', 0);
        $context = $input->get('context', '', 'string');
        $field = $input->get('field', '', 'string');
        $value = $input->get('value', '', 'raw');

        $allowedFields = [
            'title', 'metadesc', 'metakey', 'browser_page_title',
            'page_heading', 'focus_keyword', 'alias'
        ];

        if (!in_array($field, $allowedFields)) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_PHOCASEO_ERROR_INVALID_FIELD')]);
            $app->close();
            return;
        }

        if ($itemId <= 0 || empty($context)) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_PHOCASEO_ERROR_INVALID_ITEM')]);
            $app->close();
            return;
        }

        $value = $this->sanitizeValue($field, $value);

        try {
            $result = $this->saveFieldValue($context, $itemId, $field, $value);
            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }

    /**
     * Sanitize field value based on field type
     *
     * @param   string  $field  Field name
     * @param   string  $value  Raw value
     *
     * @return  string  Sanitized value
     *
     * @since   6.1.0
     */
    protected function sanitizeValue(string $field, string $value): string
    {
        $value = trim($value);
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        switch ($field) {
            case 'metadesc':
                $value = substr($value, 0, 320);
                break;
            case 'focus_keyword':
                $value = substr($value, 0, 255);
                break;
            case 'browser_page_title':
            case 'page_heading':
            case 'title':
                $value = substr($value, 0, 255);
                break;
            case 'alias':
                $value = preg_replace('/[^a-z0-9\-]/', '', strtolower($value));
                $value = substr($value, 0, 400);
                break;
        }

        return $value;
    }

    /**
     * Save field value to appropriate table
     *
     * @param   string  $context  Content context
     * @param   int     $itemId   Item ID
     * @param   string  $field    Field name
     * @param   string  $value    Value to save
     *
     * @return  array   Result array
     *
     * @since   6.1.0
     */
    protected function saveFieldValue(string $context, int $itemId, string $field, string $value): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        if ($field === 'focus_keyword') {
            return $this->savePhocaSeoField($db, $context, $itemId, $field, $value);
        }

        switch ($context) {
            case 'com_content.article':
                return $this->saveArticleField($db, $itemId, $field, $value);

            case 'com_content.category':
                return $this->saveCategoryField($db, $itemId, $field, $value);

            case 'com_menus.item':
                return $this->saveMenuItemField($db, $itemId, $field, $value);

            case 'com_phocacart.product':
                return $this->savePhocaCartProductField($db, $itemId, $field, $value);

            default:
                return ['success' => false, 'message' => Text::_('COM_PHOCASEO_ERROR_UNSUPPORTED_CONTEXT')];
        }
    }

    /**
     * Save field to #__phocacart_products table
     *
     * @param   DatabaseInterface  $db       Database
     * @param   int                $itemId   Item ID
     * @param   string             $field    Field name
     * @param   string             $value    Value
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected function savePhocaCartProductField(DatabaseInterface $db, int $itemId, string $field, string $value): array
    {
        $fieldMap = [
            'title' => 'title',
            'alias' => 'alias',
            'metadesc' => 'metadesc',
            'metakey' => 'metakey',
            'browser_page_title' => 'metatitle'
        ];

        if (isset($fieldMap[$field])) {
            $column = $fieldMap[$field];
            $object = (object) [
                'id' => $itemId,
                $column => $value,
                // Phoca Cart doesn't strictly require a modified date update for inline saves,
                // but if needed, we could add 'date_modified' => Factory::getDate()->toSql()
            ];
            $db->updateObject('#__phocacart_products', $object, 'id');
            return ['success' => true, 'message' => Text::_('COM_PHOCASEO_SAVED')];
        }

        return ['success' => false, 'message' => Text::_('COM_PHOCASEO_ERROR_INVALID_FIELD')];
    }

    /**
     * Save field to PhocaSEO metadata table
     *
     * @param   DatabaseInterface  $db       Database
     * @param   string             $context  Context
     * @param   int                $itemId   Item ID
     * @param   string             $field    Field name
     * @param   string             $value    Value
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected function savePhocaSeoField(DatabaseInterface $db, string $context, int $itemId, string $field, string $value): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__phocaseo_metadata'))
            ->where($db->quoteName('context') . ' = :context')
            ->where($db->quoteName('item_id') . ' = :itemId')
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        $db->setQuery($query);
        $id = (int) $db->loadResult();

        $now = Factory::getDate()->toSql();

        if ($id > 0) {
            $object = (object) [
                'id' => $id,
                $field => $value,
                'modified' => $now
            ];
            $db->updateObject('#__phocaseo_metadata', $object, 'id');
        } else {
            $object = (object) [
                'context' => $context,
                'item_id' => $itemId,
                $field => $value,
                'created' => $now,
                'modified' => $now
            ];
            $db->insertObject('#__phocaseo_metadata', $object);
        }

        return ['success' => true, 'message' => Text::_('COM_PHOCASEO_SAVED')];
    }

    /**
     * Save field to #__content table
     *
     * @param   DatabaseInterface  $db       Database
     * @param   int                $itemId   Item ID
     * @param   string             $field    Field name
     * @param   string             $value    Value
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected function saveArticleField(DatabaseInterface $db, int $itemId, string $field, string $value): array
    {
        $directFields = ['title', 'alias', 'metadesc', 'metakey'];

        if (in_array($field, $directFields)) {
            $object = (object) [
                'id' => $itemId,
                $field => $value,
                'modified' => Factory::getDate()->toSql()
            ];
            $db->updateObject('#__content', $object, 'id');
        } elseif ($field === 'browser_page_title') {
            $query = $db->getQuery(true)
                ->select($db->quoteName('attribs'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $itemId, ParameterType::INTEGER);

            $db->setQuery($query);
            $attribs = $db->loadResult();

            $params = json_decode($attribs ?: '{}', true) ?: [];
            $params['article_page_title'] = $value;

            $object = (object) [
                'id' => $itemId,
                'attribs' => json_encode($params),
                'modified' => Factory::getDate()->toSql()
            ];
            $db->updateObject('#__content', $object, 'id');
        }

        return ['success' => true, 'message' => Text::_('COM_PHOCASEO_SAVED')];
    }

    /**
     * Save field to #__categories table
     *
     * @param   DatabaseInterface  $db       Database
     * @param   int                $itemId   Item ID
     * @param   string             $field    Field name
     * @param   string             $value    Value
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected function saveCategoryField(DatabaseInterface $db, int $itemId, string $field, string $value): array
    {
        $directFields = ['title', 'alias', 'metadesc', 'metakey'];

        if (in_array($field, $directFields)) {
            $object = (object) [
                'id' => $itemId,
                $field => $value,
                'modified_time' => Factory::getDate()->toSql()
            ];
            $db->updateObject('#__categories', $object, 'id');
        }

        return ['success' => true, 'message' => Text::_('COM_PHOCASEO_SAVED')];
    }

    /**
     * Save field to #__menu table
     *
     * @param   DatabaseInterface  $db       Database
     * @param   int                $itemId   Item ID
     * @param   string             $field    Field name
     * @param   string             $value    Value
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected function saveMenuItemField(DatabaseInterface $db, int $itemId, string $field, string $value): array
    {
        $directFields = ['title', 'alias'];

        if (in_array($field, $directFields)) {
            $object = (object) [
                'id' => $itemId,
                $field => $value
            ];
            $db->updateObject('#__menu', $object, 'id');
        } else {
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $itemId, ParameterType::INTEGER);

            $db->setQuery($query);
            $paramsStr = $db->loadResult();

            $params = json_decode($paramsStr ?: '{}', true) ?: [];

            $fieldMap = [
                'browser_page_title' => 'page_title',
                'page_heading' => 'page_heading',
                'metadesc' => 'menu-meta_description',
                'metakey' => 'menu-meta_keywords'
            ];

            if (isset($fieldMap[$field])) {
                $params[$fieldMap[$field]] = $value;
            }

            $object = (object) [
                'id' => $itemId,
                'params' => json_encode($params)
            ];
            $db->updateObject('#__menu', $object, 'id');
        }

        return ['success' => true, 'message' => Text::_('COM_PHOCASEO_SAVED')];
    }

    /**
     * Batch save multiple items
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function batchSave()
    {
        Session::checkToken() or die(json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]));

        $app = Factory::getApplication();
        $user = Factory::getUser();

        if (!$user->authorise('core.edit', 'com_phocaseo')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $app->close();
            return;
        }

        $input = $app->input;
        $itemsJson = $input->get('items', '', 'raw');
        $items = json_decode($itemsJson, true);

        if (!is_array($items) || empty($items)) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_PHOCASEO_ERROR_NO_ITEMS')]);
            $app->close();
            return;
        }

        $results = [];
        $errors = [];

        foreach ($items as $item) {
            if (empty($item['item_id']) || empty($item['context']) || empty($item['field'])) {
                continue;
            }

            try {
                $value = $this->sanitizeValue($item['field'], $item['value'] ?? '');
                $result = $this->saveFieldValue($item['context'], (int) $item['item_id'], $item['field'], $value);

                if (!$result['success']) {
                    $errors[] = "Item {$item['item_id']}: {$result['message']}";
                } else {
                    $results[] = $item['item_id'];
                }
            } catch (\Exception $e) {
                $errors[] = "Item {$item['item_id']}: {$e->getMessage()}";
            }
        }

        $response = [
            'success' => count($errors) === 0,
            'saved' => count($results),
            'errors' => $errors
        ];

        if (count($errors) > 0) {
            $response['message'] = Text::sprintf('COM_PHOCASEO_BATCH_ERRORS', count($errors));
        } else {
            $response['message'] = Text::sprintf('COM_PHOCASEO_BATCH_SAVED', count($results));
        }

        echo json_encode($response);
        $app->close();
    }
    /**
     * Scan selected items for links
     *
     * @return  void
     *
     * @since   6.1.0
     */
    /*
    public function scanLinks()
    {
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

        $user = Factory::getUser();
        if (!$user->authorise('core.edit', 'com_phocaseo')) {
            $this->setRedirect(
                Route::_('index.php?option=com_phocaseo&view=items', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );
            return;
        }

        $app = Factory::getApplication();
        $input = $app->input;
        $cids = (array) $input->get('cid', [], 'array');

        if (empty($cids)) {
            $this->setRedirect(
                Route::_('index.php?option=com_phocaseo&view=items', false),
                Text::_('COM_PHOCASEO_ERROR_NO_ITEMS_SELECTED'),
                'warning'
            );
            return;
        }

        $filters = $input->get('filter', [], 'array');
        $context = $filters['context'] ?? 'com_content.article';

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $count = 0;

        foreach ($cids as $id) {
            $id = (int) $id;
            $content = '';

            if ($context === 'com_content.article') {
                $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName('introtext'),
                        $db->quoteName('fulltext')
                    ])
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER);
                $db->setQuery($query);
                $item = $db->loadObject();
                if ($item) {
                    $content = ($item->introtext ?? '') . ' ' . ($item->fulltext ?? '');
                }
            } elseif ($context === 'com_content.category') {
                $query = $db->getQuery(true)
                    ->select('description')
                    ->from($db->quoteName('#__categories'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER);
                $db->setQuery($query);
                $content = (string) $db->loadResult();
            }
            // Add other contexts if needed

            if (!empty($content)) {
                \Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper::scanContent($context, $id, $content);
                $count++;
            }
        }

        $this->setRedirect(
            Route::_('index.php?option=com_phocaseo&view=items', false),
            Text::sprintf('COM_PHOCASEO_ITEMS_SCANNED', $count),
            'success'
        );
    }*/
}
