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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper;

defined('_JEXEC') or die;

class LinksModel extends ListModel
{
    /**
     * Context string for the model
     *
     * @var  string
     */
    protected $context = 'com_phocaseo.links';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.1.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'l.id',
                'target_url', 'l.target_url',
                'status_code', 'l.status_code',
                'status_checked', 'l.status_checked',
                'link_type',
                'limit',
                'start'
            ];
        }

        parent::__construct($config);
    }

    /**
     * Get active link type from filter
     *
     * @return  string
     */
    public function getActiveType(): string
    {
        return (string)$this->getState('filter.link_type', 'all');
    }

    /**
     * Get items based on active filter
     *
     * @return  array
     */
    public function getItems()
    {
        $type = $this->getActiveType();
        $limit = $this->getState('list.limit');
        $limitstart = $this->getState('list.start');
        $ordering = $this->getState('list.ordering', 'l.id');
        $direction = $this->getState('list.direction', 'desc');

        switch ($type) {
            case 'broken':
                return LinkScannerHelper::getBrokenLinks((int)$limit, (int)$limitstart, $ordering, $direction);
            case 'all':
            default:
                return LinkScannerHelper::getLinks((int)$limit, (int)$limitstart, $ordering, $direction);
        }
    }

    /**
     * Get pagination
     *
     * @return  Pagination
     */
    public function getPagination()
    {
        $type = $this->getActiveType();
        $limit = $this->getState('list.limit');
        $limitstart = $this->getState('list.start');
        $total = 0;

        switch ($type) {
            case 'broken':
                $total = LinkScannerHelper::getBrokenLinksCount();
                break;
            case 'all':
            default:
                $total = LinkScannerHelper::getLinksCount();
                break;
        }

        return new Pagination($total, (int)$limitstart, (int)$limit);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc or desc).
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected function populateState($ordering = 'l.id', $direction = 'desc')
    {
        $app = Factory::getApplication();

        // List state information
        $limit = $app->getUserStateFromRequest($this->context . '.list.limit', 'limit', $app->get('list_limit'), 'int');
        $this->setState('list.limit', $limit);

        $limitstart = $app->getUserStateFromRequest($this->context . '.list.start', 'limitstart', 0, 'int');
        $this->setState('list.start', $limitstart);

        // Filter state information
        $linkType = $app->getUserStateFromRequest($this->context . '.filter.link_type', 'filter.link_type', 'all', 'string');
        $this->setState('filter.link_type', $linkType);

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

    /**
     * Get the filter form
     *
     * @param   array    $data      An associative array of data to map to the form.
     * @param   boolean  $loadData  True if the form is to retrieve its own data.
     *
     * @return  \Joomla\CMS\Form\Form|boolean
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return parent::getFilterForm($data, $loadData);
    }
}
