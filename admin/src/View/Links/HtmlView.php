<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\View\Links;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Phoca\Component\PhocaSeo\Administrator\Helper\IntegrationHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper;

defined('_JEXEC') or die;


class HtmlView extends BaseHtmlView
{

    protected $summary;
    protected $items;
    protected $hasRedirect;
    protected $pagination;
    public $filterForm;
    public $state;
    protected $activeType;

    public function display($tpl = null) {
        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->activeType    = $model->getActiveType();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        $this->summary      = LinkScannerHelper::getAnalysisSummary();
        $this->hasRedirect  = IntegrationHelper::isRedirectComponentAvailable();

        if ($errors = $this->get('Errors')) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        // Add form control fields for searchtools
        $this->filterForm
            ->addControlField('task', '')
            ->addControlField('boxchecked', '0');

        HTMLHelper::_('behavior.core');
        HTMLHelper::_('behavior.keepalive');

        $this->addToolbar();

        $wa = $this->document->getWebAssetManager();
        $wa->useStyle('com_phocaseo.admin.dashboard');
        $wa->useScript('com_phocaseo.admin.links');

        $uncheckedCount = LinkScannerHelper::getUncheckedLinksCount();
        $scanItemCount  = LinkScannerHelper::getTotalScanItemsCount();

        $this->document->addScriptOptions('com_phocaseo_links', [
            'uncheckedCount' => $uncheckedCount,
            'scanItemCount'  => $scanItemCount
        ]);
        $this->document->addScriptOptions('csrf.token', Session::getFormToken());

        Text::script('COM_PHOCASEO_JS_ALL_LINKS_CHECKED');
        Text::script('COM_PHOCASEO_JS_START_BATCH_CHECK');
        Text::script('COM_PHOCASEO_JS_CHECK_PROGRESS');
        Text::script('COM_PHOCASEO_JS_CHECK_FINISHED');
        Text::script('COM_PHOCASEO_JS_RELOAD_TO_SEE_RESULTS');
        Text::script('COM_PHOCASEO_JS_NO_ITEMS_TO_SCAN');
        Text::script('COM_PHOCASEO_JS_START_BATCH_SCAN');
        Text::script('COM_PHOCASEO_JS_SCAN_PROGRESS');
        Text::script('COM_PHOCASEO_JS_SCAN_FINISHED');

        return parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected function addToolbar(): void
    {
        $user = Factory::getUser();
        $toolbar = Toolbar::getInstance('toolbar');

        ToolbarHelper::title(Text::_('COM_PHOCASEO_LINK_MANAGER'), 'link');

        if ($user->authorise('core.edit', 'com_phocaseo')) {
            $toolbar->standardButton('scan')
                ->text('COM_PHOCASEO_SCAN_ALL_CONTENT')
                ->icon('icon-search')
                ->task('links.scanAll');

            $toolbar->standardButton('check')
                ->text('COM_PHOCASEO_CHECK_STATUSES')
                ->icon('icon-refresh')
                ->task('links.checkStatuses');
        }

        $toolbar->linkButton('dashboard', 'COM_PHOCASEO_DASHBOARD')
            ->url('index.php?option=com_phocaseo')
            ->icon('icon-home-2')
            ->buttonClass('btn btn-primary');

        if ($user->authorise('core.admin', 'com_phocaseo')) {
            ToolbarHelper::preferences('com_phocaseo');
        }
    }
}
