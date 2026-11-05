<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Controller;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Http\ImmutableFileResponse;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\ExtensionImages;
use AdzerCMS\Extensions\ExtensionPrice;
use AdzerCMS\Extensions\Guide;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function Symfony\Component\Translation\t;

final class CatalogController extends AbstractController
{
    public const PAGE = 'extensions';
    public const GUIDE = 'extensions_publishing';
    public const IMAGE = 'extensions_image';

    public function __construct(
        private readonly ExtensionRepository $extensions,
        private readonly ExtensionImages $images,
    ) {
    }

    #[Route('/extensions', name: self::PAGE, methods: ['GET'], options: ['site' => true])]
    public function index(Request $request): Response
    {
        $table = Table::of($request, self::PAGE, [], '', t('extensions.empty'), filters: [
            Filter::tabs('type', ExtensionType::all(), ExtensionType::tabs())->on('e.type'),
            Filter::cases('category', t('extensions.filter.category'), Category::cases()),
            Filter::cases('price', t('extensions.filter.price'), ExtensionPrice::cases())->filled('e.price', ExtensionPrice::Paid),
        ]);
        $context = ['table' => $table, 'rows' => $this->extensions->published($table)];

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Extensions/_catalog.html.twig', $context);
        }

        return $this->render('@Extensions/catalog.html.twig', $context);
    }

    #[Route('/extensions/publishing', name: self::GUIDE, methods: ['GET'], options: ['site' => true])]
    public function guide(Request $request): Response
    {
        return $this->render('@Extensions/guide.html.twig', ['guide' => Guide::read($request->getLocale())]);
    }

    #[Route('/extensions/images/{file}', name: self::IMAGE, requirements: ['file' => '[^/]+'], methods: ['GET'], options: ['site' => true])]
    public function image(string $file, Request $request): Response
    {
        return new ImmutableFileResponse($this->images->path($file) ?? throw $this->createNotFoundException(), $request);
    }

    #[Route('/extensions/store.json', name: 'extensions_store', methods: ['GET'], options: ['site' => true])]
    public function store(Request $request): Response
    {
        $response = new JsonResponse(['extensions' => array_map(fn (Extension $extension): array => [
            'name' => $extension->getName(),
            'type' => $extension->getType()->value,
            'demo' => $extension->getDemo(),
            'images' => array_map(fn (string $name): string => $this->generateUrl(self::IMAGE, ['file' => $name], UrlGeneratorInterface::ABSOLUTE_URL), $extension->getImages()),
        ], $this->extensions->listed())]);
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}
