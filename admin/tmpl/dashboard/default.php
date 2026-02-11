<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die;

?>

<div class="ph-dashboard-container">
    <div class="ph-dashboard-main">

        <?php if (!$this->aiConfigured) : ?>
            <div class="ph-ai-status not-configured">
                <span class="icon-warning" aria-hidden="true"></span>
                <?php echo Text::_('COM_PHOCASEO_AI_NOT_CONFIGURED'); ?>
                <a href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_phocaseo'); ?>" class="btn btn-sm btn-outline-warning ms-auto">
                    <?php echo Text::_('COM_PHOCASEO_CONFIGURE_NOW'); ?>
                </a>
            </div>
        <?php else : ?>
            <div class="ph-ai-status">
                <span class="icon-check" aria-hidden="true"></span>
                <?php echo Text::_('COM_PHOCASEO_AI_ACTIVE'); ?>
            </div>
        <?php endif; ?>

        <div class="ph-quick-icons">
            <?php foreach ($this->views as $viewName => $viewData) : ?>
                <?php
                $link = 'index.php?option=com_phocaseo&view=' . $viewName;
                if ($viewName === 'options') {
                    $link = 'index.php?option=com_config&view=component&component=com_phocaseo';
                }
                ?>
            <div class="ph-quick-icon-box">
            <div class="ph-quick-icon-box-icon">
                <a href="<?php echo Route::_($link); ?>" class="ph-quick-icon">
                    <span style="color: <?php echo $viewData[2]; ?>" class="ph-quick-icon-svg"><?php echo $viewData[1] ?></span>
                </a>
            </div>
               <div class="ph-quick-icon-box-text"> <a href="<?php echo Route::_($link); ?>" class="ph-quick-icontext">
                    <span class="ph-quick-icon-title"><?php echo $viewData[0]; ?></span>
                </a>
               </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ph-card">
            <div class="ph-card-header">
                <span class="icon-health"></span>
                <?php echo Text::_('COM_PHOCASEO_SEO_HEALTH'); ?>
            </div>
            <div class="ph-card-body">
                <div class="ph-plugin-statuses">
                    <?php foreach ($this->pluginStatuses as $key => $status) : ?>
                        <div class="ph-plugin-status-row">
                            <span class="ph-plugin-name"><?php echo $status['name']; ?></span>
                            <?php if (!$status['installed']) : ?>
                                <span class="badge bg-secondary"><?php echo Text::_('COM_PHOCASEO_NOT_INSTALLED'); ?></span>
                                <?php if ($status['link'] != '') { ?>
                                <span class="ph-seo-product-link"><?php echo Text::_('COM_PHOCASEO_LEARN_MORE') ?>: <a href="<?php echo $status['link'] ?>" target="_blank"><?php echo $status['name'];?></a></span>
                                <?php } ?>
                            <?php elseif ($status['enabled']) : ?>
                                <span class="badge bg-success"><?php echo Text::_('COM_PHOCASEO_ENABLED'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-danger"><?php echo Text::_('COM_PHOCASEO_DISABLED'); ?></span>
                                <?php if ($status['link'] != '') { ?>
                                <span class="ph-seo-product-link"><?php echo Text::_('COM_PHOCASEO_LEARN_MORE') ?>: <a href="<?php echo $status['link'] ?>" target="_blank"><?php echo $status['name'];?></a></span>
                                <?php } ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="ph-plugin-status-row">
                        <span class="ph-plugin-name"><?php echo Text::_('COM_PHOCASEO_SITEMAP'); ?></span>
                        <?php
                        $sources = [];
                        if ($this->sitemapStatus['root_sitemap']) $sources[] = 'Root';
                        if ($this->sitemapStatus['robots_link']) $sources[] = 'Robots.txt';
                        $sourceStr = !empty($sources) ? ' (' . implode(' & ', $sources) . ')' : '';

                        if ($this->sitemapStatus['root_sitemap'] || ($this->sitemapStatus['robots_link'] && $this->sitemapStatus['robots_valid'])) : ?>
                            <span class="badge bg-success" title="<?php echo htmlspecialchars($this->sitemapStatus['link']); ?>"><?php echo Text::_('COM_PHOCASEO_FOUND') . $sourceStr; ?></span>
                        <?php elseif ($this->sitemapStatus['robots_link']) : ?>
                            <span class="badge bg-warning" title="<?php echo htmlspecialchars($this->sitemapStatus['link']); ?>"><?php echo Text::_('COM_PHOCASEO_SITEMAP_INVALID') . $sourceStr; ?></span>
                        <?php else : ?>
                            <span class="badge bg-danger"><?php echo Text::_('COM_PHOCASEO_NOT_FOUND'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="ph-card">
            <div class="ph-card-header">
                <span class="icon-chart"></span>
                <?php echo Text::_('COM_PHOCASEO_SEO_METRICS'); ?>
            </div>
            <div class="ph-card-body">
                <div class="ph-metrics-grid">
                    <div class="ph-metric-item">
                        <div class="ph-metric-value <?php echo $this->seoMetrics['articles_missing_meta'] > 0 ? 'text-warning' : 'text-success'; ?>">
                            <?php echo $this->seoMetrics['articles_missing_meta']; ?>
                        </div>
                        <div class="ph-metric-label"><?php echo Text::_('COM_PHOCASEO_ARTICLES_MISSING_META'); ?></div>
                    </div>
                    <div class="ph-metric-item">
                        <div class="ph-metric-value <?php echo $this->seoMetrics['low_score_pages'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo $this->seoMetrics['low_score_pages']; ?>
                        </div>
                        <div class="ph-metric-label"><?php echo Text::_('COM_PHOCASEO_LOW_SCORE_PAGES'); ?></div>
                    </div>
                    <div class="ph-metric-item">
                        <div class="ph-metric-value text-info">
                            <?php echo $this->seoMetrics['active_redirects']; ?>
                        </div>
                        <div class="ph-metric-label"><?php echo Text::_('COM_PHOCASEO_ACTIVE_REDIRECTS'); ?></div>
                    </div>
                    <div class="ph-metric-item">
                        <div class="ph-metric-value <?php echo $this->seoMetrics['orphan_pages'] > 0 ? 'text-warning' : 'text-success'; ?>">
                            <?php echo $this->seoMetrics['orphan_pages']; ?>
                        </div>
                        <div class="ph-metric-label"><?php echo Text::_('COM_PHOCASEO_ORPHAN_PAGES_COUNT'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ph-card">
            <div class="ph-card-header">
                <span class="icon-loop"></span>
                <?php echo Text::_('COM_PHOCASEO_REDIRECT_STATUS'); ?>
            </div>
            <div class="ph-card-body">
                <div class="ph-redirect-status">
                    <div class="ph-redirect-status-row">
                        <span class="ph-status-indicator <?php echo $this->redirectStatus['enabled'] ? 'ph-status-success' : 'ph-status-danger'; ?>"></span>
                        <?php if ($this->redirectStatus['enabled']) : ?>
                            <?php echo Text::_('COM_PHOCASEO_REDIRECT_PLUGIN_ENABLED'); ?>
                        <?php else : ?>
                            <?php echo Text::_('COM_PHOCASEO_REDIRECT_PLUGIN_DISABLED'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="ph-redirect-status-row">
                        <span class="ph-status-indicator <?php echo $this->redirectStatus['collect_urls'] ? 'ph-status-warning' : 'ph-status-success'; ?>"></span>
                        <?php if ($this->redirectStatus['collect_urls']) : ?>
                            <?php echo Text::_('COM_PHOCASEO_REDIRECT_COLLECTS_URLS'); ?>
                        <?php else : ?>
                            <?php echo Text::_('COM_PHOCASEO_REDIRECT_NOT_COLLECTS_URLS'); ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($this->redirectStatus['collect_urls']) : ?>
                        <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">
                            <span class="icon-warning-circle"></span>
                            <?php echo Text::_('COM_PHOCASEO_REDIRECT_COLLECT_WARNING'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($this->redirectStatus['url_count'] > 0) : ?>
                        <div class="ph-redirect-status-row mt-2">
                            <span class="ph-status-indicator ph-status-neutral"></span>
                            <?php echo Text::_('COM_PHOCASEO_REDIRECT_SAVED_COUNT'); ?>: <strong><?php echo number_format($this->redirectStatus['url_count'], 0, '.', ' '); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <div class="ph-card">
            <div class="ph-card-header">
                <span class="icon-health"></span>
                <?php echo Text::_('COM_PHOCASEO_FASTER_ADMIN_WORKFLOW'); ?>
            </div>
            <div class="ph-card-body">
                <div class="ph-plugin-statuses">
                    <?php foreach ($this->extensionStatuses as $key => $status) : ?>
                        <div class="ph-plugin-status-row">
                            <span class="ph-plugin-name"><?php echo $status['name']; ?></span>
                            <?php if (!$status['installed']) : ?>
                                <span class="badge bg-secondary"><?php echo Text::_('COM_PHOCASEO_NOT_INSTALLED'); ?></span>
                                <?php if ($status['link'] != '') { ?>
                                <span class="ph-seo-product-link"><?php echo Text::_('COM_PHOCASEO_LEARN_MORE') ?>: <a href="<?php echo $status['link'] ?>" target="_blank"><?php echo $status['name'];?></a></span>
                                <?php } ?>
                            <?php elseif ($status['enabled']) : ?>
                                <span class="badge bg-success"><?php echo Text::_('COM_PHOCASEO_ENABLED'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-danger"><?php echo Text::_('COM_PHOCASEO_DISABLED'); ?></span>
                                <?php if ($status['link'] != '') { ?>
                                <span class="ph-seo-product-link"><?php echo Text::_('COM_PHOCASEO_LEARN_MORE') ?>: <a href="<?php echo $status['link'] ?>" target="_blank"><?php echo $status['name'];?></a></span>
                                <?php } ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>


    </div>

    <div class="ph-dashboard-sidebar">

        <div class="ph-card text-center">
            <div class="ph-card-body"><?php echo  HTMLHelper::_('image', "media/com_phocaseo/images/admin/logo-phoca-seo.svg", Text::_('COM_PHOCASEO'), ['class' => 'ph-logo-product'] ); ?></div>
        </div>

        <div class="ph-card">
            <div class="ph-card-header">
                <span class="icon-info-circle"></span>
                <?php echo Text::_('COM_PHOCASEO_INFO'); ?>
            </div>
            <div class="ph-card-body">
                <div class="ph-info-row mb-2">
                    <strong><?php echo Text::_('COM_PHOCASEO_VERSION'); ?>:</strong>
                    <?php echo $this->version; ?>
                </div>
                <div class="ph-info-row mb-2">
                    <strong><?php echo Text::_('COM_PHOCASEO_COPYRIGHT'); ?>:</strong>
                    <?php echo '© 2007 - '.  date("Y"). '<br>Jan Pavelka' ?>
                </div>
                <div class="ph-info-row mb-2">
                    <strong><?php echo Text::_('COM_PHOCASEO_LICENSE'); ?>:</strong>
                    <?php echo '<a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPLv2</a>' ?>
                </div>
                <div class="ph-info-row mb-2">
                    <strong><?php echo Text::_('COM_PHOCASEO_TRANSLATION'); ?>:</strong>
                    <?php echo Text::_( 'COM_PHOCASEO_TRANSLATION_LANGUAGE_TAG').'<br>'
.'<div>© 2007 - '.  date("Y"). ' '. Text::_('COM_PHOCASEO_TRANSLATER'). '</div>'
.'<div>'.Text::_('COM_PHOCASEO_TRANSLATION_SUPPORT_URL').'</div>' ?>
                </div>
                <hr>
                <div class="d-grid gap-2 ph-info-links">
                    <a href="https://www.phoca.cz/phocaseo" target="_blank" class="">
                        <?php echo Text::_('COM_PHOCASEO'); ?>
                    </a>
                    <a href="https://www.phoca.cz/documentation/" target="_blank" class="">
                        <?php echo Text::_('COM_PHOCASEO_DOCUMENTATION'); ?>
                    </a>
                    <a href="https://www.phoca.cz" target="_blank" class="">
                        Phoca
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
