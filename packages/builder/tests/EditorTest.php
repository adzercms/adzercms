<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Pictures;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Translation\t;

final class EditorTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
    }

    #[Group('ACMS-BLD-0001')]
    #[Group('ACMS-BLD-0009')]
    #[Group('ACMS-US-0077')]
    public function testOwnerOpensPageInEditorAsVisitorSeesIt(): void
    {
        [$page, $text, $retired] = $this->page(['text' => ['title' => 'About us', 'text' => 'We build networks'], 'retired' => ['title' => 'Gone']]);

        $record = $this->client->request('GET', '/admin/pages/'.$page->getId());
        $editor = $this->client->click($record->selectLink(self::phrase(t('builder.action')))->link());
        self::assertResponseIsSuccessful();
        $builder = $editor->filter('[data-builder]');
        self::assertSame([
            ['id' => $text->getId(), 'anchor' => 'block-'.$text->getId(), 'type' => 'text', 'label' => self::phrase(t('pages.block.text')), 'available' => true, 'question' => self::phrase(t('pages.block.drop_confirm', ['type' => self::phrase(t('pages.block.text'))]))],
            ['id' => $retired->getId(), 'anchor' => 'block-'.$retired->getId(), 'type' => 'retired', 'label' => 'retired', 'available' => false, 'question' => self::phrase(t('pages.block.drop_confirm', ['type' => 'retired']))],
        ], json_decode((string) $builder->attr('data-blocks'), true), 'the editor takes the blocks of the page in order, a block of a removed module too');

        $frame = $this->client->request('GET', (string) $builder->attr('data-frame'));
        self::assertResponseIsSuccessful();
        self::assertSame('About us', trim($frame->filter('.site-main #block-'.$text->getId().' h1')->text()), 'the canvas is the page on the site frame as the visitor sees it');
        self::assertCount(1, $frame->filter('#block-'.$text->getId().' + template[data-builder-end]'), 'the end of the blocks marks the place of new ones');
    }

    #[Group('ACMS-BLD-0002')]
    #[Group('ACMS-PAGE-0012')]
    #[Group('ACMS-US-0077')]
    public function testOwnerSavesOrderAndRemovalOfBlocksWithTheDocumentWhole(): void
    {
        $pictures = self::service(Pictures::class);
        $picture = $pictures->store($this->picture('hero.png'));
        [$page, $text, $hero, $retired] = $this->page(['text' => ['title' => 'About us', 'text' => 'We build networks'], 'hero' => ['title' => 'Internet', 'picture' => $picture], 'retired' => ['title' => 'Gone']]);
        $save = '/admin/pages/'.$page->getId().'/builder';
        $known = [$text->getId(), $hero->getId(), $retired->getId()];

        $this->post($save, ['document' => json_encode(['known' => $known, 'blocks' => [['id' => $retired->getId()], ['id' => $text->getId()]]])]);
        self::assertResponseIsSuccessful();
        self::assertSame(['message' => self::phrase(t('builder.saved'))], json_decode((string) $this->client->getResponse()->getContent(), true));
        $stored = self::service(PageBlockRepository::class)->findBy(['page' => $page->getId()], ['position' => 'ASC']);
        self::assertSame(['retired', 'text'], array_map(static fn (PageBlock $block): string => $block->getType(), $stored), 'the document goes whole: its order, without the removed block');
        self::assertNull($pictures->path($picture), 'a removed block takes its picture');

        $this->post($save, ['document' => json_encode(['known' => $known, 'blocks' => [['id' => $text->getId()], ['id' => $retired->getId()]]])]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('builder.refusal.document'))], json_decode((string) $this->client->getResponse()->getContent(), true), 'a page changed after the editor opened is not overwritten');
        self::assertSame(['retired', 'text'], array_map(static fn (PageBlock $block): string => $block->getType(), self::service(PageBlockRepository::class)->findBy(['page' => $page->getId()], ['position' => 'ASC'])));
    }

    /**
     * @param array<string, array<string, string>> $blocks
     *
     * @return non-empty-list<Page|PageBlock>
     */
    private function page(array $blocks): array
    {
        $page = new Page('Tariffs', '/tariffs');
        $page->edit('Tariffs', '/tariffs', null, true, null);
        $this->em->persist($page);
        $placed = [$page];
        foreach ($blocks as $type => $values) {
            $block = new PageBlock($page, $type, $values);
            $block->place(\count($placed));
            $this->em->persist($block);
            $placed[] = $block;
        }
        $this->em->flush();

        return $placed;
    }
}
