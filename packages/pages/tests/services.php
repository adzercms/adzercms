<?php

declare(strict_types=1);

use AdzerCMS\Pages\Tests\Fixture\Lounge;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(Lounge::class)->autoconfigure();
};
