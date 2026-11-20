<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Pages\Pictures;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Repository\PageRepository;
use AdzerCMS\Pages\Site;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class Documents implements ValueResolverInterface
{
    public const KIND = '_document';
    public const SITE = 'site';
    public const PROJECT = 'project';

    public function __construct(
        private PageRepository $pages,
        private PageBlockRepository $pageBlocks,
        private Pictures $pictures,
        private ProjectPageRepository $projectPages,
        private ProjectBlockRepository $projectBlocks,
        private ProjectAlbums $albums,
        private Site $site,
        private Security $security,
    ) {
    }

    /** @return iterable<Document> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (Document::class !== $argument->getType()) {
            return [];
        }
        $id = $request->attributes->getInt('id');

        return [match ($request->attributes->get(self::KIND)) {
            self::SITE => $this->page($id),
            self::PROJECT => $this->project($request->attributes->getInt(self::PROJECT), $id),
            default => throw new NotFoundHttpException(),
        }];
    }

    private function page(int $id): SiteDocument
    {
        if (!$this->security->isGranted(Role::ADMIN)) {
            throw new AccessDeniedException();
        }

        return new SiteDocument($this->pages->find($id) ?? throw new NotFoundHttpException(), $this->pageBlocks, $this->pictures, $this->site);
    }

    private function project(int $project, int $id): ProjectDocument
    {
        $user = $this->security->getUser();
        $page = $user instanceof User ? $this->projectPages->owned($user, $project, $id) : throw new AccessDeniedException();

        return new ProjectDocument($page ?? throw new NotFoundHttpException(), $this->projectBlocks, $this->albums->of($page->getProject()), $this->site);
    }
}
