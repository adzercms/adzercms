<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectBlock;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Builder\ProjectAlbums;
use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Builder\Versions;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class ShowTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private User $author;
    private Project $project;
    private ProjectPage $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = $this->record('79990000002', 'author@example.org');
        $this->project = new Project($this->author, 'Fiber');
        $this->project->setAccess(ProjectAccess::Link);
        $this->home = new ProjectPage($this->project, 'Fiber', '/');
        $this->home->place(1);
        $about = new ProjectPage($this->project, 'About', '/about');
        $about->place(2);
        $this->em->persist($this->project);
        $this->em->persist($this->home);
        $this->em->persist($about);
        $this->em->flush();
        $picture = self::service(ProjectAlbums::class)->of($this->project)->store($this->picture('hero.png'));
        $this->em->persist(new ProjectBlock($this->home, 'hero', ['title' => 'Fiber', 'picture' => $picture]));
        $this->em->persist(new ProjectBlock($about, 'text', ['title' => 'Our team', 'text' => 'Fiber since 2020']));
        $this->em->flush();
        $this->client->disableReboot();
    }

    #[Group('ACMS-BLD-0014')]
    #[Group('ACMS-BLD-0013')]
    #[Group('ACMS-US-0079')]
    #[Group('ACMS-US-0078')]
    public function testVisitorSeesApprovedVersionByLinkAsSiteWithItsMenu(): void
    {
        $this->version(Decision::Published);
        $show = '/templates/'.$this->project->getToken();

        $page = $this->client->request('GET', $show);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow', 'search engines do not index the show');
        self::assertSame('Fiber', trim($page->filter('.site-main h1')->text()));
        self::assertSame([['Fiber', $show, 'page'], ['About', $show.'/about', null]], $page->filter('.site-nav a')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href'), $link->attr('aria-current')]), 'the menu is the pages of the version');
        $this->client->request('GET', (string) $page->filter('.site-main img')->attr('src'));
        self::assertResponseHeaderSame('Content-Type', 'image/webp', 'the pictures of the show go to a visitor');

        self::assertStringContainsString('Fiber since 2020', $this->client->request('GET', $show.'/about')->filter('.site-main')->text());
        $this->client->request('GET', $show.'/team');
        self::assertResponseStatusCodeSame(404);

        [$hero] = self::service(ProjectBlockRepository::class)->of($this->home);
        $hero->fill(['title' => 'Fiber shop'] + $hero->getValues());
        $this->em->flush();
        $this->version(Decision::Pending);
        self::assertSame('Fiber', trim($this->client->request('GET', $show)->filter('.site-main h1')->text()), 'editing the project and a new version leave the approved one in the show');

        $this->client->loginUser($this->author);
        self::assertSame($show, $this->client->request('GET', '/builder/projects/'.$this->project->getId())->filter('[data-show]')->attr('href'), 'the owner opens the show from the project');
    }

    #[Group('ACMS-BLD-0014')]
    #[Group('ACMS-BLD-0013')]
    #[Group('ACMS-BLD-0012')]
    #[Group('ACMS-US-0078')]
    #[DataProvider('hidden')]
    public function testShowGoesOnlyWithApprovedVersionOfProjectBeyondOwner(Decision $last, ProjectAccess $access): void
    {
        $this->version(Decision::Published);
        $version = $this->version($last);
        $this->project->setAccess($access);
        $this->em->flush();

        $this->client->request('GET', '/templates/'.$this->project->getToken());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/builder/versions/'.$version->getId().'/pictures/'.$version->getCover());
        self::assertResponseStatusCodeSame(404, 'the pictures of a version out of the show stay with the owner');
    }

    /** @return iterable<string, array{Decision, ProjectAccess}> */
    public static function hidden(): iterable
    {
        yield 'the operator withdrew the latest approved version' => [Decision::Withdrawn, ProjectAccess::Open];
        yield 'the project went back to its owner alone' => [Decision::Published, ProjectAccess::Personal];
    }

    private function version(Decision $decision): ProjectVersion
    {
        $this->project = $this->reread($this->project);
        $version = self::service(Versions::class)->submit($this->project, 'Fiber', $this->picture('cover.png'));
        self::assertInstanceOf(ProjectVersion::class, $version);
        $version->decide($decision);
        $this->em->flush();

        return $version;
    }
}
