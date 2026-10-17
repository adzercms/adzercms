<?php

declare(strict_types=1);

namespace AdzerCMS\YooKassa;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class YooKassaBundle extends Module
{
    public static function kind(): Kind
    {
        return \AdzerCMS\Payments\PaymentKind::Acquiring;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\YooKassa\\', '../src/')
            ->exclude(['../src/Entity/', '../src/YooKassaBundle.php']);
    }
}
