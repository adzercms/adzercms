<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Admin\AssembledModules;
use AdzerCMS\Admin\SettingForm;
use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\ModuleAction;
use AdzerCMS\Core\Module\Requests;
use AdzerCMS\Core\Module\Tree;
use AdzerCMS\Core\Repository\ModuleRequestRepository;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/modules')]
#[IsGranted(Role::ADMIN)]
final class ModuleController extends AbstractController
{
    private const ORDER = 'name';
    private const CATALOG = 'catalog';
    private const CATEGORY = 'category';
    private const REQUESTS = 'requests';

    public function __construct(
        private readonly AssembledModules $assembled,
        private readonly Catalog $catalog,
        private readonly Requests $requests,
        private readonly ModuleRequestRepository $filings,
        private readonly Tree $tree,
        private readonly SettingForm $form,
    ) {
    }

    #[Route('', name: 'admin_modules', methods: ['GET'])]
    public function modules(Request $request, TranslatorInterface $translator): Response
    {
        $rows = [];

        foreach ($this->assembled->modules() as $key => $module) {
            $rows[] = [
                'key' => $key,
                'name' => $module['name'],
                'kind' => $module['kind']->trans($translator),
                'schema' => (string) $this->assembled->schema($module['namespace']),
            ];
        }

        $table = Table::of($request, 'admin_modules', [
            Column::live('name', t('admin.modules.name'), static fn (array $row): string => $row['name']),
            Column::live('kind', t('admin.modules.kind'), static fn (array $row): string => $row['kind']),
            Column::live('schema', t('admin.modules.schema'), static fn (array $row): string => $row['schema'])->aside(),
        ], self::ORDER, t('admin.modules.empty'), filters: [Filter::search()], live: true);

        $catalog = Table::of($request, 'admin_modules', [
            Column::live('name', t('admin.catalog.package'), static fn (array $row): string => $row['name']),
            Column::live('kind', t('admin.modules.kind'), static fn (array $row): string => $row['kind']),
            Column::live('version', t('admin.catalog.version'), static fn (array $row): string => $row['version']),
            Column::live('installed', t('admin.catalog.installed'), static fn (array $row): string => $row['installed']),
            Column::live('repository', t('admin.catalog.repository'), static fn (array $row): string => $row['repository'])->aside(),
            Column::plain('actions'),
        ], self::ORDER, t('admin.catalog.empty'), filters: [
            Filter::search(),
            Filter::cases(self::CATEGORY, t('admin.catalog.category'), Category::cases()),
        ], live: true, id: self::CATALOG);
        $offered = $this->catalog->rows();

        $requests = Table::of($request, 'admin_modules', [
            Column::plain('package', t('admin.catalog.package')),
            Column::plain('action', t('admin.requests.action')),
            Column::plain('state', t('admin.requests.state')),
            Column::of('filed', t('admin.requests.filed'), 'r.id'),
            Column::plain('finished', t('admin.requests.finished'))->aside(),
        ], 'filed', t('admin.requests.empty'), ascending: false, id: self::REQUESTS);

        return $this->render('@Admin/admin/modules.html.twig', [
            'table' => $table,
            'rows' => $table->rows($rows),
            'catalog' => $catalog,
            'offers' => $catalog->rows($this->offers($offered['rows'], Category::tryFrom($catalog->value(self::CATEGORY)), $translator)),
            'taken' => $offered['at'],
            'versioned' => $this->tree->versioned(),
            'requests' => $requests,
            'filings' => $this->filings->page($requests),
        ]);
    }

    #[Route('/request/{action}', name: 'admin_module_request', methods: ['POST'])]
    public function request(ModuleAction $action, Request $request): Response
    {
        $refusal = $this->requests->file($this->catalog->row($request->query->getString('package')), $action);
        $this->addFlash(null === $refusal ? 'success' : 'error', $refusal->reason ?? t('admin.requests.done'));

        return $this->redirectToRoute('admin_modules');
    }

    /**
     * @param list<CatalogRow> $rows
     *
     * @return list<array{name: string, description: string, link: ?string, kind: string, version: string, installed: string, repository: string, actions: list<array{label: TranslatableInterface, route: string, params: array<string, string>, post: true}>}>
     */
    private function offers(array $rows, ?Category $category, TranslatorInterface $translator): array
    {
        $offers = [];
        foreach ($rows as $row) {
            if (null !== $category && !\in_array($category, $row->package->categories, true)) {
                continue;
            }
            $offers[] = [
                'name' => $row->name,
                'description' => $row->package->description,
                'link' => $row->package->homepage ?? $row->package->source,
                'kind' => \is_string($row->kind) ? $row->kind : $row->kind->label()->trans($translator),
                'version' => (string) $row->version,
                'installed' => (string) $row->installed,
                'repository' => null === $row->repository ? '' : (string) parse_url($row->repository, \PHP_URL_HOST),
                'actions' => array_map(static fn (ModuleAction $action): array => [
                    'label' => $action->label(),
                    'route' => 'admin_module_request',
                    'params' => ['action' => $action->value, 'package' => $row->name],
                    'post' => true,
                ], $this->requests->actions($row)),
            ];
        }

        return $offers;
    }

    #[Route('/{key}', name: 'admin_module', methods: ['GET', 'POST'])]
    public function module(string $key, Request $request): Response
    {
        $module = $this->assembled->modules()[$key] ?? throw $this->createNotFoundException();
        $fields = $module['fields'];

        if ($request->isMethod('POST')) {
            if ($this->form->save($fields, $request)) {
                $this->addFlash('success', t('admin.module.saved'));
            }

            foreach ($this->form->faults() as $fault) {
                $this->addFlash('error', $fault);
            }

            return $this->redirectToRoute('admin_module', ['key' => $key]);
        }

        return $this->render('@Admin/admin/module.html.twig', [
            'module' => $module,
            'schema' => $this->assembled->schema($module['namespace']),
            'values' => $this->form->values($fields),
            'sections' => $this->form->sections($fields),
        ]);
    }
}
