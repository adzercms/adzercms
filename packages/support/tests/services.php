<?php

declare(strict_types=1);

use AdzerCMS\Support\Tests\Fixture\SubjectFixture;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(SubjectFixture::class)->autoconfigure()->public();
};
