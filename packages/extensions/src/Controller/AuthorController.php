<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Controller;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\Catalog;
use AdzerCMS\Extensions\Entity\ExtensionAuthor;
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

    /** @return array<string, mixed> */
    private function listing(ExtensionAuthor $author, Request $request): array
    {
        $table = Table::of($request, self::LIST, [
            Column::of('name', t('extensions.column.name'), 'e.name'),
            Column::plain('type', t('extensions.column.type')),
            Column::plain('price', t('extensions.column.price')),
            Column::plain('version', t('extensions.column.version'))->aside(),
            Column::plain('state', t('extensions.column.state')),
        ], 'name', t('extensions.mine.empty'));

        return ['table' => $table, 'rows' => $this->extensions->authored($author, $table)];
    }
}
