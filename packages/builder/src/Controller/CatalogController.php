<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Core\Site\Hosts;
use AdzerCMS\Core\Ui\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

final class CatalogController extends AbstractController
{
    public const CATALOG = 'builder_catalog';

    public function __construct(private readonly ProjectVersionRepository $versions)
    {
    }

    #[Route('/templates', name: self::CATALOG, methods: ['GET'], defaults: [Hosts::ROLE => Hosts::SITE])]
    public function index(Request $request): Response
    {
        $table = Table::of($request, self::CATALOG, [], '', t('builder.catalog.empty'));

        return $this->render('@Builder/catalog.html.twig', ['table' => $table, 'rows' => $this->versions->catalog($table)]);
    }
}
