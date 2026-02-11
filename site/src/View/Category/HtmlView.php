<?php
/**
 * @package     Phoca.Site
 * @subpackage  com_phocaseo
 *
 * @copyright   Copyright (C) Jan Pavelka www.phoca.cz
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Site\View\Category;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Category View
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  mixed  A string if successful, otherwise an Error object.
     *
     * @since   6.0.0
     */
    public function display($tpl = null)
    {
        return parent::display($tpl);
    }
}
