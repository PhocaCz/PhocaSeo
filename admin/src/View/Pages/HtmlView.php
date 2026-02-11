<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

namespace Phoca\Component\PhocaSeo\Administrator\View\Pages;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper;

/**
 * Pages View class.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * An array of data items.
     *
     * @var  array
     * @since  6.0.0
     */
    protected $items;

    /**
     * The pagination object.
     *
     * @var  \Joomla\CMS\Pagination\Pagination
     * @since  6.0.0
     */
    protected $pagination;

    /**
     * The model state.
     *
     * @var  \Joomla\CMS\Object\CMSObject
     * @since  6.0.0
     */
    protected $state;

    /**
     * The filter form.
     *
     * @var  \Joomla\CMS\Form\Form
     * @since  6.0.0
     */
    public $filterForm;

    /**
     * The active filters.
     *
     * @var  array
     * @since  6.0.0
     */
    public $activeFilters;

    /**
     * Active page type.
     *
     * @var  string
     * @since  6.0.0
     */
    protected $activeType;

    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  mixed  A string if successful, false otherwise.
     *
     * @since   6.0.0
     */
    public function display($tpl = null)
    {
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->activeType    = $this->state->get('filter.page_type');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        // Summary counts
        $this->orphanCount = LinkScannerHelper::getOrphanPagesCount();
        $this->topLinkedCount = LinkScannerHelper::getTopLinkedPagesCount();

        // Add form control fields for searchtools
        $this->filterForm
            ->addControlField('task', '')
            ->addControlField('boxchecked', '0');

        $this->addToolbar();

        $wa = $this->document->getWebAssetManager();
        $wa->useStyle('com_phocaseo.admin.dashboard');

        return parent::display($tpl);
    }

    protected function addToolbar() {

        $user = Factory::getUser();
        $toolbar = Toolbar::getInstance('toolbar');

        ToolbarHelper::title(Text::_('COM_PHOCASEO_PAGES_MANAGER'), 'file-2');

        $toolbar->linkButton('dashboard', 'COM_PHOCASEO_DASHBOARD')
            ->url('index.php?option=com_phocaseo')
            ->icon('icon-home-2')
            ->buttonClass('btn btn-primary');

        if ($user->authorise('core.admin', 'com_phocaseo')) {
            ToolbarHelper::preferences('com_phocaseo');
        }
    }
}
