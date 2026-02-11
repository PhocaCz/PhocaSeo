<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\MVC\Model\AdminModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


class ItemModel extends AdminModel
{
    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure
     *
     * @since   6.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        FormHelper::addFormPath(JPATH_ADMINISTRATOR . '/components/com_phocaseo/src/Model/Forms');

        $form = $this->loadForm(
            'com_phocaseo.item',
            'item',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            Factory::getApplication()->enqueueMessage('Failed to load form item.xml', 'error');
            return false;
        }

        return $form;
    }

    /**
     * Method to load the form data
     *
     * @return  array|boolean  Array of properties, or false on error.
     *
     * @since   6.0.0
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_phocaseo.edit.item.data', []);

        if (empty($data)) {
            $data = $this->getItem();
            
            // If we are creating a new record based on reference context/id
            if (empty($data->id)) {
                $app = Factory::getApplication();
                $refId = $app->input->getInt('ref_id', 0);
                $refContext = $app->input->get('ref_context', '');
                
                // If not in input, check stashed state from Controller::add
                if ($refId === 0) {
                    $refId = $app->getUserState('com_phocaseo.add.ref_id', 0);
                    $refContext = $app->getUserState('com_phocaseo.add.ref_context', '');
                }

                if ($refId > 0 && $refContext != '') {
                    $data->item_id = $refId;
                    $data->context = $refContext;
                    $app->enqueueMessage("Loaded from stash: $refContext / $refId", 'notice');
                } else {
                    $app->enqueueMessage("No reference data found in stash or URL", 'warning');
                }
            }
        }

        return $data;
    }
    
    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $type    The table name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\Table\Table  A Table object
     *
     * @since   6.0.0
     */
    public function getTable($type = 'Metadata', $prefix = 'Phoca\Component\PhocaSeo\Table\\', $config = [])
    {
        return parent::getTable($type, $prefix, $config);
    }
}
