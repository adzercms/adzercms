<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Realtime\LiveEntities;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class AdminBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Admin;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\Admin\\', '../src/')
            ->exclude(['../src/Entity/', '../src/AdminBundle.php', '../src/LiveModules.php']);

        if (interface_exists(LiveEntities::class)) {
            $container->services()->set(LiveModules::class)->autowire()->autoconfigure();
        }
    }
}
