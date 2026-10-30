<?php

declare(strict_types=1);

use AdzerCMS\Admin\Tests\Fixture\Branches;
use AdzerCMS\Admin\Tests\Fixture\Footfall;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(Branches::class)->autoconfigure()->public();
    $container->services()->set(Footfall::class)->autoconfigure()->public();
};
