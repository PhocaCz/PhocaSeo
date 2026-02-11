<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;

?>
<div id="phoca-seo-sidebar" class="ph-seo-sidebar-card status-neutral shadow-sm mb-3">
    <div class="ph-seo-sidebar-header d-flex align-items-center justify-content-between p-2">
        <strong><span class="icon-eye"></span> <?php echo Text::_('COM_PHOCASEO_SEO_ASSISTANT'); ?></strong>
        <div class="d-flex align-items-center gap-2">
            <button type="button" id="ph-seo-expand-btn" class="btn btn-sm btn-link p-0" title="<?php echo Text::_('COM_PHOCASEO_EXPAND'); ?>">
                <span class="icon-expand"></span>
            </button>
            <div id="ph-seo-traffic-light" class="ph-seo-traffic-light bg-secondary ph-seo-traffic-light-icon"></div>
        </div>
    </div>

    <div class="ph-seo-sidebar-tabs d-flex p-1 small">
        <div class="ph-seo-sidebar-tab active flex-fill text-center py-1 curs-pointer" data-tab="analysis"><?php echo Text::_('COM_PHOCASEO_SEO_SCORE'); ?></div>
        <div class="ph-seo-sidebar-tab flex-fill text-center py-1 curs-pointer" data-tab="keywords"><?php echo Text::_('COM_PHOCASEO_KEYWORDS'); ?></div>
        <div class="ph-seo-sidebar-tab flex-fill text-center py-1 curs-pointer" data-tab="social"><?php echo Text::_('COM_PHOCASEO_PREVIEW'); ?></div>
        <div class="ph-seo-sidebar-tab flex-fill text-center py-1 curs-pointer" data-tab="links"><?php echo Text::_('COM_PHOCASEO_LINKS'); ?></div>
        <div class="ph-seo-sidebar-tab flex-fill text-center py-1 curs-pointer" data-tab="settings"><?php echo Text::_('COM_PHOCASEO_SETTINGS'); ?></div>
    </div>

    <div class="ph-seo-sidebar-body p-2">
        <?php /*<div id="ph-seo-focus-keyword-container" class="mb-3 pb-2">
            <label class="form-label small fw-bold mb-1 ph-seo-font-11" for="ph-seo-focus-keyword">
                <span class="icon-key"></span> <?php echo Text::_('COM_PHOCASEO_FOCUS_KEYWORD'); ?>
            </label>
            <input type="text" id="ph-seo-focus-keyword" class="form-control form-control-sm"
                   placeholder="<?php echo Text::_('COM_PHOCASEO_ENTER_KEYWORD'); ?>">
            <div id="ph-seo-keyword-sync" class="small ph-seo-text-muted mt-1 d-none ph-seo-font-10">
                <span class="icon-sync"></span> <?php echo Text::_('COM_PHOCASEO_SYNCED'); ?>
            </div>
        </div> */ ?>
        <div id="ph-seo-focus-keyword-container" class="mb-3 pb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold mb-0 ph-seo-font-11" for="ph-seo-focus-keyword">
                    <span class="icon-key"></span> <?php echo Text::_('COM_PHOCASEO_FOCUS_KEYWORD'); ?>
                </label>
                <button type="button" id="ph-seo-keyword-reload" class="btn btn-link p-0 text-decoration-none" title="<?php echo Text::_('COM_PHOCASEO_RELOAD_ANALYTICS'); ?>">
                    <span class="icon-loop ph-seo-font-12"></span>
                </button>
            </div>
            <input type="text" id="ph-seo-focus-keyword" class="form-control form-control-sm"
                   placeholder="<?php echo Text::_('COM_PHOCASEO_ENTER_KEYWORD'); ?>">
            <div id="ph-seo-keyword-sync" class="small ph-seo-text-muted mt-1 d-none ph-seo-font-10">
                <span class="icon-sync"></span> <?php echo Text::_('COM_PHOCASEO_SYNCED'); ?>
            </div>
        </div>

        <div id="ph-seo-tab-analysis" class="ph-seo-tab-content">
            <div id="ph-seo-score-container" class="mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small fw-bold"><?php echo Text::_('COM_PHOCASEO_OVERALL_SCORE'); ?></span>
                    <span id="ph-seo-score-badge" class="badge bg-secondary">--/100</span>
                </div>
                <div class="progress mt-1 ph-seo-progress-sm">
                    <div id="ph-seo-score-progress" class="progress-bar bg-secondary" style="width:0%"></div>
                </div>
            </div>

            <div id="ph-alias-warning" class="alert alert-warning p-1 mb-2 d-none ph-seo-font-10">
                <span class="icon-warning"></span> <?php echo Text::_('COM_PHOCASEO_ALIAS_CHANGED'); ?>
            </div>

            <div class="d-grid gap-1 mb-2">
                <button type="button" id="btn-analyze" class="btn btn-sm btn-primary py-1 ph-seo-font-11">
                    <span class="icon-loop"></span> <?php echo Text::_('COM_PHOCASEO_DEEP_ANALYSIS'); ?>
                </button>
            </div>

            <div id="ph-seo-sidebar-rules" class="mb-2 ph-seo-scroll-rules"></div>

            <div class="border-top pt-2 mt-2">
                <div class="small fw-bold mb-1 ph-seo-font-10 ph-seo-color-muted"><?php echo Text::_('COM_PHOCASEO_AI_GENERATORS'); ?></div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <button type="button" id="btn-sidebar-gen-title" class="btn btn-xs btn-primary flex-fill py-1 ph-seo-font-10" title="<?php echo Text::_('COM_PHOCASEO_TITLE'); ?>"><?php echo Text::_('COM_PHOCASEO_TITLE'); ?></button>
                    <button type="button" id="btn-sidebar-gen-desc" class="btn btn-xs btn-primary flex-fill py-1 ph-seo-font-10" title="<?php echo Text::_('COM_PHOCASEO_DESCRIPTION'); ?>"><?php echo Text::_('COM_PHOCASEO_DESCRIPTION'); ?></button>
                    <button type="button" id="btn-sidebar-gen-keys" class="btn btn-xs btn-primary flex-fill py-1 ph-seo-font-10" title="<?php echo Text::_('COM_PHOCASEO_KEYWORDS'); ?>"><?php echo Text::_('COM_PHOCASEO_KEYWORDS'); ?></button>
                </div>

                <div id="ph-seo-sidebar-feedback" class="alert d-none"></div>

                <button type="button" id="btn-fix-imgs-sidebar" class="btn btn-sm btn-primary w-100 py-1 ph-seo-font-11" title="<?php echo Text::_('COM_PHOCASEO_AI_FIX_IMAGE_ALTS'); ?>">
                    <span class="icon-image"></span> <?php echo Text::_('COM_PHOCASEO_AI_FIX_IMAGE_ALTS'); ?>
                </button>
            </div>

            <div id="phocaseo-analysis-results-placeholder" class="mt-2 text-wrap ph-seo-text-muted small"></div>
        </div>

        <div id="ph-seo-tab-keywords" class="ph-seo-tab-content d-none">
            <div class="small fw-bold mb-2"><?php echo Text::_('COM_PHOCASEO_KEYWORD_ANALYSIS'); ?></div>
            <div id="ph-seo-keyword-metrics">
                <div class="ph-seo-keyword-metric mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?php echo Text::_('COM_PHOCASEO_TITLE'); ?></span>
                        <span id="ph-kw-title-pct" class="badge bg-secondary">--</span>
                    </div>
                    <div class="progress ph-seo-progress-xs">
                        <div id="ph-kw-title-bar" class="progress-bar bg-secondary" style="width:0%"></div>
                    </div>
                </div>
                <div class="ph-seo-keyword-metric mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?php echo Text::_('COM_PHOCASEO_ALIAS'); ?></span>
                        <span id="ph-kw-alias-pct" class="badge bg-secondary">--</span>
                    </div>
                    <div class="progress ph-seo-progress-xs">
                        <div id="ph-kw-alias-bar" class="progress-bar bg-secondary" style="width:0%"></div>
                    </div>
                </div>
                <div class="ph-seo-keyword-metric mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?php echo Text::_('COM_PHOCASEO_DESCRIPTION'); ?></span>
                        <span id="ph-kw-desc-pct" class="badge bg-secondary">--</span>
                    </div>
                    <div class="progress ph-seo-progress-xs">
                        <div id="ph-kw-desc-bar" class="progress-bar bg-secondary" style="width:0%"></div>
                    </div>
                </div>
                <div class="ph-seo-keyword-metric mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?php echo Text::_('COM_PHOCASEO_CONTENT'); ?></span>
                        <span id="ph-kw-content-pct" class="badge bg-secondary">--</span>
                    </div>
                    <div class="progress ph-seo-progress-xs">
                        <div id="ph-kw-content-bar" class="progress-bar bg-secondary" style="width:0%"></div>
                    </div>
                </div>
                <div class="ph-seo-keyword-metric mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?php echo Text::_('COM_PHOCASEO_KEYWORDS'); ?></span>
                        <span id="ph-kw-keywords-pct" class="badge bg-secondary">--</span>
                    </div>
                    <div class="progress ph-seo-progress-xs">
                        <div id="ph-kw-keywords-bar" class="progress-bar bg-secondary" style="width:0%"></div>
                    </div>
                </div>
                <div class="ph-seo-keyword-metric mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?php echo Text::_('COM_PHOCASEO_DENSITY'); ?></span>
                        <span id="ph-kw-density-pct" class="badge bg-secondary">--</span>
                    </div>
                    <div class="progress ph-seo-progress-xs">
                        <div id="ph-kw-density-bar" class="progress-bar bg-secondary" style="width:0%"></div>
                    </div>
                </div>
            </div>
            <div id="ph-seo-keyword-suggestions" class="mt-3 pt-2 border-top">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-bold"><?php echo Text::_('COM_PHOCASEO_LINK_SUGGESTIONS'); ?></span>
                    <button type="button" id="btn-refresh-suggestions" class="btn btn-xs btn-link p-0" title="<?php echo Text::_('COM_PHOCASEO_REFRESH'); ?>">
                        <span class="icon-refresh"></span>
                    </button>
                </div>
                <div id="ph-seo-link-suggestions-list" class="small ph-seo-text-muted ph-seo-scroll-links">
                    <em><?php echo Text::_('COM_PHOCASEO_ENTER_KEYWORD_SUGGESTIONS'); ?></em>
                </div>
            </div>
        </div>

        <div id="ph-seo-tab-social" class="ph-seo-tab-content d-none">
            <div class="small fw-bold mb-2">Google Preview <span class="ph-seo-save-notice" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_PHOCASEO_UPDATED_AFTER_SAVE'); ?>"><span class="icon-warning text-warning" aria-hidden="true"></span></span></div>
            <div id="ph-soc-prev-google" class="p-2 border rounded mb-3">
                <div id="prev-google-url" class="small ph-seo-font-9 ph-seo-google-url">domain.com > alias</div>
                <div id="prev-google-title" class="fw-bold ph-seo-font-14 ph-seo-google-title"><?php echo Text::_('COM_PHOCASEO_TITLE'); ?></div>
                <div class="d-flex align-items-start">
                    <div id="prev-google-img-container" class="me-2 d-none ph-seo-google-img-container">
                        <img id="prev-google-img" src="" class="ph-seo-google-img" alt="Google Preview">
                    </div>
                    <div id="prev-google-desc" class="small ph-seo-text-muted ph-seo-line-12 ph-seo-font-10">Meta description preview...</div>
                </div>
            </div>

            <div class="small fw-bold mb-2">Facebook Preview <span class="ph-seo-save-notice" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_PHOCASEO_UPDATED_AFTER_SAVE'); ?>"><span class="icon-warning text-warning" aria-hidden="true"></span></span></div>
            <div id="ph-soc-prev-facebook" class="border rounded mb-3 ph-seo-fb-card">
                <div id="prev-fb-img-container" class="ph-seo-fb-img-container">
                    <img id="prev-fb-img" src="" class="ph-seo-fb-img" alt="Facebook Preview">
                    <div id="prev-fb-img-placeholder" class="d-flex align-items-center justify-content-center h-100 ph-seo-text-muted small">
                        <span class="icon-image"></span> No image
                    </div>
                </div>
                <div class="p-2">
                    <div id="prev-fb-domain" class="ph-seo-text-muted small text-uppercase ph-seo-font-9">domain.com</div>
                    <div id="prev-fb-title" class="fw-bold ph-seo-font-13 ph-seo-line-12"><?php echo Text::_('COM_PHOCASEO_TITLE'); ?></div>
                    <div id="prev-fb-desc" class="ph-seo-text-muted small ph-seo-font-11 ph-seo-line-13"><?php echo Text::_('COM_PHOCASEO_DESCRIPTION'); ?></div>
                </div>
            </div>

            <div class="small fw-bold mb-2">Twitter/X Preview <span class="ph-seo-save-notice" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_PHOCASEO_UPDATED_AFTER_SAVE'); ?>"><span class="icon-warning text-warning" aria-hidden="true"></span></span></div>
            <div id="ph-soc-prev-twitter" class="border rounded ph-seo-tw-card">
                <div id="prev-tw-img-container" class="ph-seo-tw-img-container">
                    <img id="prev-tw-img" src="" class="ph-seo-tw-img" alt="Twitter Preview">
                    <div id="prev-tw-img-placeholder" class="d-flex align-items-center justify-content-center h-100 ph-seo-tw-placeholder">
                        <span class="icon-image"></span>
                    </div>
                </div>
                <div class="p-2 ph-seo-tw-body">
                    <div id="prev-tw-title" class="fw-bold ph-seo-font-13 ph-seo-line-12"><?php echo Text::_('COM_PHOCASEO_TITLE'); ?></div>
                    <div id="prev-tw-desc" class="ph-seo-text-muted small ph-seo-font-11"><?php echo Text::_('COM_PHOCASEO_DESCRIPTION'); ?></div>
                    <div id="prev-tw-domain" class="ph-seo-text-muted small ph-seo-font-10"><span class="icon-link"></span> domain.com</div>
                </div>
            </div>
        </div>

        <div id="ph-seo-tab-links" class="ph-seo-tab-content d-none">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="small fw-bold"><?php echo Text::_('COM_PHOCASEO_LINK_STATISTICS'); ?></div>
                <button type="button" id="ph-seo-scan-links-btn" class="btn btn-xs btn-primary py-1 ph-seo-font-10">
                    <span class="icon-loop"></span> <?php echo Text::_('COM_PHOCASEO_CHECK_LINKS'); ?>
                </button>
            </div>
            <div id="ph-seo-link-stats">
                <div class="d-flex justify-content-between mb-2 p-2 border rounded">
                    <span class="small"><?php echo Text::_('COM_PHOCASEO_INTERNAL_OUTBOUND'); ?></span>
                    <span id="ph-link-internal-out" class="badge ph-seo-link-bg-int-out">0</span>
                </div>
                <div class="d-flex justify-content-between mb-2 p-2 border rounded">
                    <span class="small"><?php echo Text::_('COM_PHOCASEO_EXTERNAL_OUTBOUND'); ?></span>
                    <span id="ph-link-external-out" class="badge ph-seo-link-bg-ext-out">0</span>
                </div>
                <div class="d-flex justify-content-between mb-2 p-2 border rounded">
                    <span class="small"><?php echo Text::_('COM_PHOCASEO_INTERNAL_INBOUND'); ?> <span class="ph-seo-save-notice" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_PHOCASEO_UPDATED_AFTER_SAVE'); ?>"><span class="icon-warning text-warning" aria-hidden="true"></span></span></span>
                    <span id="ph-link-internal-in" class="badge ph-seo-link-bg-int-in">0</span>
                </div>
            </div>
            <div id="ph-seo-link-suggestions-section" class="mt-3 pt-2 border-top">
                <div class="small fw-bold mb-1"><span class="icon-link"></span> <?php echo Text::_('COM_PHOCASEO_SUGGESTED_INTERNAL_LINKS'); ?></div>
                <div id="ph-seo-link-suggestions" class="small ph-seo-scroll-sugg">
                    <em class="ph-seo-text-muted"><?php echo Text::_('COM_PHOCASEO_LOADING_SUGGESTIONS'); ?></em>
                </div>
            </div>
        </div>

        <div id="ph-seo-tab-settings" class="ph-seo-tab-content d-none">
            <div class="small fw-bold mb-2"><?php echo Text::_('COM_PHOCASEO_ADVANCED_SETTINGS'); ?></div>

            <div class="mb-3">
                <label class="form-label small fw-bold mb-1" for="ph-seo-canonical-sidebar"><?php echo Text::_('COM_PHOCASEO_CANONICAL_URL'); ?></label>
                <select id="ph-seo-canonical-sidebar" class="form-select form-select-sm">
                    <option value=""><?php echo Text::_('COM_PHOCASEO_SELECT_CANONICAL_DEFAULT'); ?></option>
                    <?php if (!empty($canonicalRoutes)) : ?>
                        <?php foreach ($canonicalRoutes as $route) : ?>
                            <option value="<?php echo htmlspecialchars($route['url']); ?>"><?php echo htmlspecialchars($route['url']); ?> (<?php echo htmlspecialchars($route['note']); ?>)</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </div> </div>
