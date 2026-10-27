<?php

declare(strict_types=1);

use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Realtime\HubKind;
use AdzerCMS\Realtime\Tests\Fixture\HeraldHub;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $bundles = $builder->getParameter('kernel.bundles');
    if (\is_array($bundles) && [] === array_filter($bundles, static fn (string $bundle): bool => is_subclass_of($bundle, Module::class) && HubKind::Hub === $bundle::kind())) {
        $container->services()->set(HeraldHub::class)->autoconfigure()->public();
    }
};
