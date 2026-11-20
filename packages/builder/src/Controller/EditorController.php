<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\PageDocument;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Pages\Block;
use AdzerCMS\Pages\BlockForm;
use AdzerCMS\Pages\Blocks;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Controller\Admin\BlockController;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Site;
use AdzerCMS\Pages\Style\Screen;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
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
    public const STORED = 'stored';
    public const FIRST = 'first';

    private const FRAME = 'admin_page_builder_frame';
    private const SAVE = 'admin_page_builder_save';
    private const BLOCK = 'admin_page_builder_block';
    private const FILL = 'admin_page_builder_block_fill';
    private const PREVIEW = [Screen::Tablet->value => 768, Screen::Phone->value => 375];

    public function __construct(
        private readonly PageDocument $document,
        private readonly Site $site,
        private readonly Blocks $types,
        private readonly PageBlockRepository $blocks,
        private readonly BlockForm $form,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: self::EDITOR, methods: ['GET'])]
    public function editor(Page $page): Response
    {
        $document = $this->document->open($page);

        return $this->render('@Builder/editor.html.twig', [
            'page' => $page,
            'frame' => $this->generateUrl(self::FRAME, ['id' => $page->getId()]),
            'save' => $this->generateUrl(self::SAVE, ['id' => $page->getId()]),
            'version' => $document[PageDocument::VERSION],
            'blocks' => array_map(fn (array $block): array => $block + [
                'dialog' => $block['available'] ? $this->generateUrl(self::BLOCK, ['id' => $page->getId(), 'block' => $block['id']]) : null,
            ], $document[PageDocument::BLOCKS]),
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
        $outcome = \is_array($document) ? $this->document->save($page, $document) : new Refusal(t('builder.refusal.document'));

        return $outcome instanceof Refusal
            ? $this->json(['message' => $outcome->reason->trans($this->translator)], Response::HTTP_UNPROCESSABLE_ENTITY)
            : $this->json(['message' => $this->translator->trans('builder.saved'), PageDocument::VERSION => $outcome]);
    }

    #[Route('/frame', name: self::FRAME, methods: ['GET'])]
    public function frame(Page $page): Response
    {
        return $this->render('@Builder/frame.html.twig', ['page' => $page, 'view' => $this->site->view($page)]);
    }

    #[Route('/blocks/{block}', name: self::BLOCK, requirements: ['block' => '\d+'], methods: ['POST'])]
    public function block(Page $page, int $block, Request $request): Response
    {
        [$block, $type, $stored] = $this->edited($page, $block, $request);

        return $this->dialog($page, $block, $type, $stored, $stored, [], $request);
    }

    #[Route('/blocks/{block}/fill', name: self::FILL, requirements: ['block' => '\d+'], methods: ['POST'])]
    public function fill(Page $page, int $block, Request $request): Response
    {
        [$block, $type, $stored] = $this->edited($page, $block, $request);
        [$values, $refusals] = $this->form->posted($type, $request, $stored);
        if ([] !== $refusals) {
            return $this->dialog($page, $block, $type, $request->request->all(BlockForm::VALUE), $stored, $refusals, $request);
        }

        $view = $this->site->block($type, $values, BlockStyle::anchor($block), $request->request->getBoolean(self::FIRST));
        $response = $this->json([PageDocument::VALUES => $values, 'html' => $this->renderView($view['template'], $view)]);
        $response->headers->set('HX-Reswap', 'none');

        return $response;
    }

    /** @return array{PageBlock, Block, array<string, string>} */
    private function edited(Page $page, int $id, Request $request): array
    {
        $block = $this->blocks->find($id);
        $type = $block?->getPage() === $page ? $this->types->find($block->getType()) : null;
        if (null === $block || null === $type) {
            throw $this->createNotFoundException();
        }

        return [$block, $type, PageDocument::values(json_decode($request->request->getString(self::STORED), true)) ?? throw new BadRequestException()];
    }

    /**
     * @param array<string, mixed>  $values
     * @param array<string, string> $stored
     * @param list<Refusal>         $refusals
     */
    private function dialog(Page $page, PageBlock $block, Block $type, array $values, array $stored, array $refusals, Request $request): Response
    {
        return $this->render(BlockController::DIALOG, [
            'page' => $page,
            'action' => $this->generateUrl(self::FILL, ['id' => $page->getId(), 'block' => $block->getId()]),
            'hidden' => [self::STORED => json_encode($stored, \JSON_THROW_ON_ERROR), self::FIRST => $request->request->getBoolean(self::FIRST) ? '1' : ''],
            'submit' => 'builder.apply',
            'type' => $type,
            'types' => [],
            'values' => BlockForm::shown($type, $values, $stored),
            'refusals' => $refusals,
        ]);
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
