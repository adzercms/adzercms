<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Builder\ProjectFile;
use AdzerCMS\Builder\Projects;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Translation\t;

final class CopyTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Templates;

    private const VISITOR = 'visitor@example.org';

    private User $author;
    private User $visitor;
    private Project $project;
    private ProjectVersion $version;
    private string $show;
    private string $copy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = $this->record('79990000002', 'author@example.org');
        $this->visitor = $this->record('79990000003', self::VISITOR);
        $this->project = $this->site($this->author, ProjectAccess::Open)->getProject();
        $this->version = $this->version($this->project, Decision::Published);
        $this->show = '/templates/'.$this->project->getToken();
        $this->copy = '/templates/copy/'.$this->project->getToken();
        $this->client->disableReboot();
    }

    #[Group('ACMS-BLD-0017')]
    #[Group('ACMS-US-0079')]
    #[Group('ACMS-US-0078')]
    public function testVisitorCopiesShownVersionAsOwnPersonalProject(): void
    {
        $this->client->loginUser($this->visitor);
        self::assertSame($this->copy, $this->client->request('GET', $this->show)->filter('[data-template-copy]')->attr('action'));

        $this->post($this->copy);
        $copy = $this->copied();
        self::assertResponseRedirects('/builder/projects/'.$copy->getId());
        self::assertSame([$this->version->getName(), ProjectAccess::Personal], [$copy->getName(), $copy->getAccess()]);

        $pages = self::service(ProjectFile::class)->document($copy);
        $picture = $pages[0]['blocks'][0]['values']['picture'];
        self::assertNotSame($this->version->getPages()[0]['blocks'][0]['values']['picture'], $picture, 'the copy holds pictures of its own');
        self::assertEquals(ProjectFile::renamed($this->version->getPages(), [$this->version->getPages()[0]['blocks'][0]['values']['picture'] => $picture]), $pages, 'the copy is the document of the version');
        $this->client->request('GET', '/builder/projects/'.$copy->getId().'/pictures/'.$picture);
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        $this->client->request('GET', $this->copy);
        self::assertResponseRedirects($this->show, message: 'an address of the copy without the action copies nothing');
        self::assertCount(1, self::service(ProjectRepository::class)->findBy(['owner' => $this->visitor]));
    }

    #[Group('ACMS-BLD-0017')]
    #[Group('ACMS-CORE-0087')]
    #[Group('ACMS-UI-0176')]
    #[Group('ACMS-US-0079')]
    public function testGuestSignsInByWindowAndGetsCopyWithoutRepeatingAction(): void
    {
        $window = ['HTTP_HX-Request' => 'true'];
        $this->client->request('POST', $this->copy, [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID)], server: $window);
        $this->client->followRedirect();
        self::assertResponseRedirects('/login');
        self::assertCount(1, $this->client->followRedirect()->filter('.modal-header'), 'the action of a guest opens the window of the login');

        $this->client->request('POST', '/login', [
            '_username' => self::VISITOR,
            '_password' => self::PASSWORD,
            '_csrf_token' => $this->formToken('authenticate'),
        ], server: $window);
        self::assertResponseHeaderSame('HX-Redirect', 'http://localhost'.$this->copy);
        $this->client->request('GET', $this->copy);
        self::assertResponseRedirects('/builder/projects/'.$this->copied()->getId());
    }

    #[Group('ACMS-BLD-0017')]
    #[Group('ACMS-BLD-0020')]
    #[Group('ACMS-US-0078')]
    public function testCopyGoesOnlyToAnotherUserWithinLimitOfProjects(): void
    {
        for ($at = 1; $at <= Projects::PROJECTS; ++$at) {
            $this->em->persist(new Project($this->visitor, 'Project '.$at));
        }
        $this->em->flush();

        $this->client->loginUser($this->author);
        self::assertCount(0, $this->client->request('GET', $this->show)->filter('[data-template-copy]'), 'the owner has no copy of the own project');
        $this->post($this->copy);
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->visitor);
        $this->post($this->copy);
        self::assertSame(self::phrase(t('builder.refusal.projects', ['limit' => Projects::PROJECTS])), self::toast($this->client->followRedirect(), 'error'));
        self::assertNull(self::service(ProjectRepository::class)->findOneBy(['owner' => $this->visitor, 'name' => $this->version->getName()]));
    }

    private function copied(): Project
    {
        return self::service(ProjectRepository::class)->findOneBy(['owner' => $this->visitor]) ?? self::fail('the visitor got no copy');
    }
}
