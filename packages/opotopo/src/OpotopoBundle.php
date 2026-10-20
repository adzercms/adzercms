<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Support\DigestSection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class OpotopoBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Feature;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\Opotopo\\', '../src/')
            ->exclude(['../src/Entity/', '../src/OpotopoBundle.php', '../src/Record.php', '../src/LocalFile.php', '../src/ItemDigest.php']);

        if (interface_exists(DigestSection::class)) {
            $container->services()->set(ItemDigest::class)->autowire()->autoconfigure();
        }
    }
}
