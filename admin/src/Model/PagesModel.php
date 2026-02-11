<?php
/**
 * @package     Phoca.Administrator
 * @subpackage  com_phocaseo
 *
 * @copyright   Copyright (C) Jan Pavelka www.phoca.cz
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Phoca\Component\PhocaSeo\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Pagination\Pagination;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper;


class PagesModel extends ListModel
{
    /**
     * Context string for the model
     *
     * @var  string
     */
    protected $context = 'com_phocaseo.pages';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see     \Joomla\CMS\MVC\Model\BaseDatabaseModel
     * @since   6.0.0
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'hits', 'a.hits',
                'title', 'a.title',
                'hits', 'a.hits',
                'created', 'a.created',
                'page_type',
                'limit',
                'start'
            );
        }

        parent::__construct($config);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A secondary identifier for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.page_type');
        $id .= ':' . $this->getState('filter.search');

        return parent::getStoreId($id);
    }

    /**
     * Method to get an array of data items.
     *
     * @return  mixed  An array of data items on success, false on failure.
     *
     * @since   6.0.0
     */
    public function getItems()
    {
        $limit      = (int) $this->getState('list.limit');
        $limitstart = (int) $this->getState('list.start');
        $pageType   = $this->getState('filter.page_type');
        $search     = $this->getState('filter.search');
        $ordering   = $this->getState('list.ordering', 'a.id');
        $direction  = $this->getState('list.direction', 'desc');

        if ($pageType === 'orphans') {
            return LinkScannerHelper::getOrphanPages($limit, $limitstart, $search, $ordering, $direction);
        }

        if ($pageType === 'top') {
             return LinkScannerHelper::getTopLinkedPages($limit, $limitstart, $search, $ordering, $direction);
        }

        return LinkScannerHelper::getAllPages($limit, $limitstart, $search, $ordering, $direction);
    }

    /**
     * Method to get the total number of items.
     *
     * @return  integer  The total number of items.
     *
     * @since   6.0.0
     */
    public function getTotal()
    {
        $pageType = $this->getState('filter.page_type');
        $search   = $this->getState('filter.search');

        if ($pageType === 'orphans') {
            return LinkScannerHelper::getOrphanPagesCount($search);
        }

        if ($pageType === 'top') {
            return LinkScannerHelper::getTopLinkedPagesCount($search);
        }

        return LinkScannerHelper::getAllPagesCount($search);
    }

    /**
     * Method to get a pagination object.
     *
     * @return  Pagination  A pagination object.
     *
     * @since   6.0.0
     */
    public function getPagination()
    {
        // Get total number of items
        $total = $this->getTotal();

        // New pagination object
        return new Pagination($total, (int) $this->getState('list.start'), (int) $this->getState('list.limit'));
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function populateState($ordering = 'a.id', $direction = 'desc')
    {
        $app = Factory::getApplication();

        // Standard Joomla limit and start
        $limit = $app->getUserStateFromRequest($this->context . '.list.limit', 'limit', $app->get('list_limit'), 'int');
        $this->setState('list.limit', $limit);

        $limitstart = $app->getUserStateFromRequest($this->context . '.list.start', 'limitstart', 0, 'int');
        $this->setState('list.start', $limitstart);

        // Filter state
        $pageType = $app->getUserStateFromRequest($this->context . '.filter.page_type', 'filter.page_type', 'all', 'string');
        $this->setState('filter.page_type', $pageType);

        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

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
    }
}
