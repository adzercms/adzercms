<?php

declare(strict_types=1);

use AdzerCMS\Shop\Tests\Fixture\Counters;
use AdzerCMS\Shop\Tests\Fixture\Till;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(Counters::class)->autoconfigure()->public();
    $container->services()->set(Till::class)->autoconfigure()->public();
};
