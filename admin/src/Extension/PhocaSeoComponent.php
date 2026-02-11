<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Extension;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Schemaorg\SchemaorgServiceInterface;
use Joomla\CMS\Schemaorg\SchemaorgServiceTrait;
use Psr\Container\ContainerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class PhocaSeoComponent extends MVCComponent implements
    BootableExtensionInterface,
    SchemaorgServiceInterface
{
    use HTMLRegistryAwareTrait;
    use SchemaorgServiceTrait;

    public function boot(ContainerInterface $container): void {
        // Register HTML services if needed
    }

    public function getSchemaorgContexts(): array {
        return [
            'com_phocaseo.metadata' => 'Phoca SEO Metadata',
        ];
    }

    public function getSupportedContexts(): array {
        return [
            'com_content.article'       => 'COM_PHOCASEO_CONTEXT_ARTICLE',
            'com_content.category'      => 'COM_PHOCASEO_CONTEXT_CATEGORY',
            'com_phocadownload.file'    => 'COM_PHOCASEO_CONTEXT_PHOCADOWNLOAD_FILE',
            'com_phocadownload.category'=> 'COM_PHOCASEO_CONTEXT_PHOCADOWNLOAD_CATEGORY',
            'com_phocagallery.image'    => 'COM_PHOCASEO_CONTEXT_PHOCAGALLERY_IMAGE',
            'com_phocagallery.category' => 'COM_PHOCASEO_CONTEXT_PHOCAGALLERY_CATEGORY',
            'com_contact.contact'       => 'COM_PHOCASEO_CONTEXT_CONTACT',
            'menu.item'                 => 'COM_PHOCASEO_CONTEXT_MENU_ITEM',
        ];
    }

    public function hasNativeMetaSupport(string $context): bool {
        $nativeContexts = [
            'com_content.article',
            'com_content.category',
        ];

        return \in_array($context, $nativeContexts, true);
    }
}
