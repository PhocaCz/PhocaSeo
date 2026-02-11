<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\View\Items;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Phoca\Component\PhocaSeo\Administrator\Model\ItemsModel;

defined('_JEXEC') or die;

/**
 * Items List View
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The items to display
     *
     * @var    array
     * @since  6.0.0
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var    \Joomla\CMS\Pagination\Pagination
     * @since  6.0.0
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var    \Joomla\CMS\Object\CMSObject
     * @since  6.0.0
     */
    protected $state;

    /**
     * The filter form
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  6.0.0
     */
    public $filterForm;

    /**
     * The active filters
     *
     * @var    array
     * @since  6.0.0
     */
    public $activeFilters;

    /**
     * Context options for dropdown
     *
     * @var    array
     * @since  6.1.0
     */
    public $contextOptions;

    /**
     * Current context
     *
     * @var    string
     * @since  6.1.0
     */
    public $currentContext;

    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  mixed  A string if successful, otherwise an Error object.
     *
     * @since   6.0.0
     * @throws  \Exception
     */
    public function display($tpl = null)
    {
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->contextOptions = ItemsModel::getContextOptions();
        $this->currentContext = $this->state->get('filter.context', 'com_content.article');

        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        // Add form control fields for searchtools
        $this->filterForm
            ->addControlField('task', '')
            ->addControlField('boxchecked', '0');

        $this->addToolbar();
        $this->loadAssets();

        return parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function addToolbar()
    {
        $user = Factory::getUser();
        $toolbar = Toolbar::getInstance('toolbar');

        ToolbarHelper::title(Text::_('COM_PHOCASEO_ITEMS_TITLE'), 'list');

        /*if ($user->authorise('core.edit', 'com_phocaseo')) {
            $toolbar->standardButton('scan')
                ->text('COM_PHOCASEO_SCAN_LINKS')
                ->icon('icon-search')
                ->task('items.scanLinks')
                ->listCheck(true);
        }*/

        $toolbar->linkButton('dashboard', 'COM_PHOCASEO_DASHBOARD')
            ->url('index.php?option=com_phocaseo')
            ->icon('icon-home-2')
            ->buttonClass('btn btn-primary');

        /*
        $toolbar->linkButton('links')
            ->text('COM_PHOCASEO_LINK_MANAGER')
            ->icon('icon-link')
            ->url('index.php?option=com_phocaseo&view=links');*/

        if ($user->authorise('core.admin', 'com_phocaseo') || $user->authorise('core.options', 'com_phocaseo')) {
            ToolbarHelper::preferences('com_phocaseo');
        }
    }

    protected function loadAssets()
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        if (!$wa->getRegistry()->exists('style', 'com_phocaseo.admin.items')) {
            $wa->getRegistry()->addExtensionRegistryFile('com_phocaseo');
        }

        $wa->useStyle('com_phocaseo.admin.dashboard');
        //$wa->useStyle('com_phocaseo.admin.items');
        $wa->useScript('com_phocaseo.admin.items-inline');

        $contextLabels = [];
        foreach ($this->contextOptions as $key => $label) {
            $contextLabels[$key] = Text::_($label);
        }

        $script = sprintf(
            "window.PhocaSeoItems = { token: '%s', context: '%s', contextLabels: %s };",
            Session::getFormToken(),
            $this->currentContext,
            json_encode($contextLabels)
        );

        Factory::getApplication()->getDocument()->addScriptDeclaration($script);
    }
}
