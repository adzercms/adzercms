<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Extensions\Entity\ExtensionAuthor;
use AdzerCMS\Extensions\ExtensionDecision;
use AdzerCMS\Extensions\ExtensionState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class AuthorTest extends CatalogTestCase
{
    use Packages;

    private const SECTION = '/extensions/mine';
    private const NAME = 'acme/shop';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serve();
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

        $this->entry('acme/mine', author: $this->mine())->decide(ExtensionDecision::Rejected, 'No demo site');
        $this->entry('acme/foreign', author: $this->author('79990000003'));
        $this->em->flush();

        $page = $this->arrive(self::SECTION);
        self::assertSame($key, $this->key(), 'the key is issued once');
        self::assertSame(['acme/mine'], $page->filter('tbody tr strong')->each(static fn (Crawler $name): string => $name->text()));
        $state = self::cell($page, 'acme/mine', self::phrase('extensions.column.state'));
        self::assertSame([self::phrase(ExtensionState::Rejected->label()), 'No demo site'], [$state->filter('.badge')->text(), $state->filter('.muted')->text()]);
    }

    #[Group('ACMS-EXT-0012')]
    #[Group('ACMS-US-0044')]
    public function testOwnEntriesSortByState(): void
    {
        $this->arrive(self::SECTION);
        $mine = $this->mine();
        $this->entry('acme/alpha', author: $mine)->decide(ExtensionDecision::Rejected, 'No demo site');
        $this->entry('acme/zeta', author: $mine);
        $this->em->flush();
        $sorted = fn (string $order): array => $this->arrive(self::SECTION.'?sort=state&order='.$order)
            ->filter('tbody tr strong')->each(static fn (Crawler $name): string => $name->text());

        self::assertSame(['acme/zeta', 'acme/alpha'], $sorted('asc'), 'a pending entry goes before a rejected one');
        self::assertSame(['acme/alpha', 'acme/zeta'], $sorted('desc'));
    }

    #[Group('ACMS-EXT-0009')]
    #[Group('ACMS-EXT-0010')]
    #[Group('ACMS-US-0044')]
    public function testSubmittedEntryWithKeyAwaitsDecisionOffSite(): void
    {
        $this->arrive(self::SECTION);
        $this->open[self::NAME] = [self::version(self::NAME, '1.2.0', 'Shop', author: $this->key())];

        $this->post(self::SECTION.'/new', ['name' => self::NAME, 'type' => 'theme', 'demo' => 'https://demo.example.test', 'price' => '900']);

        self::assertResponseHeaderSame('HX-Redirect', self::SECTION);
        $entry = self::extensions()->named(self::NAME) ?? self::fail('the entry is not in the catalog');
        self::assertSame([$this->user->getId(), ExtensionDecision::Pending, ExtensionType::Theme, '1.2.0', 'https://demo.example.test', null],
            [$entry->getAuthor()?->getUser()->getId(), $entry->getDecision(), $entry->getType(), $entry->getVersion(), $entry->getDemo(), $entry->getPrice()]);
        self::assertSame([], $this->arrive('/extensions')->filter('.offer-title')->each(static fn (Crawler $title): string => $title->text()));
    }

    #[Group('ACMS-EXT-0010')]
    #[Group('ACMS-US-0044')]
    #[DataProvider('unproven')]
    public function testSubmissionWithoutProvenKeyIsRefused(bool $down, string $reason): void
    {
        $this->arrive(self::SECTION);
        $key = $this->key();
        $this->open[self::NAME] = [self::version(self::NAME, '1.0.0', 'Shop')];
        $this->down = $down;

        $dialog = $this->post(self::SECTION.'/new', ['name' => self::NAME, 'type' => 'module', 'demo' => '']);

        self::assertSame(self::phrase($reason, ['name' => self::NAME, 'key' => $key]), trim($dialog->filter('.flash-error')->text()));
        self::assertNull(self::extensions()->named(self::NAME));
    }

    /** @return iterable<string, array{bool, string}> */
    public static function unproven(): iterable
    {
        yield 'metadata without the key' => [false, 'extensions.refusal.key'];
        yield 'repository down' => [true, 'extensions.refusal.unavailable'];
    }

    #[Group('ACMS-CORE-0931')]
    #[Group('ACMS-EXT-0003')]
    #[Group('ACMS-US-0044')]
    #[DataProvider('priced')]
    public function testAuthorEntryTakesPriceOfItsOpotopoCard(string $card, ?string $price): void
    {
        $this->arrive(self::SECTION);
        $this->paid[self::NAME] = [self::version(self::NAME, '1.0.0', 'Shop', author: $this->key(), price: $card)];

        $this->post(self::SECTION.'/new', ['name' => self::NAME, 'type' => 'module', 'demo' => '', 'price' => '900']);

        self::assertSame($price, (self::extensions()->named(self::NAME) ?? self::fail('the entry is not in the catalog'))->getPrice());
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function priced(): iterable
    {
        yield 'paid' => ['1500', '1500.00'];
        yield 'free variant' => ['0', null];
        yield 'beyond the price column' => ['100000000.00', null];
    }

    #[Group('ACMS-EXT-0009')]
    #[Group('ACMS-US-0044')]
    public function testEditOfRejectedEntryReturnsItToDecisionWhilePublishedStays(): void
    {
        $this->arrive(self::SECTION);
        $author = $this->mine();
        $this->open['acme/rejected'] = [self::version('acme/rejected', '1.0.0', 'Rejected', author: $author->getKey())];
        $this->open['acme/published'] = [self::version('acme/published', '1.0.0', 'Published', author: $author->getKey())];
        $rejected = $this->entry('acme/rejected', author: $author);
        $rejected->decide(ExtensionDecision::Rejected, 'No demo site');
        $published = $this->entry('acme/published', author: $author);
        $published->decide(ExtensionDecision::Published);
        $this->em->flush();

        $this->post(self::SECTION.'/'.$rejected->getId(), ['name' => 'acme/rejected', 'type' => 'module', 'demo' => 'https://demo.example.test']);
        $this->post(self::SECTION.'/'.$published->getId(), ['name' => 'acme/published', 'type' => 'theme', 'demo' => '']);

        $rejected = $this->reread($rejected);
        $published = $this->reread($published);
        self::assertSame([ExtensionDecision::Pending, null, 'https://demo.example.test'], [$rejected->getDecision(), $rejected->getReason(), $rejected->getDemo()]);
        self::assertSame([ExtensionDecision::Published, ExtensionType::Theme], [$published->getDecision(), $published->getType()]);
    }

    #[Group('ACMS-US-0044')]
    public function testAuthorReachesOnlyOwnEntries(): void
    {
        $foreign = $this->entry('acme/foreign', author: $this->author('79990000003'));

        $this->client->request('GET', self::SECTION.'/'.$foreign->getId());

        self::assertResponseStatusCodeSame(404);
    }

    private function key(): string
    {
        return $this->mine()->getKey();
    }

    private function mine(): ExtensionAuthor
    {
        $this->em->clear();

        return $this->em->find(ExtensionAuthor::class, $this->user->getId()) ?? self::fail('the author key is not issued');
    }
}
