<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Entity\Setting;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Settings;

trait Configuration
{
    /** @param array<string, string> $values */
    protected function configure(array $values): void
    {
        $fields = self::service(Catalog::class)->fields();
        foreach ($values as $name => $value) {
            self::assertNull(self::service(Settings::class)->save($fields[$name] ?? self::fail(\sprintf('setting %s is not in the catalog', $name)), $value), $name);
        }
        $this->em->flush();
    }

    /** @param array<string, string> $values */
    protected function store(array $values): void
    {
        foreach ($values as $name => $value) {
            $setting = $this->em->find(Setting::class, $name);
            null === $setting ? $this->em->persist(new Setting($name, $value)) : $setting->setValue($value);
        }
        $this->em->flush();
        self::service(Settings::class)->reset();
    }

    protected function unconfigure(string ...$names): void
    {
        $fields = self::service(Catalog::class)->fields();
        foreach ($names as $name) {
            self::service(Settings::class)->clear($fields[$name] ?? self::fail(\sprintf('setting %s is not in the catalog', $name)));
        }
        $this->em->flush();
    }
}
