<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Realtime\LiveEntities;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class SupportBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Feature;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\Support\\', '../src/')
            ->exclude(['../src/Entity/', '../src/SupportBundle.php', '../src/LiveTickets.php']);

        if (interface_exists(LiveEntities::class)) {
            $container->services()->set(LiveTickets::class)->autowire()->autoconfigure();
        }
    }
}
