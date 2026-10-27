<?php

declare(strict_types=1);

use AdzerCMS\Opotopo\Tests\Fixture\ShelfCatalog;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(ShelfCatalog::class)->autowire()->autoconfigure()->public();
};
