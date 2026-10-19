<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui\Fixture;

use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

use function Symfony\Component\Translation\t;

#[AsController]
final class ListController
{
    public const ROWS = [['id' => 7, 'name' => 'Alpha', 'group' => 'g'], ['id' => 8, 'name' => 'Beta', 'group' => 'g']];

    private const PAGE = <<<'TWIG'
        {% embed '@Theme/_table.html.twig' %}
            {% block row %}
                <tr>{% include '@Theme/_row_handle.html.twig' with {id: row.id, group: row.group} %}<td>{{ row.name }}</td></tr>
            {% endblock %}
        {% endembed %}
        TWIG;

    #[Route('/fixture/list', name: 'fixture_list', methods: ['GET'])]
    public function list(Request $request, Environment $twig): Response
    {
        $table = Table::of($request, 'fixture_list', [Column::of('name', t('theme.show'), 'name')], 'name', t('theme.show'), filters: [Filter::search()], order: Order::of('fixture_list_order', 'position'));

        return new Response($twig->createTemplate(self::PAGE)->render(['table' => $table, 'rows' => self::ROWS]));
    }

    #[Route('/fixture/list/order', name: 'fixture_list_order', methods: ['POST'])]
    public function order(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
