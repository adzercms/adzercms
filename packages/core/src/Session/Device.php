<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Session;

use DeviceDetector\Cache\PSR6Bridge;
use DeviceDetector\DeviceDetector;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Device
{
    public function __construct(#[Autowire(service: 'cache.system')] private readonly CacheItemPoolInterface $rules)
    {
    }

    public function label(string $agent): ?string
    {
        $detector = new DeviceDetector($agent);
        $detector->setCache(new PSR6Bridge($this->rules));
        $detector->parse();
        $version = $detector->getClient('version');

        $label = implode(', ', array_filter([
            self::named($detector->getClient('name'), \is_string($version) ? strtok($version, '.') : null),
            self::named($detector->getOs('name'), $detector->getOs('version')),
        ]));

        return '' === $label ? null : $label;
    }

    private static function named(mixed $name, mixed $version): ?string
    {
        if (!\is_string($name) || '' === $name || DeviceDetector::UNKNOWN === $name) {
            return null;
        }

        return \is_string($version) && '' !== $version && DeviceDetector::UNKNOWN !== $version ? $name.' '.$version : $name;
    }
}
