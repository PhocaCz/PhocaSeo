<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

namespace Phoca\Component\PhocaSeo\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Form\FormField;


class SeoSidebarField extends FormField
{

    protected $type = 'SeoSidebar';

    protected function getInput()
    {
        // Render the sidebar layout via direct include to avoid path issues
        ob_start();
        $layoutPath = JPATH_ADMINISTRATOR . '/components/com_phocaseo/layouts/phocaseo/sidebar.php';
        if (file_exists($layoutPath)) {
            include $layoutPath;
        } else {
             echo '<div class="alert alert-danger">Phoca SEO Sidebar Layout Not Found: ' . htmlspecialchars($layoutPath) . '</div>';
        }
        $html = ob_get_clean();
        
        // Wrap in a hidden container so it doesn't disrupt the form flow
        // The JS will move the inner #phoca-seo-sidebar to the right column
        return '<div id="phoca-seo-sidebar-container" class="d-none">' . $html . '</div>';
    }
}
