<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Composer\Description;
use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\Entity\ExtensionAuthor;
use AdzerCMS\Extensions\Repository\ExtensionRepository;

abstract class CatalogTestCase extends SchemaTestCase
{
    use Accounts;
    use Browser;

    protected const SOURCE = 'https://github.com/acme/code';

    protected function operate(): void
    {
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    protected function author(string $phone, ?string $email = null): ExtensionAuthor
    {
        $author = new ExtensionAuthor($this->record($phone, $email));
        $this->em->persist($author);
        $this->em->flush();

        return $author;
    }

    protected function entry(string $name, ExtensionType $type = ExtensionType::Module, ?string $price = null, ?Package $package = null, ?string $demo = null, ?ExtensionAuthor $author = null): Extension
    {
        $entry = (new Extension($author))->describe($name, $type, $price, $demo)->know($package);
        $entry->place(\count(self::extensions()->findAll()) + 1);
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * @param list<Category>        $categories
     * @param array<string, string> $translations
     */
    protected static function package(string $description, ?string $homepage = null, array $categories = [Category::Other], array $translations = [], ?string $author = null): Package
    {
        return new Package(new Description($description, $translations), '1.0.0', $categories, self::SOURCE, $homepage, Registry::TYPE, null, null, $author);
    }

    protected static function extensions(): ExtensionRepository
    {
        return self::service(ExtensionRepository::class);
    }
}
