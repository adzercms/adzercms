<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Support\DigestSection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class LeadsBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Feature;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', ['rate_limiter' => [
            Intake::LIMITER => ['policy' => 'fixed_window', 'limit' => Intake::LIMIT, 'interval' => Intake::INTERVAL],
        ]]);
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\Leads\\', '../src/')
            ->exclude(['../src/Entity/', '../src/LeadsBundle.php', '../src/OverdueDigest.php']);

        if (interface_exists(DigestSection::class)) {
            $container->services()->set(OverdueDigest::class)->autowire()->autoconfigure();
        }
    }
}
