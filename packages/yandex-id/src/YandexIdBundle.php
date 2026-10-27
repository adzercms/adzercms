<?php

declare(strict_types=1);

namespace AdzerCMS\YandexId;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class YandexIdBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Login;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->set(YandexIdService::class)->autowire()->autoconfigure();
    }
}
