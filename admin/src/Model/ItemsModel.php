<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\Database\ParameterType;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Component\ComponentHelper;

defined('_JEXEC') or die;

class ItemsModel extends ListModel
{
    /**
     * Context string for the model
     *
     * @var  string
     */
    protected $context = 'com_phocaseo.items';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'context',
                'seo_score',
                'focus_keyword',
                'state', 'a.state',
                'published', 'a.published',
                'metadesc',
                'metakey',
                'alias',
                'limit',
                'start'
            ];
        }

        parent::__construct($config);
    }

    /**
     * Override to manipulate the filter form
     *
     * @param   array    $data      An associative array of data to map to the form.
     * @param   boolean  $loadData  True if the form is to retrieve its own data.
     *
     * @return  \Joomla\CMS\Form\Form|boolean
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        $form = parent::getFilterForm($data, $loadData);

        if ($form instanceof Form) {
            // Remove Phoca Cart option if not installed
            if (!ComponentHelper::isEnabled('com_phocacart')) {
                $form->removeField('context', 'filter', 'com_phocacart.product');
            }
        }

        return $form;
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc or desc).
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function populateState($ordering = 'a.id', $direction = 'desc')
    {
        $app = Factory::getApplication();
        
        // Load the parameters.
        $params = ComponentHelper::getParams('com_phocaseo');
        $this->setState('params', $params);

        $ordering  = $app->getUserStateFromRequest($this->context . '.filter_order', 'filter_order', $ordering);
        $direction = $app->getUserStateFromRequest($this->context . '.filter_order_Dir', 'filter_order_Dir', $direction);

        // Handle fullordering
        $fullOrdering = $app->getUserStateFromRequest($this->context . '.list.fullordering', 'fullordering', '', 'string');
        if ($fullOrdering) {
            $parts = explode(' ', $fullOrdering);
            if (count($parts) === 2) {
                $ordering = $parts[0];
                $direction = $parts[1];
            }
        }

        $this->setState('list.ordering', $ordering);
        $this->setState('list.direction', $direction);

        parent::populateState($ordering, $direction);

        // Standard Joomla limit and start from searchtools
        $limit = $app->getUserStateFromRequest($this->context . '.list.limit', 'limit', $app->get('list_limit'), 'int');
        $this->setState('list.limit', $limit);

        $limitstart = $app->getUserStateFromRequest($this->context . '.list.start', 'limitstart', 0, 'int');
        $this->setState('list.start', $limitstart);

        // Filters
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        // Filters
        $context = $app->getUserStateFromRequest($this->context . '.filter.context', 'filter.context', 'com_content.article', 'string');
        $this->setState('filter.context', $context);
        
        $seoStatus = $app->getUserStateFromRequest($this->context . '.filter.seo_status', 'filter.seo_status', '', 'string');
        $this->setState('filter.seo_status', $seoStatus);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.0.0
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $context = $this->getState('filter.context');

        switch ($context) {
            case 'com_content.article':
                $query = $this->buildArticleQuery($db, $query);
                break;
            
            case 'com_content.category':
                $query = $this->buildCategoryQuery($db, $query);
                break;
            
            case 'com_menus.item':
                $query = $this->buildMenuItemQuery($db, $query);
                break;
            
            case 'com_phocacart.product':
                $query = $this->buildPhocaCartProductQuery($db, $query);
                break;
            
            default:
                $query->select('0 as id')->from($db->quoteName('#__content'))->where('1=0');
        }

        return $query;
    }

    /**
     * Build query for articles
     *
     * @param   \Joomla\Database\DatabaseInterface  $db     Database
     * @param   \Joomla\Database\DatabaseQuery      $query  Query
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.1.0
     */
    protected function buildArticleQuery($db, $query)
    {
        $context = 'com_content.article';

        $query->select([
            'a.id',
            'a.title',
            'a.alias',
            'a.state',
            'a.metadesc',
            'a.metakey',
            'a.checked_out',
            'a.checked_out_time',
            'a.catid',
            'a.access',
            'a.language',
            'JSON_UNQUOTE(JSON_EXTRACT(a.attribs, \'$.article_page_title\')) AS browser_page_title'
        ]);
        $query->from($db->quoteName('#__content', 'a'));

        $query->select('c.title AS category_title');
        $query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid');

        $query->select([
            's.seo_score',
            's.focus_keyword',
            's.id as phocaseo_id',
            's.canonical_url',
            's.sitemap_exclude'
        ]);
        $query->join(
            'LEFT',
            $db->quoteName('#__phocaseo_metadata', 's') 
            . ' ON s.item_id = a.id AND s.context = ' . $db->quote($context)
        );

        $this->applyFilters($db, $query, 'a');

        return $query;
    }

    /**
     * Build query for categories
     *
     * @param   \Joomla\Database\DatabaseInterface  $db     Database
     * @param   \Joomla\Database\DatabaseQuery      $query  Query
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.1.0
     */
    protected function buildCategoryQuery($db, $query)
    {
        $context = 'com_content.category';

        $query->select([
            'a.id',
            'a.title',
            'a.alias',
            'a.published AS state',
            'a.metadesc',
            'a.metakey',
            'a.checked_out',
            'a.checked_out_time',
            'a.access',
            'a.language',
            'a.params'
        ]);
        $query->from($db->quoteName('#__categories', 'a'));
        $query->where($db->quoteName('a.extension') . ' = ' . $db->quote('com_content'));

        $query->select([
            's.seo_score',
            's.focus_keyword',
            's.id as phocaseo_id',
            's.canonical_url',
            's.sitemap_exclude'
        ]);
        $query->join(
            'LEFT',
            $db->quoteName('#__phocaseo_metadata', 's') 
            . ' ON s.item_id = a.id AND s.context = ' . $db->quote($context)
        );

        $this->applyFilters($db, $query, 'a');

        return $query;
    }

    /**
     * Build query for menu items
     *
     * @param   \Joomla\Database\DatabaseInterface  $db     Database
     * @param   \Joomla\Database\DatabaseQuery      $query  Query
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.1.0
     */
    protected function buildMenuItemQuery($db, $query)
    {
        $context = 'com_menus.item';

        $query->select([
            'a.id',
            'a.title',
            'a.alias',
            'a.published AS state',
            'a.checked_out',
            'a.checked_out_time',
            'a.access',
            'a.language',
            'a.params',
            'a.menutype',
            'a.link',
            'JSON_UNQUOTE(JSON_EXTRACT(a.params, \'$.page_title\')) AS browser_page_title',
            'JSON_UNQUOTE(JSON_EXTRACT(a.params, \'$.page_heading\')) AS page_heading',
            'JSON_UNQUOTE(JSON_EXTRACT(a.params, \'$.menu-meta_description\')) AS metadesc',
            'JSON_UNQUOTE(JSON_EXTRACT(a.params, \'$.menu-meta_keywords\')) AS metakey'
        ]);
        $query->from($db->quoteName('#__menu', 'a'));
        $query->where($db->quoteName('a.client_id') . ' = 0');
        $query->where($db->quoteName('a.parent_id') . ' > 0');

        $query->select('m.title AS menutype_title');
        $query->join('LEFT', $db->quoteName('#__menu_types', 'm') . ' ON m.menutype = a.menutype');

        $query->select([
            's.seo_score',
            's.focus_keyword',
            's.id as phocaseo_id',
            's.canonical_url',
            's.sitemap_exclude'
        ]);
        $query->join(
            'LEFT',
            $db->quoteName('#__phocaseo_metadata', 's') 
            . ' ON s.item_id = a.id AND s.context = ' . $db->quote($context)
        );

        $this->applyFilters($db, $query, 'a');

        return $query;
    }

    /**
     * Apply common filters to query
     *
     * @param   \Joomla\Database\DatabaseInterface  $db     Database
     * @param   \Joomla\Database\DatabaseQuery      $query  Query
     * @param   string                              $alias  Table alias
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected function applyFilters($db, $query, string $alias)
    {
        $stateColumn = ($alias === 'a' && $this->getState('filter.context') !== 'com_content.article') 
            ? 'published' : 'state';

        $published = $this->getState('filter.published');

        if (is_numeric($published)) {
            $query->where($db->quoteName($alias . '.' . $stateColumn) . ' = ' . (int) $published);
        } elseif ($published === '') {
            $query->where('(' . $db->quoteName($alias . '.' . $stateColumn) . ' = 0 OR ' 
                . $db->quoteName($alias . '.' . $stateColumn) . ' = 1)');
        }

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where($db->quoteName($alias . '.id') . ' = ' . (int) substr($search, 3));
            } else {
                $search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
                $query->where('(' . $db->quoteName($alias . '.title') . ' LIKE ' . $search 
                    . ' OR ' . $db->quoteName($alias . '.alias') . ' LIKE ' . $search . ')');
            }
        }

        $seoFilter = $this->getState('filter.seo_status');
        if (!empty($seoFilter)) {
            switch ($seoFilter) {
                case 'good':
                    $query->where('s.seo_score >= 70');
                    break;
                case 'needs_improvement':
                    $query->where('s.seo_score >= 40 AND s.seo_score < 70');
                    break;
                case 'poor':
                    $query->where('s.seo_score > 0 AND s.seo_score < 40');
                    break;
                case 'not_analyzed':
                    $query->where('(s.seo_score IS NULL OR s.seo_score = 0)');
                    break;
            }
        }

        $orderCol = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'desc');
        
        if ($orderCol === 'seo_score') {
            $orderCol = 's.seo_score';
        } elseif ($orderCol === 'id') {
            $orderCol = $alias . '.id';
        }
        
        $query->order($db->escape($orderCol . ' ' . $orderDirn));
    }

    /**
     * Get context options for filter dropdown
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public static function getContextOptions(): array
    {
        $options = [
            'com_content.article'  => 'COM_PHOCASEO_CONTEXT_ARTICLE',
            'com_content.category' => 'COM_PHOCASEO_CONTEXT_CATEGORY',
            'com_menus.item'       => 'COM_PHOCASEO_CONTEXT_MENU_ITEM',
        ];

        // Add Phoca Cart products if installed
        if (ComponentHelper::isEnabled('com_phocacart')) {
            $options['com_phocacart.product'] = 'COM_PHOCASEO_CONTEXT_PHOCACART_PRODUCT';
        }

        return $options;
    }

    /**
     * Build query for Phoca Cart products
     *
     * @param   \Joomla\Database\DatabaseInterface  $db     Database
     * @param   \Joomla\Database\DatabaseQuery      $query  Query
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.1.0
     */
    protected function buildPhocaCartProductQuery($db, $query)
    {
        $context = 'com_phocacart.product';

        $query->select([
            'a.id',
            'a.title',
            'a.alias',
            'a.published AS state',
            'a.metadesc',
            'a.metatitle AS browser_page_title',
            'a.metakey',
            'a.checked_out',
            'a.checked_out_time',
            'a.catid',
            'a.access',
            'a.language'
        ]);
        $query->from($db->quoteName('#__phocacart_products', 'a'));

        $query->select('c.title AS category_title');
        $query->join('LEFT', $db->quoteName('#__phocacart_categories', 'c') . ' ON c.id = a.catid');

        $query->select([
            's.seo_score',
            's.focus_keyword',
            's.id as phocaseo_id',
            's.canonical_url',
            's.sitemap_exclude'
        ]);
        $query->join(
            'LEFT',
            $db->quoteName('#__phocaseo_metadata', 's') 
            . ' ON s.item_id = a.id AND s.context = ' . $db->quote($context)
        );

        $this->applyFilters($db, $query, 'a');

        return $query;
    }
}
