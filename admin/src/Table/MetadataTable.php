<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Table;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


class MetadataTable extends Table
{
    /**
     * Constructor
     *
     * @param   \Joomla\Database\DatabaseDriver  $db  Database connector object
     *
     * @since   6.0.0
     */
    public function __construct(\Joomla\Database\DatabaseDriver $db)
    {
        parent::__construct('#__phocaseo_metadata', 'id', $db);
    }

    /**
     * Overloaded bind method to pre-process data
     *
     * @param   array  $array   The data to bind to the table
     * @param   mixed  $ignore  An optional array or space separated list of properties to ignore
     *
     * @return  boolean
     *
     * @since   6.0.0
     */
    public function bind($array, $ignore = '')
    {
        return parent::bind($array, $ignore);
    }

    /**
     * Overloaded store method to handle created/modified dates
     *
     * @param   boolean  $updateNulls  True to update null values
     *
     * @return  boolean
     *
     * @since   6.0.0
     */
    public function store($updateNulls = false)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getUser();

        if (!$this->id) {
            // New record
            if (!(int) $this->created) {
                $this->created = $date;
            }
        }

        // Always update modified date
        $this->modified = $date;

        return parent::store($updateNulls);
    }
}
