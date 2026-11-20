<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Projects;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Core\Account\UserRemoval;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Translation\t;

final class ProjectsTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const SECTION = '/builder/projects';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->record('79990000002');
        $this->client->loginUser($this->user);
    }

    #[Group('ACMS-BLD-0011')]
    #[Group('ACMS-BLD-0023')]
    #[Group('ACMS-US-0078')]
    public function testUserKeepsProjectsOfPagesInOrderAndDeletesThem(): void
    {
        $this->post(self::SECTION.'/new', [Projects::NAME => 'Fiber']);
        $project = self::service(ProjectRepository::class)->findOneBy(['owner' => $this->user]);
        self::assertInstanceOf(Project::class, $project);
        self::assertSame(self::SECTION.'/'.$project->getId(), $this->client->getResponse()->headers->get('HX-Redirect'), 'a new project opens');
        self::assertSame([['Fiber', '/']], $this->pages($project), 'a new project starts with its home page');
        self::assertSame(['Fiber'], self::firstCells($this->client->request('GET', self::SECTION)), 'the section lists the projects of the user');

        $base = self::SECTION.'/'.$project->getId();
        $this->post($base.'/pages/new', [Projects::TITLE => 'Tariffs', Projects::PATH => 'tariffs']);
        $this->post($base.'/pages/new', [Projects::TITLE => 'Again', Projects::PATH => '/tariffs']);
        self::assertSelectorTextContains('.modal-body', self::phrase(t('pages.refusal.path_page', ['path' => '/tariffs', 'title' => 'Tariffs'])), 'a path of another page of the project is refused');
        $this->post($base.'/pages/new', [Projects::TITLE => 'Contacts', Projects::PATH => 'Contacts Us']);
        self::assertSelectorTextContains('.modal-body', self::phrase(t('pages.refusal.path')));
        $this->post($base.'/pages/new', [Projects::TITLE => 'Contacts', Projects::PATH => '/contacts']);
        self::assertSame([['Fiber', '/'], ['Tariffs', '/tariffs'], ['Contacts', '/contacts']], $this->pages($project));

        $ids = array_map(static fn (ProjectPage $page): int => (int) $page->getId(), self::service(ProjectPageRepository::class)->of($project));
        $this->post($base.'/order', [Order::FIELD => [$ids[2], $ids[0], $ids[1]]]);
        self::assertSame([['Contacts', '/contacts'], ['Fiber', '/'], ['Tariffs', '/tariffs']], $this->pages($project), 'the order of the pages is the order of the menu');

        $this->post($base.'/pages/'.$ids[1].'/drop');
        self::assertSame([['Contacts', '/contacts'], ['Fiber', '/']], $this->pages($project));

        $other = $this->record('79990000003');
        $this->client->loginUser($other);
        $this->client->request('GET', $base);
        self::assertResponseStatusCodeSame(404, 'a project of another user is not open');
        $this->post($base.'/drop');
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->user);
        $this->post($base.'/drop');
        self::assertResponseRedirects(self::SECTION);
        self::assertSame([], self::service(ProjectRepository::class)->findBy(['owner' => $this->user]), 'a deleted project leaves with its pages');
        self::assertSame([], self::service(ProjectPageRepository::class)->findAll());
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function limits(): iterable
    {
        yield 'projects of a user' => ['project', Projects::PROJECTS, 'builder.refusal.projects'];
        yield 'pages of a project' => ['page', Projects::PAGES, 'builder.refusal.pages'];
    }

    #[Group('ACMS-BLD-0020')]
    #[Group('ACMS-US-0078')]
    #[DataProvider('limits')]
    public function testLimitRefusesOneMoreAndNamesItself(string $kind, int $limit, string $reason): void
    {
        $project = new Project($this->user, 'Fiber');
        $this->em->persist($project);
        for ($at = 1; $at <= $limit; ++$at) {
            $this->em->persist('project' === $kind ? new Project($this->user, 'Project '.$at) : new ProjectPage($project, 'Page '.$at, '/page-'.$at));
        }
        $this->em->flush();

        'project' === $kind
            ? $this->post(self::SECTION.'/new', [Projects::NAME => 'One more'])
            : $this->post(self::SECTION.'/'.$project->getId().'/pages/new', [Projects::TITLE => 'One more', Projects::PATH => '/one-more']);

        self::assertSelectorTextContains('.modal-body', self::phrase(t($reason, ['limit' => $limit])));
    }

    #[Group('ACMS-BLD-0021')]
    #[Group('ACMS-US-0078')]
    public function testRemovalOfUserTakesHisProjects(): void
    {
        $project = new Project($this->user, 'Fiber');
        $this->em->persist($project);
        $this->em->persist(new ProjectPage($project, 'Fiber', '/'));
        $this->em->flush();

        self::service(UserRemoval::class)->remove($this->user);
        $this->em->clear();

        self::assertSame([], self::service(ProjectRepository::class)->findAll());
        self::assertSame([], self::service(ProjectPageRepository::class)->findAll());
    }

    /** @return list<array{string, string}> */
    private function pages(Project $project): array
    {
        $this->em->clear();

        return array_map(static fn (ProjectPage $page): array => [$page->getTitle(), $page->getPath()], self::service(ProjectPageRepository::class)->of($this->em->find(Project::class, $project->getId()) ?? $project));
    }
}
