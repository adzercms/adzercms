<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Controller;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\ExtensionPrice;
use AdzerCMS\Extensions\ExtensionType;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

final class CatalogController extends AbstractController
{
    private const PAGE = 'extensions';

    public function __construct(private readonly ExtensionRepository $extensions)
    {
    }

    #[Route('/extensions', name: self::PAGE, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $table = Table::of($request, self::PAGE, [], '', t('extensions.empty'), filters: [
            Filter::cases('type', t('extensions.filter.type'), ExtensionType::cases()),
            Filter::cases('category', t('extensions.filter.category'), Category::cases()),
            Filter::cases('price', t('extensions.filter.price'), ExtensionPrice::cases()),
        ]);
        $context = ['table' => $table, 'rows' => $this->extensions->published($table)];

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Extensions/_catalog.html.twig', $context);
        }

        return $this->render('@Extensions/catalog.html.twig', $context);
    }
}
