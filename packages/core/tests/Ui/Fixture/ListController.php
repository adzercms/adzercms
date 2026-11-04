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

    public const CREATE = '/fixture/list/new';

    private const HEADED = <<<'TWIG'
        {% embed '@Theme/_table.html.twig' %}
            {% block row %}<tr><td>{{ row.name }}</td></tr>{% endblock %}
        {% endembed %}
        TWIG;

    #[Route('/fixture/list', name: 'fixture_list', methods: ['GET'])]
    public function list(Request $request, Environment $twig): Response
    {
        $table = Table::of($request, 'fixture_list', [Column::of('name', t('theme.main'), 'name')], 'name', t('theme.main'), filters: [Filter::search()], order: Order::of('fixture_list_order', 'position'));

        return new Response($twig->createTemplate(self::PAGE)->render(['table' => $table, 'rows' => self::ROWS]));
    }

    private const PAGED = <<<'TWIG'
        {% extends '@Theme/panel.html.twig' %}
        {% block title %}{{ 'theme.main'|trans }}{% endblock %}
        {% block page %}
        {% embed '@Theme/_table.html.twig' %}
            {% block row %}<tr><td>{{ row.name }}</td></tr>{% endblock %}
        {% endembed %}
        {% endblock %}
        TWIG;

    #[Route('/fixture/list/headed', name: 'fixture_list_headed', methods: ['GET'])]
    public function headed(Request $request, Environment $twig): Response
    {
        return $this->creating($request, $twig, 'fixture_list_headed', self::HEADED, ['heading' => 'theme.main']);
    }

    #[Route('/fixture/list/paged', name: 'fixture_list_paged', methods: ['GET'])]
    public function paged(Request $request, Environment $twig): Response
    {
        return $this->creating($request, $twig, 'fixture_list_paged', self::PAGED, []);
    }

    /** @param array<string, mixed> $context */
    private function creating(Request $request, Environment $twig, string $route, string $template, array $context): Response
    {
        $table = Table::of($request, $route, [Column::of('name', t('theme.main'), 'name')], 'name', t('theme.main'), filters: [Filter::search()]);

        return new Response($twig->createTemplate($template)->render($context + [
            'table' => $table,
            'rows' => $request->query->has('none') ? [] : self::ROWS,
            'actions' => [['href' => self::CREATE, 'label' => 'theme.main']],
        ]));
    }

    #[Route('/fixture/list/order', name: 'fixture_list_order', methods: ['POST'])]
    public function order(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
