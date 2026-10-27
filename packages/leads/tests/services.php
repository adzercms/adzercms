<?php

declare(strict_types=1);

use AdzerCMS\Leads\Tests\Fixture\ItemSubject;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(ItemSubject::class)->autoconfigure()->public();
};
