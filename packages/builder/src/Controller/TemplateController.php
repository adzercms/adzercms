<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Core\Moderation\Moderation;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/builder/templates')]
#[IsGranted(Role::ADMIN)]
final class TemplateController extends AbstractController
{
    public const LIST = 'admin_builder_templates';
    public const RECORD = 'admin_builder_template';

    public function __construct(
        private readonly ProjectVersionRepository $versions,
        private readonly Moderation $moderation,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $table = Table::of($request, self::LIST, [
            Column::plain('cover'),
            Column::of('name', t('builder.project.name'), 'v.name'),
            Column::of('access', t('builder.access.column'), 'p.access')->aside(),
            Column::of('author', t('builder.templates.author'), 'u.id')->aside(),
            Column::of('submitted', t('builder.templates.submitted'), 'v.submitted'),
            Column::of('decision', t('core.column.status'), 'v.decision'),
        ], 'submitted', t('builder.templates.empty'), ascending: false, filters: [
            Filter::search(),
            Filter::cases('decision', t('core.column.status'), Decision::cases())->on('v.decision'),
        ]);
        $context = ['table' => $table, 'rows' => $this->versions->page($table)];

        return $request->headers->has('HX-Request')
            ? $this->render('@Builder/admin/_templates.html.twig', $context)
            : $this->render('@Builder/admin/templates.html.twig', $context);
    }

    #[Route('/{id}', name: self::RECORD, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(ProjectVersion $version): Response
    {
        return $this->card($version);
    }

    #[Route('/{id}/decide', name: 'admin_builder_template_decide', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decide(ProjectVersion $version, Request $request): Response
    {
        $refusal = $this->moderation->decide($version, Decision::tryFrom($request->request->getString(Moderation::DECISION)), $request->request->getString(Moderation::REASON));
        if (null !== $refusal) {
            return $this->card($version, $refusal->reason);
        }
        $this->addFlash('success', t('builder.version.current', ['name' => $version->getName(), 'decision' => $version->getDecision()->label()]));

        return Fragment::go($this->generateUrl(self::LIST));
    }

    private function card(ProjectVersion $version, ?TranslatableInterface $error = null): Response
    {
        return $this->render('@Builder/admin/_template.html.twig', ['version' => $version, 'error' => $error]);
    }
}
