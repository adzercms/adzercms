<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Builder\Versions;
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
    #[Group('ACMS-US-0080')]
    public function testOperatorDecidesVersionsWaitingFirstAndAuthorLearnsDecision(): void
    {
        self::mockTime('2026-11-21 10:00');
        $decided = $this->version('Bakery');
        $decided->decide(Decision::Published);
        $this->em->flush();
        self::mockTime('2026-11-21 09:00');
        $waiting = $this->version('Fiber');
        $this->client->loginUser($this->admin());

        $listing = $this->client->request('GET', self::SECTION);
        self::assertSame(['Fiber', 'Bakery'], $listing->filter('tbody tr td:nth-child(2)')->each(static fn (Crawler $name): string => $name->text()), 'the versions waiting for a decision come first');
        self::assertSame(self::phrase(Decision::Pending->label()), trim(self::cell($listing, 'Fiber', self::phrase('core.column.status'))->text()));
        self::assertSame(['Bakery'], $this->client->request('GET', self::SECTION.'?decision=published')->filter('tbody tr td:nth-child(2)')->each(static fn (Crawler $name): string => $name->text()), 'the section filters the versions by decision');

        $record = self::SECTION.'/'.$waiting->getId();
        $this->client->request('GET', $record);
        $this->post($record.'/decide', [Moderation::DECISION => Decision::Rejected->value, Moderation::REASON => '']);
        self::assertSelectorTextContains('.modal-body', self::phrase('core.moderation.refusal.reason'), 'a rejection takes a reason');

        $this->post($record.'/decide', [Moderation::DECISION => Decision::Rejected->value, Moderation::REASON => 'No contacts']);
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

    private function version(string $name): ProjectVersion
    {
        $project = new Project($this->author, $name);
        $project->setAccess(ProjectAccess::Link);
        $home = new ProjectPage($project, $name, '/');
        $home->place(1);
        $this->em->persist($project);
        $this->em->persist($home);
        $this->em->flush();
        $version = self::service(Versions::class)->submit($project, $name, $this->picture('cover.png'));
        self::assertInstanceOf(ProjectVersion::class, $version);

        return $version;
    }
}
