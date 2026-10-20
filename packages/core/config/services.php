<?php

declare(strict_types=1);

use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\Snapshot;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Schema\MigrationOrder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Extra\CssInliner\CssInlinerExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()->autowire()->autoconfigure();

    $services->load('AdzerCMS\\Core\\Account\\', '../src/Account/');
    $services->load('AdzerCMS\\Core\\Captcha\\', '../src/Captcha/');
    $services->load('AdzerCMS\\Core\\Command\\', '../src/Command/');
    $services->load('AdzerCMS\\Core\\Confirmation\\', '../src/Confirmation/');
    $services->load('AdzerCMS\\Core\\Controller\\', '../src/Controller/');
    $services->load('AdzerCMS\\Core\\Login\\', '../src/Login/');
    $services->load('AdzerCMS\\Core\\Notice\\', '../src/Notice/')->exclude('../src/Notice/BotChannel.php');
    $services->load('AdzerCMS\\Core\\Repository\\', '../src/Repository/');
    $services->load('AdzerCMS\\Core\\Security\\', '../src/Security/');
    $services->load('AdzerCMS\\Core\\Seo\\', '../src/Seo/')->exclude('../src/Seo/Head.php');
    $services->load('AdzerCMS\\Core\\Session\\', '../src/Session/');
    $services->load('AdzerCMS\\Core\\Setting\\', '../src/Setting/');
    $services->load('AdzerCMS\\Core\\Text\\', '../src/Text/');
    $services->load('AdzerCMS\\Core\\Ui\\', '../src/Ui/');
    $services->set(Repositories::class);
    $services->set(Snapshot::class);
    $services->set(Catalog::class);
    $services->set(Switches::class);
    $services->set(MigrationOrder::class);
    $services->set(CssInlinerExtension::class);
};
