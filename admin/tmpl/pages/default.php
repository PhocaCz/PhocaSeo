<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper;

/** @var \Phoca\Component\PhocaSeo\Administrator\View\Pages\HtmlView $this */

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

?>

<form action="<?php echo Route::_('index.php?option=com_phocaseo&view=pages'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">

            <!-- Summary Area -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="ph-stat-card border-start ph-seo-link-border-total border-5 shadow-sm h-100 p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon ph-seo-link-bg-total p-3 rounded-circle me-3 ph-seo-rounded-box">
                                <?php  echo PhocaSeoHelper::renderSvg('links') ?>
                            </div>
                            <div>
                                <h6 class="card-subtitle mb-1 text-muted small text-uppercase fw-bold"><?php echo Text::_('COM_PHOCASEO_TOP_LINKED_PAGES'); ?></h6>
                                <h3 class="card-title mb-0 fw-bold"><?php echo (int) $this->topLinkedCount; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ph-stat-card border-start ph-seo-link-border-external border-5 shadow-sm h-100 p-3">
                        <div class="d-flex align-items-centerr">
                            <div class="stat-icon ph-seo-link-bg-external p-3 rounded-circle me-3 ph-seo-rounded-box">
                                <?php  echo PhocaSeoHelper::renderSvg('pages') ?>
                            </div>
                            <div>
                                <h6 class="card-subtitle mb-1 text-muted small text-uppercase fw-bold"><?php echo Text::_('COM_PHOCASEO_ORPHAN_PAGES'); ?></h6>
                                <h3 class="card-title mb-0 fw-bold"><?php echo (int) $this->orphanCount; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="j-main-container" class="j-main-container">
                <div class="alert alert-warning">
                    <span class="icon-warning" aria-hidden="true"></span>
                    <span class="visually-hidden"><?php echo Text::_('WARNING'); ?></span>
                    <?php echo Text::sprintf('COM_PHOCASEO_WARNING_SCAN_REQUIRED', Route::_('index.php?option=com_phocaseo&view=links')); ?>
                </div>
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <table class="table table-hover align-middle mb-0" id="pagesList">
                    <thead class="small">


                        <tr>
                            <th width="1%" class=" d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?></th>
                            <th><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?></th>
                            <th width="15%"><?php echo HTMLHelper::_('searchtools.sort', 'COM_PHOCASEO_CREATED', 'a.created', $listDirn, $listOrder); ?></th>
                            <th width="10%"><?php echo HTMLHelper::_('searchtools.sort', 'COM_PHOCASEO_HITS', 'a.hits', $listDirn, $listOrder); ?></th>
                            <?php /*if ($this->activeType === 'top') :*/ ?>
                                <th width="10%"><?php echo Text::_('COM_PHOCASEO_LINK_COUNT'); ?></th>
                            <?php /*endif;*/ ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($this->items)) : ?>
                            <tr>
                                <td colspan="5" class="text-center p-4">
                                    <div class="text-muted">
                                        <i class="icon-info-circle fs-2 d-block mb-2"></i>
                                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($this->items as $item) : ?>
                                <tr>
                                    <td class=" d-none d-md-table-cell"><?php echo (int)($item->id ?? 0); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $this->escape($item->title ?? Text::_('COM_PHOCASEO_UNKNOWN_PAGE')); ?></div>
                                        <small class=""><?php echo $this->escape($item->alias ?? ''); ?></small>
                                    </td>
                                    <td class="small">
                                        <?php echo !empty($item->created) ? HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC3')) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?php echo (int)($item->hits ?? 0); ?></span>
                                    </td>
                                    <?php /*if ($this->activeType === 'top') :*/?>
                                        <td>
                                            <span class="badge <?php echo LinkScannerHelper::getLinkCssClass($item->link_count ?? 0); ?> rounded-pill"><?php echo (int)($item->link_count ?? 0); ?></span>
                                        </td>
                                    <?php /*endif;*/ ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination Footer -->
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
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <input type="hidden" name="limitstart" value="<?php echo (int)$this->pagination->limitstart; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
