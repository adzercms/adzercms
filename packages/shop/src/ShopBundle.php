<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

final class ShopBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Feature;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', ['rate_limiter' => [
            Checkout::LIMITER => ['policy' => 'fixed_window', 'limit' => Checkout::LIMIT, 'interval' => Checkout::INTERVAL],
        ]]);
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure()
            ->load('AdzerCMS\\Shop\\', '../src/')
            ->exclude(['../src/Entity/', '../src/ShopBundle.php']);
        $services->set('shop.images', ImageManager::class)->args([Driver::class]);
        $services->get(Photos::class)->arg('$images', new Reference('shop.images'));
    }
}
