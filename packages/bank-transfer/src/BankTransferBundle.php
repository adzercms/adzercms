<?php

declare(strict_types=1);

namespace AdzerCMS\BankTransfer;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Payments\PaymentKind;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class BankTransferBundle extends Module
{
    public static function kind(): Kind
    {
        return PaymentKind::Acquiring;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\BankTransfer\\', '../src/')
            ->exclude(['../src/BankTransferBundle.php']);
    }
}
