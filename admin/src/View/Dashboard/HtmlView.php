<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\View\Dashboard;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Phoca\Component\PhocaSeo\Administrator\Ai\AiClientFactory;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class HtmlView extends BaseHtmlView{

    protected string $version = '6.0.0';
    protected array $views = [];
    protected bool $aiConfigured = false;
    protected array $pluginStatuses = [];
    protected array $extensionStatuses = [];
    protected array $seoMetrics = [];
    protected array $redirectStatus = [];
    protected array $sitemapStatus = [];

    public function display($tpl = null): void {

        $this->version = PhocaSeoHelper::getPhocaVersion('com_phocaseo');

        // Set up quick icon views (Phoca-style)
        $this->views = [
            'items' => [Text::_('COM_PHOCASEO_METADATA_MANAGER'), PhocaSeoHelper::renderSvg('items'), '#8c0069 '],
            'links' => [Text::_('COM_PHOCASEO_LINK_MANAGER'), PhocaSeoHelper::renderSvg('links'), '#da7400'],
            'pages' => [Text::_('COM_PHOCASEO_PAGES_MANAGER'), PhocaSeoHelper::renderSvg('pages'), '#00aaff'],
            'options' => [Text::_('COM_PHOCASEO_OPTIONS'), PhocaSeoHelper::renderSvg('options'), '#55557f'],
            'info' => [Text::_('COM_PHOCASEO_INFO'), PhocaSeoHelper::renderSvg('info'), '#3378cc']
        ];

        // Check AI configuration status
        $this->aiConfigured = AiClientFactory::isAnyProviderConfigured();

        // Get plugin statuses for SEO Health Check
        $this->pluginStatuses = $this->getPluginStatuses();
        $this->extensionStatuses = $this->getExtensionStatuses();

        // Get SEO Metrics
        $this->seoMetrics = $this->getSeoMetrics();

        // Get detailed redirect status
        $this->redirectStatus = $this->getRedirectStatus();

        // Get sitemap status
        $this->sitemapStatus = PhocaSeoHelper::checkSitemap();

        // Register Web Assets
        $wa = $this->document->getWebAssetManager();
        $wa->useStyle('com_phocaseo.admin.dashboard');

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function getPluginStatuses(): array {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $statuses = [];

        $plugins = [
            'phocaseo_system' => ['folder' => 'system', 'element' => 'phocaseo', 'name' => 'Phoca SEO Plugin (System)', 'link' => 'https://www.phoca.cz/phoca-open-graph-system-plugin'],
            'phocaseocanonical_system' => ['folder' => 'system', 'element' => 'phocaseocanonical', 'name' => 'Phoca SEO Canonical Plugin (System)', 'link' => 'https://www.phoca.cz/phoca-open-graph-system-plugin'],
            'phocaopengraph_system' => ['folder' => 'system', 'element' => 'phocaopengraph', 'name' => 'Phoca Open Graph Plugin (System)', 'link' => 'https://www.phoca.cz/phoca-open-graph-system-plugin'],
            'phocaopengraph_content' => ['folder' => 'content', 'element' => 'phocaopengraph', 'name' => 'Phoca Open Graph Plugin (Content)', 'link' => 'https://www.phoca.cz/phoca-open-graph-plugin'],
            'schemaorg' => ['folder' => 'system', 'element' => 'schemaorg', 'name' => 'Schema.org Plugin', 'link' => ''],
            'redirect' => ['folder' => 'system', 'element' => 'redirect', 'name' => 'Redirect Plugin', 'link' => ''],
        ];

        foreach ($plugins as $key => $plugin) {
            $query = $db->getQuery(true)
                ->select('enabled')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote($plugin['folder']))
                ->where($db->quoteName('element') . ' = ' . $db->quote($plugin['element']));

            $db->setQuery($query);
            $result = $db->loadResult();

            $statuses[$key] = [
                'name' => $plugin['name'],
                'installed' => $result !== null,
                'enabled' => (int) $result === 1,
                'link' => $plugin['link'],
            ];
        }

        return $statuses;
    }

    protected function getExtensionStatuses(): array {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $statuses = [];

        $extensions = [
            'phocafilteroptions' => ['type' => 'plugin', 'folder' => 'system', 'element' => 'phocafilteroptions', 'name' => 'Phoca Filter Options Plugin (System)', 'link' => 'https://www.phoca.cz/phoca-filter-options-system-plugin'],
            'phocadesktop' => ['type' => 'plugin', 'folder' => 'system', 'element' => 'phocadesktop', 'name' => 'Phoca Desktop Plugin (System)', 'link' => 'https://www.phoca.cz/phoca-desktop-system-plugin'],
            'phocacollapse' => ['type' => 'plugin', 'folder' => 'system', 'element' => 'phocacollapse', 'name' => 'Phoca Collapse Plugin (System)', 'link' => 'https://www.phoca.cz/phoca-collapse-system-plugin'],
            'phocatopmenu' => ['type' => 'module', 'folder' => '', 'element' => 'mod_phocatopmenu', 'name' => 'Phoca Top Menu Module', 'link' => 'https://www.phoca.cz/phoca-top-menu-module'],
        ];

        foreach ($extensions as $key => $extension) {
            $query = $db->getQuery(true)
                ->select('enabled')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote($extension['type']))
                ->where($db->quoteName('folder') . ' = ' . $db->quote($extension['folder']))
                ->where($db->quoteName('element') . ' = ' . $db->quote($extension['element']));

            $db->setQuery($query);
            $result = $db->loadResult();

            $statuses[$key] = [
                'name' => $extension['name'],
                'installed' => $result !== null,
                'enabled' => (int) $result === 1,
                'link' => $extension['link'],
            ];
        }

        return $statuses;
    }

    protected function getSeoMetrics(): array {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $metrics = [
            'articles_missing_meta' => 0,
            'low_score_pages' => 0,
            'active_redirects' => 0,
            'orphan_pages' => 0,
        ];

        // Articles missing meta descriptions
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__content'))
                ->where('(' . $db->quoteName('metadesc') . ' = ' . $db->quote('') . ' OR ' . $db->quoteName('metadesc') . ' IS NULL)')
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($query);
            $metrics['articles_missing_meta'] = (int) $db->loadResult();
        } catch (\Exception $e) {
            // Table might not exist
        }

        // Low score pages (score < 40)
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__phocaseo_metadata'))
                ->where($db->quoteName('seo_score') . ' < 40')
                ->where($db->quoteName('seo_score') . ' > 0');
            $db->setQuery($query);
            $metrics['low_score_pages'] = (int) $db->loadResult();
        } catch (\Exception $e) {
            // Table might not exist
        }

        // Active redirects
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__redirect_links'))
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($query);
            $metrics['active_redirects'] = (int) $db->loadResult();
        } catch (\Exception $e) {
            // Redirect component not installed
        }

        // Orphan pages (pages with no inbound links)
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__content', 'a'))
                ->where($db->quoteName('a.state') . ' = 1')
                ->where('NOT EXISTS (' .
                    'SELECT 1 FROM ' . $db->quoteName('#__phocaseo_links', 'l') .
                    ' WHERE l.target_option = ' . $db->quote('com_content') .
                    ' AND l.target_view = ' . $db->quote('article') .
                    ' AND l.target_id = a.id' .
                    ' AND l.link_type = ' . $db->quote('internal') .
                ')');
            $db->setQuery($query);
            $metrics['orphan_pages'] = (int) $db->loadResult();
        } catch (\Exception $e) {
            // Tables might not exist
        }

        return $metrics;
    }

    protected function getRedirectStatus(): array {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $status = [
            'installed' => false,
            'enabled' => false,
            'collect_urls' => false,
            'url_count' => 0,
        ];

        try {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('enabled'), $db->quoteName('params')])
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('redirect'));

            $db->setQuery($query);
            $row = $db->loadObject();

            if ($row !== null) {
                $status['installed'] = true;
                $status['enabled'] = (int) $row->enabled === 1;

                if (!empty($row->params)) {
                    $params = json_decode($row->params, true);
                    $status['collect_urls'] = !empty($params['collect_urls']);
                }
            }

            // Get redirect count
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__redirect_links'));
            $db->setQuery($query);
            $status['url_count'] = (int) $db->loadResult();

        } catch (\Exception $e) {
            // Redirect not installed
        }

        return $status;
    }

    protected function addToolbar(): void {
        $canDo = $this->canDo();

        ToolbarHelper::title(Text::_('COM_PHOCASEO_DASHBOARD'), 'home-2 phocaseo');

        $toolbar = Toolbar::getInstance('toolbar');

        // Control Panel button
        $toolbar->linkButton('dashboard', 'COM_PHOCASEO_DASHBOARD')
            ->url('index.php?option=com_phocaseo')
            ->icon('icon-home-2')
            ->buttonClass('btn btn-primary');

        if ($canDo->get('core.admin')) {
            ToolbarHelper::preferences('com_phocaseo');
        }

        ToolbarHelper::divider();
        ToolbarHelper::help('screen.phocaseo', true);
    }

    protected function canDo(): object {
        return ContentHelper::getActions('com_phocaseo');
    }
}
