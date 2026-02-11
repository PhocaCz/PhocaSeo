<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$isMenuContext = $this->currentContext === 'com_menus.item';
$isCategoryContext = $this->currentContext === 'com_content.category';
?>

<form action="<?php echo Route::_('index.php?option=com_phocaseo&view=items'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">



                <?php

                echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>


                <div id="ph-seo-save-status" class="alert alert-info d-none mb-3">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    <span class="status-text"><?php echo Text::_('COM_PHOCASEO_SAVING'); ?></span>
                </div>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table table-hover align-middle mb-0 ph-seo-items-table" id="itemList">
                    <thead class="small">
                            <tr>
                                <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                                <th width="1%" class="text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </th>
                                <th scope="col" style="width:1%" class="text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" style="width:20%">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo Text::_('COM_PHOCASEO_META_INFORMATION'); ?>
                                </th>
                                <th scope="col" style="width:5%" class="text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_PHOCASEO_SEO_SCORE', 'seo_score', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" style="width:3%" class="text-center">
                                    <?php echo Text::_('COM_PHOCASEO_ACTIONS'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $i => $item) : ?>
                                <?php
                                $canEdit = Factory::getUser()->authorise('core.edit', 'com_phocaseo');
                                $itemContext = $this->currentContext;
                                $editUrl = '';

                                switch ($itemContext) {
                                    case 'com_content.article':
                                        $editUrl = Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $item->id);
                                        break;
                                    case 'com_content.category':
                                        $editUrl = Route::_('index.php?option=com_categories&task=category.edit&id=' . (int) $item->id . '&extension=com_content');
                                        break;
                                    case 'com_menus.item':
                                        $editUrl = Route::_('index.php?option=com_menus&task=item.edit&id=' . (int) $item->id);
                                        break;
                                    case 'com_phocacart.product':
                                        $editUrl = Route::_('index.php?option=com_phocacart&task=phocacartitem.edit&id=' . (int) $item->id);
                                        break;
                                }

                                $score = (int) ($item->seo_score ?? 0);
                                $scoreClass = 'secondary';
                                if (!empty($item->phocaseo_id)) {
                                    if ($score >= 70) {
                                        $scoreClass = 'success';
                                    } elseif ($score >= 40) {
                                        $scoreClass = 'warning';
                                    } elseif ($score > 0) {
                                        $scoreClass = 'danger';
                                    }
                                }
                                ?>
                                <tr class="row<?php echo $i % 2; ?>" data-item-id="<?php echo $item->id; ?>" data-context="<?php echo $itemContext; ?>">
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php echo $item->id; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $stateVal = (int) ($item->state ?? 0);
                                        if ($stateVal === 1) {
                                            echo '<span class="badge bg-success text-center"><span class="ph-seo-svg-box-inline">'.PhocaSeoHelper::renderSvg('publish') . '</span></span>';
                                        } elseif ($stateVal === 0) {
                                            echo '<span class="badge bg-secondary text-center"><span class="ph-seo-svg-box-inline">'.PhocaSeoHelper::renderSvg('unpublish') . '</span></span>';
                                        } else {
                                            echo '<span class="badge bg-danger text-center"><span class="ph-seo-svg-box-inline">'.PhocaSeoHelper::renderSvg('links') . '</span></span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="ph-seo-inline-field" data-field="title">
                                            <?php if ($canEdit && $editUrl) : ?>
                                                <a href="<?php echo $editUrl; ?>" class="ph-seo-title-link">
                                                    <?php echo $this->escape($item->title); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo $this->escape($item->title); ?>
                                            <?php endif; ?>
                                            <div class="small text-muted mt-1">
                                                <?php echo '<span class="ph-seo-svg-box-inline">'.PhocaSeoHelper::renderSvg('links') . '</span>';?><?php echo $this->escape($item->alias ?? ''); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        // Define fields layout based on context
                                        $fieldsLayout = [];

                                        // Common field definitions to reuse
                                        $f_metadesc = [
                                            'field' => 'metadesc',
                                            'type' => 'textarea',
                                            'label' => 'COM_PHOCASEO_META_DESCRIPTION',
                                            'rows' => 3,
                                            'count' => 160
                                        ];
                                        $f_metakey = [
                                            'field' => 'metakey',
                                            'type' => 'textarea',
                                            'label' => 'COM_PHOCASEO_META_KEYWORDS',
                                            'rows' => 3,
                                            'count' => 0
                                        ];
                                        $f_browser_title = [
                                            'field' => 'browser_page_title',
                                            'type' => 'text',
                                            'label' => 'COM_PHOCASEO_BROWSER_PAGE_TITLE'
                                        ];
                                        $f_page_heading = [
                                            'field' => 'page_heading',
                                            'type' => 'text',
                                            'label' => 'COM_PHOCASEO_PAGE_HEADING'
                                        ];
                                        $f_focus_keyword = [
                                            'field' => 'focus_keyword',
                                            'type' => 'text',
                                            'label' => 'COM_PHOCASEO_FOCUS_KEYWORD'
                                        ];

                                        switch ($itemContext) {
                                            case 'com_content.article':
                                                $fieldsLayout = [
                                                    [$f_metadesc, $f_metakey],
                                                    [$f_focus_keyword, $f_browser_title]
                                                ];
                                                break;
                                            case 'com_content.category':
                                                $fieldsLayout = [
                                                    [$f_metadesc, $f_metakey]
                                                ];
                                                break;
                                            case 'com_menus.item':
                                                $fieldsLayout = [
                                                    [$f_browser_title, $f_page_heading],
                                                    [$f_metadesc, $f_metakey]
                                                ];
                                                break;
                                            case 'com_phocacart.product':
                                                $f_meta_title = $f_browser_title;
                                                $f_meta_title['label'] = 'COM_PHOCASEO_META_TITLE';

                                                $fieldsLayout = [
                                                    [$f_meta_title, $f_metakey],
                                                    [$f_metadesc] // Full width if only one in row, or create empty slot
                                                ];
                                                break;
                                        }
                                        ?>

                                        <div class="ph-seo-condensed-form">
                                            <?php foreach ($fieldsLayout as $rowFields) : ?>
                                                <div class="d-flex gap-2 mb-2">
                                                    <?php foreach ($rowFields as $f) : ?>
                                                        <div class="ph-seo-form-row flex-grow-1" style="width: 50%;">
                                                            <label class="ph-seo-form-label"><?php echo Text::_($f['label']); ?></label>
                                                            <div class="ph-seo-inline-field" data-field="<?php echo $f['field']; ?>">
                                                                <?php if ($f['type'] === 'textarea') : ?>
                                                                    <textarea class="form-control form-control-sm ph-seo-editable"
                                                                              rows="<?php echo $f['rows']; ?>"
                                                                              data-original="<?php echo $this->escape($item->{$f['field']} ?? ''); ?>"
                                                                              placeholder="<?php echo Text::_('COM_PHOCASEO_ENTER_VALUE'); ?>"
                                                                              <?php echo $canEdit ? '' : 'disabled'; ?>
                                                                    ><?php echo $this->escape($item->{$f['field']} ?? ''); ?></textarea>
                                                                    <?php if (!empty($f['count'])) : ?>
                                                                        <div class="ph-seo-char-count small text-muted mt-1 text-end">
                                                                            <span class="count"><?php echo strlen($item->{$f['field']} ?? ''); ?></span>/<?php echo $f['count']; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php else : ?>
                                                                    <input type="text"
                                                                           class="form-control form-control-sm ph-seo-editable"
                                                                           value="<?php echo $this->escape($item->{$f['field']} ?? ''); ?>"
                                                                           data-original="<?php echo $this->escape($item->{$f['field']} ?? ''); ?>"
                                                                           placeholder="<?php echo Text::_('COM_PHOCASEO_ENTER_VALUE'); ?>"
                                                                           <?php echo $canEdit ? '' : 'disabled'; ?>
                                                                    >
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>

                                                    <?php // Spacer for single item rows to keep 50% width
                                                    if (count($rowFields) === 1) : ?>
                                                        <div class="ph-seo-form-row flex-grow-1" style="width: 50%;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $scoreClass; ?> ph-seo-score-badge">
                                            <?php echo empty($item->phocaseo_id) ? '-' : $score; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($canEdit && $editUrl) : ?>
                                                <a href="<?php echo $editUrl; ?>" class="btn btn-outline-primary btn-sm" title="<?php echo Text::_('JACTION_EDIT'); ?>">
                                                    <?php echo '<span class="ph-seo-svg-box-inline">'.PhocaSeoHelper::renderSvg('edit') . '</span>';?>
                                                </a>
                                            <?php endif; ?>
                                            <?php /*<button type="button" class="btn btn-outline-info btn-sm ph-seo-analyze-btn"
                                                    data-item-id="<?php echo $item->id; ?>"
                                                    title="<?php echo Text::_('COM_PHOCASEO_ANALYZE'); ?>">
                                                <span class="icon-search"></span>
                                            </button> */ ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

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

                <input type="hidden" name="task" value="" />
                <input type="hidden" name="boxchecked" value="0" />
                <input type="hidden" name="limitstart" value="<?php echo (int)$this->pagination->limitstart; ?>" />
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
