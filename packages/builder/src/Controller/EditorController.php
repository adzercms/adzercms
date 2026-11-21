<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\Document;
use AdzerCMS\Builder\Documents;
use AdzerCMS\Builder\FreeBlock;
use AdzerCMS\Builder\PageDocument;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Core\Ui\SiteNav;
use AdzerCMS\Pages\Block;
use AdzerCMS\Pages\BlockForm;
use AdzerCMS\Pages\Blocks;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Controller\Admin\BlockController;
use AdzerCMS\Pages\Entity\BlockRecord;
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

#[IsGranted(Role::USER)]
final class EditorController extends AbstractController
{
    public const EDITOR = 'admin_page_builder';
    public const PROJECT = 'builder_project_editor';
    public const DOCUMENT = 'document';
    public const STORED = 'stored';
    public const FIRST = 'first';

    private const ON_SITE = '/admin/pages/{id}/builder';
    private const IN_PROJECT = '/builder/projects/{project}/pages/{id}/editor';
    private const NUMBERS = ['id' => '\d+', 'project' => '\d+'];
    private const SITE = [Documents::KIND => Documents::SITE];
    private const OF_PROJECT = [Documents::KIND => Documents::PROJECT];
    private const FRAME = '_frame';
    private const SAVE = '_save';
    private const TYPE = '_type';
    private const FILL = '_type_fill';
    private const PICTURES = '_pictures';
    private const FILES = 'files';
    private const DRAWN = 'block';
    private const PREVIEW = [Screen::Tablet->value => 768, Screen::Phone->value => 375];

    public function __construct(
        private readonly PageDocument $document,
        private readonly Site $site,
        private readonly Blocks $types,
        private readonly BlockForm $form,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(self::ON_SITE, name: self::EDITOR, requirements: self::NUMBERS, defaults: self::SITE, methods: ['GET'])]
    #[Route(self::IN_PROJECT, name: self::PROJECT, requirements: self::NUMBERS, defaults: self::OF_PROJECT, methods: ['GET'])]
    public function editor(Document $document): Response
    {
        $opened = $this->document->open($document);
        [$exit, $parameters] = $document->exit();

        return $this->render('@Builder/editor.html.twig', [
            'title' => $document->title(),
            'exit' => $this->generateUrl($exit, $parameters),
            'frame' => $this->url($document, self::FRAME),
            'save' => $this->url($document, self::SAVE),
            'version' => $opened[PageDocument::VERSION],
            'blocks' => array_map(fn (array $block): array => [...$block, PageDocument::STYLE => (object) $block[PageDocument::STYLE], 'dialog' => $block['available'] ? $this->opening($document, $block['type']) : null], $opened[PageDocument::BLOCKS]),
            'types' => array_map(fn (array $type): array => $type + ['dialog' => $this->opening($document, $type['type']), 'draw' => $this->drawing($document, $type['type'])], $this->document->types()),
            'screens' => array_map(static fn (Screen $screen): array => [
                'id' => $screen->value,
                'media' => $screen->width(),
                'width' => self::PREVIEW[$screen->value] ?? null,
                'label' => self::label($screen),
            ], Screen::cases()),
            'sectors' => $this->sectors(),
            'pictures' => $document->album()->listed(),
            'upload' => $this->url($document, self::PICTURES),
            'accept' => implode(',', Photos::TYPES),
            'free' => FreeBlock::KEY,
            'elements' => array_map(fn (string $kind): array => ['kind' => $kind, 'label' => $this->translator->trans('builder.element.'.$kind)], FreeBlock::kinds()),
        ]);
    }

