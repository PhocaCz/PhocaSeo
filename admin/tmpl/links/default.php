<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;
use Phoca\Plugin\System\PhocaSeo\Extension\PhocaSeo;

defined('_JEXEC') or die;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

?>
<form action="<?php echo Route::_('index.php?option=com_phocaseo&view=links'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">

        <!-- Summary Cards -->
        <div class="ph-dashboard-stats mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ph-stat-card border-start ph-seo-link-border-total border-5 shadow-sm h-100 p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon ph-seo-link-bg-total p-3 rounded-circle me-3 ph-seo-rounded-box">
                                <?php  echo PhocaSeoHelper::renderSvg('links') ?>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-bold"><?php echo Text::_('COM_PHOCASEO_TOTAL_LINKS'); ?></small>
                                <h3 class="mb-0 fw-bold"><?php echo (int)($this->summary['internal']->total + $this->summary['external']->total); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ph-stat-card border-start ph-seo-link-border-broken border-5 shadow-sm h-100 p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon ph-seo-link-bg-broken p-3 rounded-circle me-3 ph-seo-rounded-box">
                                <?php  echo PhocaSeoHelper::renderSvg('broken') ?>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-bold"><?php echo Text::_('COM_PHOCASEO_BROKEN_LINKS'); ?></small>
                                <h3 class="mb-0 fw-bold"><?php echo (int)($this->summary['internal']->status_404 + $this->summary['external']->status_404); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ph-stat-card border-start ph-seo-link-border-internal border-5 shadow-sm h-100 p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon ph-seo-link-bg-internal p-3 rounded-circle me-3 ph-seo-rounded-box">
                                <?php  echo PhocaSeoHelper::renderSvg('pages') ?>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-bold"><?php echo Text::_('COM_PHOCASEO_PAGES_ANALYZED'); ?></small>
                                <h3 class="mb-0 fw-bold"><?php echo \Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper::getTotalScanItemsCount(); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Tools -->
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <!-- Batch Progress UI (Hidden by default) -->
        <div id="ph-links-progress-container" class="card shadow-sm mb-4 d-none">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0" id="ph-batch-title"><?php echo Text::_('COM_PHOCASEO_PROCESSING_PROGRESS'); ?></h5>
                </div>
                <div class="progress mb-3" style="height: 20px;">
                    <div id="ph-links-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                </div>
                <div id="ph-links-progress-text" class="text-muted small">
                    <?php echo Text::_('COM_PHOCASEO_PREPARING_BATCH'); ?>
                </div>
            </div>
        </div>

        <!-- Unified Results Table -->
        <div class="card shadow-sm border-0 rounded overflow-hidden mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="linkList">
                    <thead class="small">
                        <tr>
                            <th width="1%"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'l.id', $listDirn, $listOrder); ?></th>
                            <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_PHOCASEO_TARGET_URL', 'l.target_url', $listDirn, $listOrder); ?></th>
                            <th><?php echo Text::_('COM_PHOCASEO_SOURCE_PAGE'); ?></th>
                            <th><?php echo Text::_('COM_PHOCASEO_ANCHOR_TEXT'); ?></th>
                            <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_PHOCASEO_LAST_CHECKED', 'l.status_checked', $listDirn, $listOrder); ?></th>
                            <?php /* if ($this->activeType === 'broken') : */ ?>
                                <th width="10%"><?php echo HTMLHelper::_('searchtools.sort', 'COM_PHOCASEO_STATUS', 'l.status_code', $listDirn, $listOrder); ?></th>
                            <?php /* endif; */ ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($this->items)) : ?>
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <i class="icon-info-2 fs-1 d-block mb-3 opacity-25"></i>
                                    <?php echo Text::_('COM_PHOCASEO_NO_LINKS_FOUND'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($this->items as $item) : ?>
                                <tr>
                                    <td class="small"><?php echo (int)$item->id; ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-break"><?php echo HTMLHelper::_('string.truncate', $item->target_url, 80, true, true); ?></span>
                                            <a href="<?php echo $item->target_url; ?>" target="_blank" class="small text-primary text-decoration-none">
                                                <?php echo Text::_('COM_PHOCASEO_VISIT_URL'); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center ph-seo-svg-box-inline">
                                            <?php echo PhocaSeoHelper::renderSvg('pages'); ?>
                                            <span class="text-truncate" style="max-width: 250px;"><?php echo $this->escape($item->source_title ?: Text::_('COM_PHOCASEO_UNKNOWN_SOURCE')); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="fw-bold"><?php echo $this->escape($item->link_text ?: Text::_('COM_PHOCASEO_NOT_AVAILABLE_SHORT_LABEL')); ?></span></td>
                                    <?php if ($item->status_checked) {
                                        $statusChecked = HTMLHelper::_('date', $item->status_checked, Text::_('DATE_FORMAT_LC3'));
                                    } else {
                                        $statusChecked = Text::_('COM_PHOCASEO_NOT_AVAILABLE_SHORT_LABEL');
                                    }

                                    ?>
                                    <td class="small"><?php echo $statusChecked; ?></td>
                                    <?php /*if ($this->activeType === 'broken') :*/ ?>
                                        <td>
                                            <span class="badge <?php echo LinkScannerHelper::getStatusCssClass($item->status_code); ?>"><?php echo (empty($item->status_code) ? Text::_('COM_PHOCASEO_NOT_AVAILABLE_SHORT_LABEL') : $item->status_code); ?></span>
                                        </td>
                                    <?php /* endif;*/ ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Single Pagination Footer -->
            <?php if ($this->pagination && $this->pagination->pagesTotal > 1) : ?>
                <div class="card-footer border-top py-2 px-5">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="pagination-links">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </div>
                        <div class="pagination-counter text-muted small">
                            <?php echo $this->pagination->getPagesCounter(); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <input type="hidden" name="limitstart" value="<?php echo (int)$this->pagination->limitstart; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
