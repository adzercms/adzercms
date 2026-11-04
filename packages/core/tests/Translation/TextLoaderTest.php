<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Translation;

use AdzerCMS\Core\Text\TextLoader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class TextLoaderTest extends TestCase
{
    #[Group('ACMS-CORE-1062')]
    public function testLoaderWithoutDatabaseAddressGivesEmptyCatalogWithoutConnection(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');

        self::assertSame([], (new TextLoader($em, ''))->load(TextLoader::FORMAT, 'ru', 'core+intl-icu')->all());
    }
}
