<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\PageDocument;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Site;
use AdzerCMS\Pages\Style\Screen;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/pages/{id}/builder', requirements: ['id' => '\d+'])]
#[IsGranted(Role::ADMIN)]
final class EditorController extends AbstractController
{
    public const EDITOR = 'admin_page_builder';
    public const DOCUMENT = 'document';

    private const FRAME = 'admin_page_builder_frame';
    private const SAVE = 'admin_page_builder_save';
    private const PREVIEW = [Screen::Tablet->value => 768, Screen::Phone->value => 375];

    public function __construct(
        private readonly PageDocument $document,
        private readonly Site $site,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: self::EDITOR, methods: ['GET'])]
    public function editor(Page $page): Response
    {
        return $this->render('@Builder/editor.html.twig', [
            'page' => $page,
            'frame' => $this->generateUrl(self::FRAME, ['id' => $page->getId()]),
            'save' => $this->generateUrl(self::SAVE, ['id' => $page->getId()]),
            'blocks' => $this->document->blocks($page),
            'screens' => array_map(static fn (Screen $screen): array => [
                'id' => $screen->value,
                'media' => $screen->width(),
                'width' => self::PREVIEW[$screen->value] ?? null,
                'label' => self::label($screen),
            ], Screen::cases()),
        ]);
    }

    #[Route('', name: self::SAVE, methods: ['POST'])]
    public function save(Page $page, Request $request): JsonResponse
    {
        $document = json_decode($request->request->getString(self::DOCUMENT), true);
        $refusal = \is_array($document) ? $this->document->save($page, $document) : new Refusal(t('builder.refusal.document'));

        return null === $refusal
            ? $this->json(['message' => $this->translator->trans('builder.saved')])
            : $this->json(['message' => $refusal->reason->trans($this->translator)], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/frame', name: self::FRAME, methods: ['GET'])]
    public function frame(Page $page): Response
    {
        return $this->render('@Builder/frame.html.twig', ['page' => $page, 'view' => $this->site->view($page)]);
    }

    private static function label(Screen $screen): TranslatableInterface
    {
        return match ($screen) {
            Screen::Desktop => t('builder.screen.desktop'),
            Screen::Tablet => t('builder.screen.tablet'),
            Screen::Phone => t('builder.screen.phone'),
        };
    }
}
