<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Controller;

use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\Catalog;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\Entity\ExtensionAuthor;
use AdzerCMS\Extensions\ExtensionDecision;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/extensions/mine')]
#[IsGranted(Role::USER)]
final class AuthorController extends AbstractController
{
    public const LIST = 'extensions_mine';

    public function __construct(
        private readonly ExtensionRepository $extensions,
        private readonly Catalog $catalog,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $author = $this->catalog->author($user);
        $context = $this->listing($author, $request);

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Extensions/cabinet/_mine.html.twig', $context);
        }

        return $this->render('@Extensions/cabinet/mine.html.twig', $context + ['author' => $author]);
    }

    #[Route('/new', name: 'extensions_mine_new', methods: ['GET', 'POST'])]
    public function create(#[CurrentUser] User $user, Request $request): Response
    {
        return $this->record(new Extension($this->catalog->author($user)), $request);
    }

    #[Route('/{id}', name: 'extensions_mine_entry', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(#[CurrentUser] User $user, int $id, Request $request): Response
    {
        $extension = $this->extensions->findOneBy(['id' => $id, 'author' => $this->catalog->author($user)]);

        return $this->record($extension ?? throw $this->createNotFoundException(), $request);
    }

    private function record(Extension $extension, Request $request): Response
    {
        $refusal = null;

        if ($request->isMethod('POST')) {
            $refusal = $this->catalog->submit($extension, $request->request);

            if (null === $refusal) {
                $this->addFlash('success', ExtensionDecision::Pending === $extension->getDecision()
                    ? t('extensions.flash.submitted', ['name' => $extension->getName()])
                    : t('extensions.flash.saved', ['name' => $extension->getName()]));

                return Fragment::go($this->generateUrl(self::LIST));
            }
        }

        return $this->render('@Extensions/_extension.html.twig', [
            'extension' => $extension,
            'action' => null === $extension->getId() ? $this->generateUrl('extensions_mine_new') : $this->generateUrl('extensions_mine_entry', ['id' => $extension->getId()]),
            'types' => ExtensionType::cases(),
            'input' => $request->request->all(),
            'error' => $refusal?->reason,
        ]);
    }

    /** @return array<string, mixed> */
    private function listing(ExtensionAuthor $author, Request $request): array
    {
        $table = Table::of($request, self::LIST, [
            Column::of('name', t('extensions.column.name'), 'e.name'),
            Column::of('type', t('extensions.column.type'), 'e.type'),
            Column::of('price', t('extensions.column.price'), 'e.price'),
            Column::of('version', t('extensions.column.version'), 'e.version')->aside(),
            Column::of('state', t('extensions.column.state'), 'e.state'),
        ], 'name', t('extensions.mine.empty'));

        return ['table' => $table, 'rows' => $this->extensions->authored($author, $table)];
    }
}
