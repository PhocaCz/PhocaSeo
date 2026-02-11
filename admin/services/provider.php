<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
$srcPath = dirname(__DIR__) . '/src';
\JLoader::registerNamespace('Phoca\Component\PhocaSeo\Administrator', $srcPath);
\JLoader::registerNamespace('Phoca\Component\PhocaSeo\Site', JPATH_SITE . '/components/com_phocaseo/src');

use Phoca\Component\PhocaSeo\Administrator\Extension\PhocaSeoComponent;


return new class () implements ServiceProviderInterface {

    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\Phoca\\Component\\PhocaSeo'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Phoca\\Component\\PhocaSeo'));

        $container->set(
            ComponentInterface::class,
            function (Container $container): PhocaSeoComponent {
                $component = new PhocaSeoComponent($container->get(ComponentDispatcherFactoryInterface::class));

                $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
