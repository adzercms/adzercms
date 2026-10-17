<?php

declare(strict_types=1);

namespace AdzerCMS\Centrifugo;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Realtime\HubKind;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class CentrifugoBundle extends Module
{
    public static function kind(): Kind
    {
        return HubKind::Hub;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\Centrifugo\\', '../src/')
            ->exclude(['../src/CentrifugoBundle.php']);
    }
}
