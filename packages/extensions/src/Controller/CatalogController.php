<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Controller;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\ExtensionPrice;
use AdzerCMS\Extensions\Guide;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

final class CatalogController extends AbstractController
{
    public const PAGE = 'extensions';
    public const GUIDE = 'extensions_publishing';

    public function __construct(private readonly ExtensionRepository $extensions)
    {
    }

    #[Route('/extensions', name: self::PAGE, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $table = Table::of($request, self::PAGE, [], '', t('extensions.empty'), filters: [
            Filter::tabs('type', ExtensionType::all(), ExtensionType::tabs())->on('e.type'),
            Filter::cases('category', t('extensions.filter.category'), Category::cases()),
            Filter::cases('price', t('extensions.filter.price'), ExtensionPrice::cases()),
        ]);
        $context = ['table' => $table, 'rows' => $this->extensions->published($table)];

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Extensions/_catalog.html.twig', $context);
        }

        return $this->render('@Extensions/catalog.html.twig', $context);
    }

    #[Route('/extensions/publishing', name: self::GUIDE, methods: ['GET'])]
    public function guide(Request $request): Response
    {
        return $this->render('@Extensions/guide.html.twig', ['guide' => Guide::read($request->getLocale())]);
    }

    #[Route('/extensions/store.json', name: 'extensions_store', methods: ['GET'])]
    public function store(Request $request): Response
    {
        $response = new JsonResponse(['extensions' => array_map(static fn (Extension $extension): array => [
            'name' => $extension->getName(),
            'type' => $extension->getType()->value,
            'demo' => $extension->getDemo(),
        ], $this->extensions->listed())]);
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}
