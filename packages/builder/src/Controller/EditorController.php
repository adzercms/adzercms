<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\PageDocument;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Pages\Block;
use AdzerCMS\Pages\BlockForm;
use AdzerCMS\Pages\Blocks;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Controller\Admin\BlockController;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Pictures;
use AdzerCMS\Pages\Site;
use AdzerCMS\Pages\Style\Group;
use AdzerCMS\Pages\Style\Property;
use AdzerCMS\Pages\Style\Screen;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
    private const TYPE = 'admin_page_builder_type';
    private const FILL = 'admin_page_builder_type_fill';
    private const PICTURES = 'admin_page_builder_pictures';
    private const FILES = 'files';
    private const DRAWN = 'block';
    private const PREVIEW = [Screen::Tablet->value => 768, Screen::Phone->value => 375];

    public function __construct(
        private readonly PageDocument $document,
        private readonly Site $site,
        private readonly Blocks $types,
        private readonly BlockForm $form,
        private readonly Pictures $pictures,
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
            'blocks' => array_map(fn (array $block): array => [...$block, PageDocument::STYLE => (object) $block[PageDocument::STYLE], 'dialog' => $block['available'] ? $this->opening($page, $block['type']) : null], $document[PageDocument::BLOCKS]),
            'types' => array_map(fn (array $type): array => $type + ['dialog' => $this->opening($page, $type['type'])], $this->document->types()),
            'screens' => array_map(static fn (Screen $screen): array => [
                'id' => $screen->value,
                'media' => $screen->width(),
                'width' => self::PREVIEW[$screen->value] ?? null,
                'label' => self::label($screen),
            ], Screen::cases()),
            'sectors' => $this->sectors(),
            'pictures' => $this->pictures->listed(),
            'upload' => $this->generateUrl(self::PICTURES, ['id' => $page->getId()]),
            'accept' => implode(',', Photos::TYPES),
        ]);
    }

    #[Route('/pictures', name: self::PICTURES, methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $files = array_values(array_filter($request->files->all(self::FILES), static fn (mixed $file): bool => $file instanceof UploadedFile));
        $refusal = Photos::refusal($files);

        return null === $refusal
            ? $this->json(['data' => array_map(fn (UploadedFile $file): array => $this->pictures->entry($this->pictures->store($file)), $files)])
            : $this->json(['message' => $refusal->reason->trans($this->translator)], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('', name: self::SAVE, methods: ['POST'])]
    public function save(Page $page, Request $request): JsonResponse
    {
        $document = json_decode($request->request->getString(self::DOCUMENT), true);
        $outcome = \is_array($document) ? $this->document->save($page, $document) : new Refusal(t('builder.refusal.document'));

        return $outcome instanceof Refusal
            ? $this->json(['message' => $outcome->reason->trans($this->translator)], Response::HTTP_UNPROCESSABLE_ENTITY)
            : $this->json([
                'message' => $this->translator->trans('builder.saved'),
                PageDocument::VERSION => $outcome[PageDocument::VERSION],
                PageDocument::BLOCKS => array_map(static fn (PageBlock $block): array => [PageDocument::ID => $block->getId(), 'anchor' => BlockStyle::anchor($block)], $outcome[PageDocument::BLOCKS]),
            ]);
    }

    #[Route('/frame', name: self::FRAME, methods: ['GET'])]
    public function frame(Page $page): Response
    {
        return $this->render('@Builder/frame.html.twig', ['page' => $page, 'view' => $this->site->view($page)]);
    }

    #[Route('/types/{type}', name: self::TYPE, methods: ['POST'])]
    public function type(Page $page, string $type, Request $request): Response
    {
        $stored = PageDocument::values(json_decode($request->request->getString(self::STORED), true)) ?? throw new BadRequestException();

        return $this->dialog($page, $this->kind($type), $stored, [], $request);
    }

    #[Route('/types/{type}/fill', name: self::FILL, methods: ['POST'])]
    public function fill(Page $page, string $type, Request $request): Response
    {
        $block = $this->kind($type);
        [$values, $refusals] = $this->form->posted($block, $request);
        if ([] !== $refusals) {
            return $this->dialog($page, $block, $request->request->all(BlockForm::VALUE), $refusals, $request);
        }

        $view = $this->site->block($block, $values, self::DRAWN, $request->request->getBoolean(self::FIRST));
        $response = $this->json([PageDocument::VALUES => $values, 'anchor' => self::DRAWN, 'html' => $this->renderView($view['template'], $view)]);
        $response->headers->set('HX-Reswap', 'none');

        return $response;
    }

    /** @return list<array{id: string, name: string, properties: list<array{property: string, label: string, kind: string, keywords: list<array{id: string, label: string}>, units: list<string>, signed: bool}>}> */
    private function sectors(): array
    {
        $properties = [];
        foreach (Property::cases() as $property) {
            $properties[$property->group()->value][] = [
                'property' => $property->value,
                'label' => $property->label()->trans($this->translator),
                'kind' => $property->kind()->value,
                'keywords' => array_map(fn (string $keyword): array => ['id' => $keyword, 'label' => Property::word($keyword)->trans($this->translator)], $property->keywords()),
                'units' => $property->units(),
                'signed' => $property->signed(),
            ];
        }

        return array_map(fn (Group $group): array => ['id' => $group->value, 'name' => $group->label()->trans($this->translator), 'properties' => $properties[$group->value] ?? []], Group::cases());
    }

    private function opening(Page $page, string $type): string
    {
        return $this->generateUrl(self::TYPE, ['id' => $page->getId(), 'type' => $type]);
    }

    private function kind(string $key): Block
    {
        return $this->types->find($key) ?? throw $this->createNotFoundException();
    }

    /**
     * @param array<string, mixed> $values
     * @param list<Refusal>        $refusals
     */
    private function dialog(Page $page, Block $type, array $values, array $refusals, Request $request): Response
    {
        return $this->render(BlockController::DIALOG, [
            'page' => $page,
            'action' => $this->generateUrl(self::FILL, ['id' => $page->getId(), 'type' => $type->key()]),
            'hidden' => [self::FIRST => $request->request->getBoolean(self::FIRST) ? '1' : ''],
            'submit' => 'builder.apply',
            'type' => $type,
            'types' => [],
            'values' => BlockForm::shown($type, $values),
            'refusals' => $refusals,
            'pick' => true,
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
