<?php

declare(strict_types=1);

use AdzerCMS\Core\Tests\HttpAnswers;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(HttpAnswers::class)->public();
};
