<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Opotopo\Tests\Marketplace;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

final class ListingsTest extends CatalogTestCase
{
    use Marketplace;

    private const EXTENSIONS = '00000000-0000-0000-0032-00000000000b';

    #[Group('ACMS-US-0038')]
    #[Group('ACMS-EXT-0006')]
    #[Group('ACMS-EXT-0020')]
    public function testPublishedOperatorEntriesGoToOpotopoAsRepositoryListingsWithLicensePrice(): void
    {
        $this->connect();
        $this->entry('acme/shop', price: '1500.00', package: self::package('Online store', title: ['en' => 'Shop']));
        $this->entry('acme/blog', package: self::package('Blog posts'));
        $this->entry('acme/draft');
        $author = $this->author('+79990000001');
        $this->entry('acme/theirs', package: self::package('Their module', author: $author->getKey()), author: $author)->decide(Decision::Published);
        $this->em->flush();

        $job = self::command('adzercms:opotopo');

        self::assertSame(Command::SUCCESS, $job->execute(['--catalog' => 'extensions']), $job->getDisplay());
        self::assertSame([
            self::listing('acme/shop', 'Shop', 'Online store', 1500),
            self::listing('acme/blog', 'acme/blog', 'Blog posts', 0),
        ], self::items($this->synced[0]['body']));
    }

    /** @return array<string, mixed> */
    private static function listing(string $name, string $title, string $description, int $price): array
    {
        return [
            'sku' => $name,
            'title' => $title,
            'description' => $description,
            'offerType' => 'PRODUCT',
            'categoryId' => self::EXTENSIONS,
            'repositoryPackage' => ['manager' => 'COMPOSER', 'name' => $name],
            'packageGitSource' => ['repositoryUrl' => self::SOURCE],
            'variants' => [['name' => self::phrase('extensions.license'), 'price' => $price, 'includes3dModel' => false]],
        ];
    }
}
