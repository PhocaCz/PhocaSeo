<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

namespace Phoca\Component\PhocaSeo\Administrator\Helper;

defined('_JEXEC') or die;

//use Joomla\CMS\Factory;
//use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Component\ComponentHelper;

class SeoRulesHelper
{
    public static function getRules(): array {
        // 1. Check custom path from params
        $params = ComponentHelper::getParams('com_phocaseo');
        $customFile = trim($params->get('custom_rules_file', ''));

        if ($customFile !== '') {
            // Path relative to JPATH_ROOT
            $customPath = JPATH_ROOT . '/' . $customFile;

            // Clean path to prevent traversal (basic check)
            $customPath = Path::clean($customPath);

            if (is_file($customPath)) {
                $content = file_get_contents($customPath);
                $rules = json_decode($content, true);
                if ($rules) {
                    return $rules;
                }
            }
        }

        // 2. Default fallback
        $path = JPATH_SITE. '/media/com_phocaseo/rules/rules.json';

        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $rules   = json_decode($content, true);

        return $rules ?: [];
    }

    public static function getFieldRules(string $field): array
    {
        $rules = self::getRules();

        return $rules['parameters'][$field] ?? [];
    }
}
