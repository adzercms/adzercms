<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Controller\UploadController;
use AdzerCMS\Builder\ProjectFile;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\Entity\MenuEntry;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Pictures;
use AdzerCMS\Pages\Repository\MenuEntryRepository;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Repository\PageRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class UploadTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const UPLOAD = '/admin/pages/upload';
    private const PICTURE = '0123456789abcdef0123456789abcdef.webp';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $home = new Page('Home', Page::ROOT);
        $home->place(1);
        $entry = MenuEntry::ofPage($home);
        $entry->place(1);
        $this->em->persist($home);
        $this->em->persist($entry);
        $this->em->flush();
    }

    #[Group('ACMS-BLD-0019')]
    #[Group('ACMS-PAGE-0011')]
    #[Group('ACMS-PAGE-0027')]
    #[Group('ACMS-US-0077')]
    public function testOwnerUploadsProjectFileAsUnpublishedPagesAtTheEndOfMenu(): void
    {
        self::assertCount(1, $this->client->request('GET', '/admin/pages')->filter('button[hx-get="'.self::UPLOAD.'"]'), 'the section "Site" offers the upload of a project');

        $this->post(self::UPLOAD, [], [UploadController::FILE => $this->archive([
            ['path' => '/tariffs', 'title' => 'Tariffs', 'blocks' => [
                ['type' => 'hero', 'values' => ['title' => 'Fiber', 'picture' => self::PICTURE], 'style' => ['' => ['desktop' => ['color' => '#112233', 'background-image' => self::PICTURE]]]],
                ['type' => 'gone', 'values' => ['title' => 'Kept'], 'style' => []],
            ]],
            ['path' => '/contacts', 'title' => 'Contacts', 'blocks' => []],
        ])]);
        self::assertSame(self::phrase(t('builder.uploaded', ['count' => 2])), self::toast($this->client->followRedirect(), 'success'));

        $this->em->clear();
        $pages = self::service(PageRepository::class)->ordered();
        self::assertSame([['Home', '/', false], ['Tariffs', '/tariffs', false], ['Contacts', '/contacts', false]], array_map(static fn (Page $page): array => [$page->getTitle(), $page->getPath(), $page->isPublished()], $pages), 'the pages of the file come after the pages of the site unpublished');
        self::assertSame(['/', '/tariffs', '/contacts'], array_map(static fn (MenuEntry $entry): ?string => $entry->getPage()?->getPath(), self::service(MenuEntryRepository::class)->ordered()), 'the menu of the project goes to the end of the menu');

        $blocks = self::service(PageBlockRepository::class)->of($pages[1]);
        $picture = $blocks[0]->getValues()['picture'] ?? '';
        self::assertNotSame(self::PICTURE, $picture);
        self::assertNotNull(self::service(Pictures::class)->path($picture), 'the picture of a block goes to the photos of the site');
        self::assertSame([['hero', ['color' => '#112233', 'background-image' => $picture]], ['gone', []]], array_map(static fn (PageBlock $block): array => [$block->getType(), $block->getStyle()['']['desktop'] ?? []], $blocks), 'the background picture of the style goes to the photos of the site under the same name');
        self::assertSame(['title' => 'Kept'], $blocks[1]->getValues(), 'a block of a type without its module keeps its values');
    }

    /** @param list<array<string, mixed>> $pages */
    #[Group('ACMS-BLD-0019')]
    #[Group('ACMS-US-0077')]
    #[DataProvider('refusals')]
    public function testUploadRefusesFileWithoutPagesAndPictures(array $pages, int $format, string $picture, TranslatableInterface $reason): void
    {
        $this->post(self::UPLOAD, [], [UploadController::FILE => $this->archive($pages, $format, $picture)]);

        self::assertSame(self::phrase($reason), self::toast($this->client->followRedirect(), 'error'));
        self::assertCount(1, self::service(PageRepository::class)->findAll());
        self::assertSame([], self::service(Pictures::class)->listed());
    }

    /** @return iterable<string, array{list<array<string, mixed>>, int, string, TranslatableInterface}> */
    public static function refusals(): iterable
    {
        $hero = ['path' => '/tariffs', 'title' => 'Tariffs', 'blocks' => [['type' => 'hero', 'values' => ['title' => 'Fiber', 'picture' => self::PICTURE], 'style' => []]]];

        yield 'paths of a page and of a module section' => [[['path' => '/', 'title' => 'Home', 'blocks' => []], $hero, ['path' => '/admin/pages', 'title' => 'Pages', 'blocks' => []]], ProjectFile::FORMAT, self::dot(), t('builder.refusal.taken', ['paths' => '/, /admin/pages'])];
        yield 'format the module does not know' => [[$hero], ProjectFile::FORMAT + 1, self::dot(), t('builder.refusal.format', ['format' => ProjectFile::FORMAT + 1])];
        yield 'picture that is not an image' => [[$hero], ProjectFile::FORMAT, 'text', t('builder.refusal.file')];
        yield 'style out of the list' => [[['path' => '/tariffs', 'title' => 'Tariffs', 'blocks' => [['type' => 'hero', 'values' => [], 'style' => ['' => ['desktop' => ['position' => 'fixed']]]]]]], ProjectFile::FORMAT, self::dot(), t('pages.refusal.style')];
        yield 'values the type refuses' => [[['path' => '/tariffs', 'title' => 'Tariffs', 'blocks' => [['type' => 'hero', 'values' => ['title' => 'Fiber', 'picture' => self::PICTURE, 'link' => 'javascript:alert(1)'], 'style' => []]]]], ProjectFile::FORMAT, self::dot(), t('pages.refusal.link', ['field' => t('pages.field.link')])];
    }

    /** @param list<array<string, mixed>> $pages */
    private function archive(array $pages, int $format = ProjectFile::FORMAT, ?string $picture = null): UploadedFile
    {
        $file = $this->scratchFile('project.zip', '');
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file->getPathname(), \ZipArchive::OVERWRITE));
        $zip->addFromString(ProjectFile::DOCUMENT, json_encode(['format' => $format, 'pages' => $pages], \JSON_THROW_ON_ERROR));
        $zip->addFromString(ProjectFile::PICTURES.self::PICTURE, $picture ?? self::dot());
        $zip->close();

        return new UploadedFile($file->getPathname(), 'project.zip', null, null, true);
    }
}
