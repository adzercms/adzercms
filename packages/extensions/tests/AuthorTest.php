<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Extensions\Entity\ExtensionAuthor;
use AdzerCMS\Extensions\ExtensionDecision;
use AdzerCMS\Extensions\ExtensionState;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class AuthorTest extends CatalogTestCase
{
    private const SECTION = '/extensions/mine';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->record('79990000002');
        $this->client->loginUser($this->user);
    }

    #[Group('ACMS-EXT-0010')]
    #[Group('ACMS-US-0044')]
    public function testAuthorSeesOwnKeyAndEntriesWithDecisionAndReason(): void
    {
        $page = $this->arrive(self::SECTION);
        $key = $this->key();
        self::assertSame('"extra": {"adzercms": {"author": "'.$key.'"}}', $page->filter('#author-key')->attr('value'));

        $this->entry('acme/mine', author: $this->em->find(ExtensionAuthor::class, $this->user->getId()))->decide(ExtensionDecision::Rejected, 'No demo site');
        $this->entry('acme/foreign', author: $this->author('79990000003'));
        $this->em->flush();

        $page = $this->arrive(self::SECTION);
        self::assertSame($key, $this->key(), 'the key is issued once');
        self::assertSame(['acme/mine'], $page->filter('tbody tr strong')->each(static fn (Crawler $name): string => $name->text()));
        $state = self::cell($page, 'acme/mine', self::phrase('extensions.column.state'));
        self::assertSame([self::phrase(ExtensionState::Rejected->label()), 'No demo site'], [$state->filter('.badge')->text(), $state->filter('.muted')->text()]);
    }

    private function key(): string
    {
        $this->em->clear();

        return ($this->em->find(ExtensionAuthor::class, $this->user->getId()) ?? self::fail('the author key is not issued'))->getKey();
    }
}
