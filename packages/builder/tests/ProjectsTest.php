<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectBlock;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\ProjectAlbums;
use AdzerCMS\Builder\Projects;
use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Core\Account\UserRemoval;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Pictures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;

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
        self::assertSame([['Fiber', '/']], $this->pages($project), 'a new project starts with its home page');
        $base = self::SECTION.'/'.$project->getId();
        $editor = $base.'/pages/'.self::service(ProjectPageRepository::class)->of($project)[0]->getId().'/editor';
        self::assertSame($editor, $this->client->getResponse()->headers->get('HX-Redirect'), 'a new project opens its home page in the editor');
        self::assertSame([$editor], $this->client->request('GET', $base)->filter('tr[data-href]')->each(static fn (Crawler $row): ?string => $row->attr('data-href')), 'a page of the project opens in the editor');
        self::assertSame(['Fiber'], self::firstCells($this->client->request('GET', self::SECTION)), 'the section lists the projects of the user');

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
        $album = self::service(ProjectAlbums::class)->of($project);
        $picture = $album->store($this->picture('fiber.png'));
        $this->post($base.'/drop');
        self::assertResponseRedirects(self::SECTION);
        self::assertSame([], self::service(ProjectRepository::class)->findBy(['owner' => $this->user]), 'a deleted project leaves with its pages');
        self::assertSame([], self::service(ProjectPageRepository::class)->findAll());
        self::assertNull($album->path($picture), 'a deleted project takes its pictures');
    }

    #[Group('ACMS-BLD-0002')]
    #[Group('ACMS-BLD-0008')]
    #[Group('ACMS-BLD-0023')]
    #[Group('ACMS-US-0078')]
    public function testOwnerBuildsPageOfProjectInEditorWithPicturesOfProject(): void
    {
        [$project, $home] = $this->project();
        $base = self::SECTION.'/'.$project->getId();
        $editor = $base.'/pages/'.$home->getId().'/editor';
        $builder = $this->client->request('GET', $editor)->filter('[data-builder]');
        self::assertResponseIsSuccessful();
        self::assertSame($base, $builder->filter('[data-exit]')->attr('href'), 'the editor leaves to the pages of the project');

        $this->post((string) $builder->attr('data-upload'), [], ['files' => [$this->picture('fiber.png')]]);
        [$entry] = json_decode((string) $this->client->getResponse()->getContent(), true)['data'];
        $this->client->request('GET', $entry['src']);
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        $hero = ['id' => null, 'type' => 'hero', 'values' => ['title' => 'Fiber', 'picture' => self::service(Pictures::class)->store($this->picture('site.png'))], 'style' => []];
        $this->post($editor, ['document' => json_encode(['version' => $builder->attr('data-version'), 'blocks' => [$hero]])]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('pages.refusal.picture', ['field' => self::phrase(t('pages.field.picture'))]))], json_decode((string) $this->client->getResponse()->getContent(), true), 'a block takes the pictures of its project only');

        $hero['values']['picture'] = $entry['name'];
        $this->post($editor, ['document' => json_encode(['version' => $builder->attr('data-version'), 'blocks' => [$hero]])]);
        self::assertResponseIsSuccessful();
        [$block] = self::service(ProjectBlockRepository::class)->of($home);
        self::assertSame(['hero', $hero['values']], [$block->getType(), $block->getValues()], 'saving keeps the blocks of the page of the project');

        $frame = $this->client->request('GET', (string) $builder->attr('data-frame'));
        self::assertSame(['Fiber', $entry['src']], [trim($frame->filter('.site-main #block-'.$block->getId().' h1')->text()), $frame->filter('#block-'.$block->getId().' img')->attr('src')], 'the canvas draws the page of the project with its pictures on the site frame');

        $this->client->loginUser($this->record('79990000003'));
        $this->client->request('GET', $editor);
        self::assertResponseStatusCodeSame(404, 'a page of another user is not open in the editor');
        $this->client->request('GET', $entry['src']);
        self::assertResponseStatusCodeSame(404, 'the pictures of a project go to its owner only');
    }

    #[Group('ACMS-BLD-0020')]
    #[Group('ACMS-US-0078')]
    public function testEditorRefusesPicturesAndSizeOverLimitsOfProject(): void
    {
        [$project, $home] = $this->project();
        $other = new ProjectPage($project, 'Tariffs', '/tariffs');
        $this->em->persist($other);
        $this->em->persist(new ProjectBlock($other, 'text', ['title' => 'Tariffs', 'text' => str_repeat('a', Projects::SIZE * 1048576)]));
        $this->em->flush();
        $files = new Filesystem();
        for ($at = 1; $at <= Projects::PICTURES; ++$at) {
            $files->dumpFile(self::parameter('adzercms.files_dir').'/builder/'.$project->getId().'/'.bin2hex(random_bytes(16)).'.webp', '');
        }
        $editor = self::SECTION.'/'.$project->getId().'/pages/'.$home->getId().'/editor';
        $builder = $this->client->request('GET', $editor)->filter('[data-builder]');

        $this->post((string) $builder->attr('data-upload'), [], ['files' => [$this->picture('fiber.png')]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('builder.refusal.pictures', ['limit' => Projects::PICTURES]))], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->post($editor, ['document' => json_encode(['version' => $builder->attr('data-version'), 'blocks' => []])]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('builder.refusal.size', ['megabytes' => Projects::SIZE]))], json_decode((string) $this->client->getResponse()->getContent(), true), 'the size counts all the pages of the project');
    }

    #[Group('ACMS-BLD-0008')]
    public function testPictureNoBlockOfProjectHoldsLeavesAfterTheTerm(): void
    {
        [, $home] = $this->project();
        $album = self::service(ProjectAlbums::class)->of($home->getProject());
        [$held, $stale, $fresh] = array_map(fn (string $name): string => $album->store($this->picture($name)), ['held.png', 'stale.png', 'fresh.png']);
        $this->em->persist(new ProjectBlock($home, 'hero', ['title' => 'Fiber', 'picture' => $held]));
        $this->em->flush();
        foreach ([$held, $stale] as $name) {
            touch((string) $album->path($name), (int) strtotime('-2 days'));
        }

        $sweep = self::command('adzercms:builder');
        $sweep->execute([]);

        self::assertSame(Command::SUCCESS, $sweep->getStatusCode());
        self::assertNull($album->path($stale), 'a picture no block of the project holds leaves after the term');
        self::assertNotNull($album->path($held));
        self::assertNotNull($album->path($fresh), 'an upload within the term waits for the document it goes to');
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
        [$project] = $this->project();
        $album = self::service(ProjectAlbums::class)->of($project);
        $picture = $album->store($this->picture('fiber.png'));

        self::service(UserRemoval::class)->remove($this->user);
        $this->em->clear();

        self::assertSame([], self::service(ProjectRepository::class)->findAll());
        self::assertSame([], self::service(ProjectPageRepository::class)->findAll());
        self::assertNull($album->path($picture), 'the projects leave with their pictures');
    }

    /** @return array{Project, ProjectPage} */
    private function project(): array
    {
        $project = new Project($this->user, 'Fiber');
        $home = new ProjectPage($project, 'Fiber', '/');
        $home->place(1);
        $this->em->persist($project);
        $this->em->persist($home);
        $this->em->flush();

        return [$project, $home];
    }

    /** @return list<array{string, string}> */
    private function pages(Project $project): array
    {
        $this->em->clear();

        return array_map(static fn (ProjectPage $page): array => [$page->getTitle(), $page->getPath()], self::service(ProjectPageRepository::class)->of($this->em->find(Project::class, $project->getId()) ?? $project));
    }
}
