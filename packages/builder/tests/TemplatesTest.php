<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Core\Moderation\Moderation;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class TemplatesTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use Delivery;
    use Templates;

    private const SECTION = '/admin/builder/templates';

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure(self::MAIL);
        $this->author = $this->record('79990000002', 'author@example.org');
        $this->client->disableReboot();
    }

    #[Group('ACMS-BLD-0022')]
    #[Group('ACMS-BLD-0013')]
    #[Group('ACMS-BLD-0014')]
    #[Group('ACMS-CORE-1198')]
    #[Group('ACMS-US-0080')]
    public function testOperatorDecidesVersionsWaitingFirstAndAuthorLearnsDecision(): void
    {
        self::mockTime('2026-11-21 10:00');
        $this->version($this->home($this->author, 'Bakery', ProjectAccess::Link)->getProject(), Decision::Published);
        self::mockTime('2026-11-21 09:00');
        $waiting = $this->version($this->home($this->author, 'Fiber', ProjectAccess::Link)->getProject(), Decision::Pending);
        $this->client->loginUser($this->admin());

        $listing = $this->client->request('GET', self::SECTION);
        self::assertSame(['Fiber', 'Bakery'], $listing->filter('tbody tr td:nth-child(2)')->each(static fn (Crawler $name): string => $name->text()), 'the versions waiting for a decision come first');
        self::assertSame(self::phrase(Decision::Pending->label()), trim(self::cell($listing, 'Fiber', self::phrase('core.column.status'))->text()));
        self::assertSame(['Bakery'], $this->client->request('GET', self::SECTION.'?decision=published')->filter('tbody tr td:nth-child(2)')->each(static fn (Crawler $name): string => $name->text()), 'the section filters the versions by decision');

        $record = self::SECTION.'/'.$waiting->getId();
        $pages = (string) $this->client->request('GET', $record)->filter('[data-pages]')->attr('href');
        $page = $this->client->request('GET', $pages);
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame([['Fiber', $pages]], $page->filter('.site-nav a')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href')]), 'the operator sees the pages of a version waiting for a decision');
        $decide = $this->client->request('GET', $record)->filter('form[hx-post$="/decide"]');
        self::assertCount(1, $decide, 'the decisions go by one form');
        self::assertSame(self::phrase(Decision::Rejected->reason()), trim($decide->filter('label')->text()), 'the field of the form is the reason of the rejection');
        self::assertCount(1, $decide->filter('button[value="'.Decision::Published->value.'"][formnovalidate]'), 'the publication goes past the reason');
        $reject = static fn (string $reason): array => array_replace($decide->selectButton(self::phrase(Decision::Rejected->action()))->form()->getPhpValues(), [Moderation::REASON => $reason]);

        $this->client->request('POST', (string) $decide->attr('hx-post'), $reject(''));
        self::assertSelectorTextContains('.modal-body', self::phrase('core.moderation.refusal.reason'), 'a rejection takes a reason');

        $this->client->request('POST', (string) $decide->attr('hx-post'), $reject('No contacts'));
        self::assertResponseHeaderSame('HX-Redirect', self::SECTION);
        self::assertSame(Decision::Rejected, $this->reread($waiting)->getDecision());
        self::assertSame(['author@example.org'], array_map(static fn ($address): string => $address->getAddress(), $this->letter()->getTo()), 'the author learns the decision');
        self::assertStringContainsString(self::phrase(Decision::Rejected->label()), $this->letterText());
        self::assertStringContainsString('No contacts', $this->letterText());

        $this->client->request('GET', $this->client->request('GET', $record)->filter('.info-value img')->attr('src') ?? '');
        self::assertResponseHeaderSame('Content-Type', 'image/webp', 'the operator sees the cover of a version');

        $this->client->loginUser($this->author);
        self::assertStringContainsString('No contacts', $this->client->request('GET', '/builder/projects/'.$waiting->getProject()->getId())->filter('[data-version]')->text(), 'the project shows the reason of the decision');
    }
}