    #[Route(self::ON_SITE.'/pictures', name: self::EDITOR.self::PICTURES, requirements: self::NUMBERS, defaults: self::SITE, methods: ['POST'])]
    #[Route(self::IN_PROJECT.'/pictures', name: self::PROJECT.self::PICTURES, requirements: self::NUMBERS, defaults: self::OF_PROJECT, methods: ['POST'])]
    public function upload(Document $document, Request $request): JsonResponse
    {
        $files = array_values(array_filter($request->files->all(self::FILES), static fn (mixed $file): bool => $file instanceof UploadedFile));
        $refusal = Photos::refusal($files) ?? $document->picturesRefusal(\count($files));
        $album = $document->album();

        return null === $refusal
            ? $this->json(['data' => array_map(static fn (UploadedFile $file): array => $album->entry($album->store($file)), $files)])
            : $this->json(['message' => $refusal->reason->trans($this->translator)], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route(self::ON_SITE, name: self::EDITOR.self::SAVE, requirements: self::NUMBERS, defaults: self::SITE, methods: ['POST'])]
    #[Route(self::IN_PROJECT, name: self::PROJECT.self::SAVE, requirements: self::NUMBERS, defaults: self::OF_PROJECT, methods: ['POST'])]
    public function save(Document $document, Request $request): JsonResponse
    {
        $sent = json_decode($request->request->getString(self::DOCUMENT), true);
        $outcome = \is_array($sent) ? $this->document->save($document, $sent) : new Refusal(t('builder.refusal.document'));

        return $outcome instanceof Refusal
            ? $this->json(['message' => $outcome->reason->trans($this->translator)], Response::HTTP_UNPROCESSABLE_ENTITY)
            : $this->json([
                'message' => $this->translator->trans('builder.saved'),
                PageDocument::VERSION => $outcome[PageDocument::VERSION],
                PageDocument::BLOCKS => array_map(static fn (BlockRecord $block): array => [PageDocument::ID => $block->getId(), 'anchor' => BlockStyle::anchor($block)], $outcome[PageDocument::BLOCKS]),
            ]);
    }

    #[Route(self::ON_SITE.'/frame', name: self::EDITOR.self::FRAME, requirements: self::NUMBERS, defaults: self::SITE, methods: ['GET'])]
    #[Route(self::IN_PROJECT.'/frame', name: self::PROJECT.self::FRAME, requirements: self::NUMBERS, defaults: self::OF_PROJECT, methods: ['GET'])]
    public function frame(Document $document, Request $request): Response
    {
        $menu = $document->menu();
        if (null !== $menu) {
            SiteNav::replace($request, $menu);
        }

        return $this->render('@Builder/frame.html.twig', ['view' => $document->view()]);
    }

    #[Route(self::ON_SITE.'/types/{type}', name: self::EDITOR.self::TYPE, requirements: self::NUMBERS, defaults: self::SITE, methods: ['POST'])]
    #[Route(self::IN_PROJECT.'/types/{type}', name: self::PROJECT.self::TYPE, requirements: self::NUMBERS, defaults: self::OF_PROJECT, methods: ['POST'])]
    public function type(Document $document, string $type, Request $request): Response
    {
        $stored = PageDocument::values(json_decode($request->request->getString(self::STORED), true)) ?? throw new BadRequestException();

        return $this->dialog($document, $this->kind($type), $stored, [], $request);
    }

    #[Route(self::ON_SITE.'/types/{type}/fill', name: self::EDITOR.self::FILL, requirements: self::NUMBERS, defaults: self::SITE, methods: ['POST'])]
    #[Route(self::IN_PROJECT.'/types/{type}/fill', name: self::PROJECT.self::FILL, requirements: self::NUMBERS, defaults: self::OF_PROJECT, methods: ['POST'])]
    public function fill(Document $document, string $type, Request $request): Response
    {
        $block = $this->kind($type);
        [$values, $refusals] = $this->form->posted($block, $request, $document->album());
        if ([] !== $refusals) {
            return $this->dialog($document, $block, $request->request->all(BlockForm::VALUE), $refusals, $request);
        }

        $view = $this->site->block($block, $values, self::DRAWN, $request->request->getBoolean(self::FIRST), $document->album());
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

    /** @param array<string, string> $more */
    private function url(Document $document, string $action, array $more = []): string
    {
        [$route, $parameters] = $document->route();

        return $this->generateUrl($route.$action, $parameters + $more);
    }

    private function opening(Document $document, string $type): ?string
    {
        return [] === $this->kind($type)->fields() ? null : $this->url($document, self::TYPE, ['type' => $type]);
    }

    private function drawing(Document $document, string $type): ?string
    {
        return [] === $this->kind($type)->fields() ? $this->url($document, self::FILL, ['type' => $type]) : null;
    }

    private function kind(string $key): Block
    {
        return $this->types->find($key) ?? throw $this->createNotFoundException();
    }

    /**
     * @param array<string, mixed> $values
     * @param list<Refusal>        $refusals
     */
    private function dialog(Document $document, Block $type, array $values, array $refusals, Request $request): Response
    {
        [$route, $parameters] = $document->picture();

        return $this->render(BlockController::DIALOG, [
            'action' => $this->url($document, self::FILL, ['type' => $type->key()]),
            'hidden' => [self::FIRST => $request->request->getBoolean(self::FIRST) ? '1' : ''],
            'submit' => 'builder.apply',
            'type' => $type,
            'types' => [],
            'values' => BlockForm::shown($type, $values),
            'refusals' => $refusals,
            'pick' => true,
            'album' => ['route' => $route, 'params' => $parameters],
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
