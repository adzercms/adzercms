<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectBlock;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Builder\ProjectAlbums;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Builder\Versions;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Translation\t;

final class VersionsTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const SECTION = '/builder/projects';

    private User $user;
    private Project $project;
    private string $held;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->record('79990000002', 'author@example.org');
        $this->project = new Project($this->user, 'Fiber');
        $this->project->setAccess(ProjectAccess::Link);
        $home = new ProjectPage($this->project, 'Fiber', '/');
        $home->place(1);
        $this->em->persist($this->project);
        $this->em->persist($home);
        $this->em->flush();
        $this->held = self::service(ProjectAlbums::class)->of($this->project)->store($this->picture('held.png'));
        $this->em->persist(new ProjectBlock($home, 'hero', ['title' => 'Fiber', 'picture' => $this->held]));
        $this->em->flush();
        $this->client->disableReboot();
        $this->client->loginUser($this->user);
    }

    #[Group('ACMS-BLD-0013')]
    #[Group('ACMS-US-0078')]
    public function testOwnerSubmitsSnapshotOfProjectWithNameAndCover(): void
    {
        $base = self::SECTION.'/'.$this->project->getId();
        $this->post($base.'/version', [Versions::NAME => 'Fiber']);
        self::assertSelectorTextContains('.modal-body', self::phrase(t('builder.refusal.cover')), 'the first version takes a cover');

        $this->post($base.'/version', [Versions::NAME => ' Fiber shop '], [Versions::COVER => $this->picture('cover.png')]);
        self::assertResponseHeaderSame('HX-Redirect', $base);
        [$version] = self::service(ProjectVersionRepository::class)->findAll();
        self::assertSame(['Fiber shop', 'Fiber shop', Decision::Pending], [$this->reread($this->project)->getName(), $version->getName(), $version->getDecision()]);
        $picture = $version->getPages()[0]['blocks'][0]['values']['picture'];
        self::assertSame([['path' => '/', 'title' => 'Fiber', 'blocks' => [['type' => 'hero', 'values' => ['title' => 'Fiber', 'picture' => $picture], 'style' => []]]]], $version->getPages(), 'the version is a snapshot of the document');
        $album = self::service(ProjectAlbums::class)->ofVersion($version);
        self::assertNotSame($this->held, $picture);
        self::assertNotNull($album->path($picture), 'the version keeps its own copy of the pictures');
        self::assertNotNull($album->path($version->getCover()));
        $shown = $this->client->request('GET', $base)->filter('[data-version]');
        self::assertStringContainsString(self::phrase(Decision::Pending->label()), $shown->text());
        self::assertStringContainsString(self::phrase(Decision::Pending->label()), $this->client->request('GET', self::SECTION)->filter('tbody tr')->text(), 'the section shows the decision on the version');
        $cover = (string) $shown->filter('img')->attr('src');
        $this->client->request('GET', $cover);
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        $this->client->loginUser($this->record('79990000003'));
        $this->client->request('GET', $cover);
        self::assertResponseStatusCodeSame(404, 'the pictures of a version go to the owner of the project');
        $this->client->loginUser($this->user);

        $this->post($base.'/version', [Versions::NAME => 'Fiber shop']);
        $versions = self::service(ProjectVersionRepository::class)->findAll();
        self::assertCount(1, $versions, 'a new version replaces the waiting one');
        self::assertNull($album->path($picture), 'the replaced version takes its pictures');
        self::assertNotNull(self::service(ProjectAlbums::class)->ofVersion($versions[0])->path($versions[0]->getCover()), 'the cover of the project serves the next version');

        $this->post($base.'/drop');
        self::assertSame([], self::service(ProjectVersionRepository::class)->findAll());
        self::assertNull(self::service(ProjectAlbums::class)->ofVersion($versions[0])->path($versions[0]->getCover()), 'a deleted project takes its versions with their pictures');
    }

    #[Group('ACMS-BLD-0013')]
    #[Group('ACMS-US-0078')]
    public function testProjectOfOwnerAloneIsNotSubmitted(): void
    {
        $this->project->setAccess(ProjectAccess::Personal);
        $this->em->flush();

        $this->post(self::SECTION.'/'.$this->project->getId().'/version', [Versions::NAME => 'Fiber'], [Versions::COVER => $this->picture('cover.png')]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], self::service(ProjectVersionRepository::class)->findAll());
    }
}
