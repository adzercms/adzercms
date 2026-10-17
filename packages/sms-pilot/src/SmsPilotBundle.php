<?php

declare(strict_types=1);

namespace AdzerCMS\SmsPilot;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class SmsPilotBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Sms;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\SmsPilot\\', '../src/')
            ->exclude(['../src/Entity/', '../src/SmsPilotBundle.php']);
    }
}
