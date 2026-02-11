<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\View\Info;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;

defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $t = [];

    public function display($tpl = null): void
    {

        $this->t['component_links'] = $this->getLinks(1);
        $this->t['version'] = PhocaSeoHelper::getPhocaVersion('com_phocaseo');

        $wa = $this->document->getWebAssetManager();
        $wa->useStyle('com_phocaseo.admin.dashboard');

        ToolbarHelper::title(Text::_('COM_PHOCASEO_INFO'), 'info-circle phocaseo');
        $toolbar = Toolbar::getInstance('toolbar');

        $toolbar->linkButton('dashboard', 'COM_PHOCASEO_DASHBOARD')
            ->url('index.php?option=com_phocaseo')
            ->icon('icon-home-2')
            ->buttonClass('btn btn-primary');

        parent::display($tpl);
    }


    public function getLinks($internalLinksOnly = 0) {


        $links = array();

        $links[] = array('Phoca SEO site', 'https://www.phoca.cz/phocaseo');
        $links[] = array('Phoca SEO documentation site', 'https://www.phoca.cz/documentation');
        $links[] = array('Phoca SEO download site', 'https://www.phoca.cz/download');
        $links[] = array('Phoca News', 'https://www.phoca.cz/news');
        $links[] = array('Phoca Forum', 'https://www.phoca.cz/forum');

        if ($internalLinksOnly == 1) {
            return $links;
        }

        return $links;
    }
}
