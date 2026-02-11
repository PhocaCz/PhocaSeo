<?php
/**
 * @package     Phoca.Site
 * @subpackage  com_phocaseo
 *
 * @copyright   Copyright (C) Jan Pavelka www.phoca.cz
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Site\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Display Controller
 *
 * @since  6.0.0
 */
class DisplayController extends BaseController
{
    /**
     * The default view.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $default_view = 'category';
}
