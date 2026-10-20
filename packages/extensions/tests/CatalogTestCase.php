<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\ExtensionType;
use AdzerCMS\Extensions\Repository\ExtensionRepository;

abstract class CatalogTestCase extends SchemaTestCase
{
    use Accounts;
    use Browser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    protected function entry(string $name, ExtensionType $type = ExtensionType::Module, ?string $price = null): Extension
    {
        $entry = (new Extension())->describe($name, $type, $price, null);
        $entry->place(\count(self::extensions()->findAll()) + 1);
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    protected static function extensions(): ExtensionRepository
    {
        return self::service(ExtensionRepository::class);
    }
}
