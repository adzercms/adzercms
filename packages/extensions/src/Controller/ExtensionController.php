<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Controller;

use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\Catalog;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\ExtensionDecision;
use AdzerCMS\Extensions\ExtensionState;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/extensions')]
#[IsGranted(Role::ADMIN)]
final class ExtensionController extends AbstractController
{
    private const LIST = 'admin_extensions';

    public function __construct(
        private readonly ExtensionRepository $extensions,
        private readonly Catalog $catalog,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $context = $this->listing($request);

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Extensions/admin/_extensions.html.twig', $context);
        }

        return $this->render('@Extensions/admin/extensions.html.twig', $context);
    }

    #[Route('/order', name: 'admin_extensions_order', methods: ['POST'])]
    public function order(Request $request): Response
    {
        Order::place($this->extensions->ordered(), Order::moved($request), static fn (Extension $entry): ExtensionDecision => $entry->getDecision());
        $this->em->flush();

        return $this->render('@Extensions/admin/_extensions.html.twig', $this->listing($request));
    }

    #[Route('/new', name: 'admin_extension_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->record(new Extension(), $request);
    }

    #[Route('/{id}', name: 'admin_extension', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Extension $extension, Request $request): Response
    {
        return null === $extension->getAuthor() ? $this->record($extension, $request) : $this->card($extension);
    }

    #[Route('/{id}/decide', name: 'admin_extension_decide', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decide(Extension $extension, Request $request): Response
    {
        $refusal = $this->catalog->decide($extension, ExtensionDecision::tryFrom($request->request->getString('decision')), $request->request->getString('reason'));
        if (null !== $refusal) {
            return $this->card($extension, $refusal->reason);
        }

        $this->addFlash(ExtensionDecision::Published === $extension->getDecision() && ExtensionState::Published !== $extension->state() ? 'error' : 'success',
            t('extensions.flash.decided', ['name' => $extension->getName(), 'state' => $extension->state()->label()]));

        return Fragment::go($this->generateUrl(self::LIST));
    }

    #[Route('/{id}/drop', name: 'admin_extension_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(Extension $extension): Response
    {
        $this->addFlash('success', t('extensions.flash.dropped', ['name' => $extension->getName()]));
        $this->em->remove($extension);
        $this->em->flush();

        return $this->redirectToRoute(self::LIST);
    }

    private function record(Extension $extension, Request $request): Response
    {
        $refusal = null;

        if ($request->isMethod('POST')) {
            $refusal = $this->catalog->save($extension, $request->request);

            if (null === $refusal) {
                $this->addFlash(...ExtensionState::Published === $extension->state()
                    ? ['success', t('extensions.flash.saved', ['name' => $extension->getName()])]
                    : ['error', t('extensions.flash.missing', ['name' => $extension->getName()])]);

                return Fragment::go($this->generateUrl(self::LIST));
            }
        }

        return $this->render('@Extensions/_extension.html.twig', [
            'extension' => $extension,
            'action' => null === $extension->getId() ? $this->generateUrl('admin_extension_new') : $this->generateUrl('admin_extension', ['id' => $extension->getId()]),
            'types' => ExtensionType::cases(),
            'input' => $request->request->all(),
            'error' => $refusal?->reason,
        ]);
    }

    private function card(Extension $extension, ?TranslatableInterface $error = null): Response
    {
        return $this->render('@Extensions/admin/_entry.html.twig', ['extension' => $extension, 'error' => $error]);
    }

    /** @return array<string, mixed> */
    private function listing(Request $request): array
    {
        $table = Table::of($request, self::LIST, [
            Column::plain('name', t('extensions.column.name')),
            Column::plain('type', t('extensions.column.type')),
            Column::plain('price', t('extensions.column.price')),
            Column::plain('demo', t('extensions.column.demo'))->aside(),
            Column::plain('version', t('extensions.column.version'))->aside(),
            Column::plain('author', t('extensions.column.author'))->aside(),
            Column::plain('state', t('extensions.column.state')),
            Column::plain('actions'),
        ], 'name', t('extensions.empty'),
            filters: [
                Filter::search(),
                Filter::cases('type', t('extensions.filter.type'), ExtensionType::cases())->on('e.type'),
                Filter::cases('state', t('extensions.column.state'), ExtensionState::cases())->on('e.state'),
            ],
            order: Order::of('admin_extensions_order', 'e.decision', 'e.position', 'e.id'),
        );

        return ['table' => $table, 'rows' => $this->extensions->page($table)];
    }
}
