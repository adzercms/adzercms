<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Tests;

use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Pictures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

final class PicturesTest extends SchemaTestCase
{
    use Browser;

    #[Group('ACMS-PAGE-0012')]
    public function testPictureNoBlockHoldsLeavesAfterTheTerm(): void
    {
        $pictures = self::service(Pictures::class);
        [$held, $stale, $fresh] = array_map(fn (string $name): string => $pictures->store($this->picture($name)), ['held.png', 'stale.png', 'fresh.png']);
        $page = new Page('Home', Page::ROOT);
        $this->em->persist($page);
        $this->em->persist(new PageBlock($page, 'hero', ['title' => 'Internet', 'picture' => $held]));
        $this->em->flush();
        foreach ([$held, $stale] as $name) {
            touch((string) $pictures->path($name), (int) strtotime('-2 days'));
        }

        $sweep = self::command('adzercms:pages');
        $sweep->execute([]);

        self::assertSame(Command::SUCCESS, $sweep->getStatusCode());
        self::assertNull($pictures->path($stale), 'a picture no block holds leaves after the term');
        self::assertNotNull($pictures->path($held), 'a picture a block holds stays');
        self::assertNotNull($pictures->path($fresh), 'an upload within the term waits for the document it goes to');
    }
}
