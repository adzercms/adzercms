<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Admin\AssembledModules;
use AdzerCMS\Admin\ModuleScope;
use AdzerCMS\Admin\ModuleState;
use AdzerCMS\Admin\SettingForm;
use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Ui\Badge;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Composer\Artifacts;
use AdzerCMS\Core\Entity\ModuleSource;
use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\ModuleAction;
use AdzerCMS\Core\Module\Operations;
use AdzerCMS\Core\Module\Sources;
use AdzerCMS\Core\Module\Tree;
use AdzerCMS\Core\Repository\ModuleOperationRepository;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
    private const MODULES = 'modules';
    private const SCOPE = 'scope';
    private const CATEGORY = 'category';
    private const OPERATIONS = 'operations';
    private const SOURCES = 'sources';
    private const TABS = [self::MODULES, self::SOURCES, self::OPERATIONS];

    public function __construct(
        private readonly AssembledModules $assembled,
        private readonly Catalog $catalog,
        private readonly Operations $operations,
        private readonly Sources $sources,
        private readonly Artifacts $artifacts,
        private readonly ModuleOperationRepository $filings,
        private readonly Tree $tree,
        private readonly SettingForm $form,
    ) {
    }

    #[Route('', name: 'admin_modules', methods: ['GET'])]
    public function modules(Request $request, TranslatorInterface $translator): Response
    {
        $asked = $request->query->getString('tab');
        $tab = \in_array($asked, self::TABS, true) ? $asked : self::MODULES;

        return $this->render('@Admin/admin/modules.html.twig', ['tabs' => self::TABS, 'tab' => $tab, ...match ($tab) {
            self::MODULES => $this->modulesTab($request, $translator),
            self::SOURCES => $this->sourcesTab($request),
            self::OPERATIONS => $this->operationsTab($request),
        }]);
    }

    /** @return array<string, mixed> */
    private function modulesTab(Request $request, TranslatorInterface $translator): array
    {
        $scopes = [];
        foreach (ModuleScope::cases() as $scope) {
            $scopes[$scope->value] = $scope->label();
        }
        $table = Table::of($request, 'admin_modules', [
            Column::live('name', t('admin.modules.name'), static fn (array $row): string => $row['name'].' '.$row['description']),
            Column::live('kind', t('admin.modules.kind'), static fn (array $row): string => $row['kind']),
            Column::live('version', t('admin.modules.version'), static fn (array $row): string => $row['version']),
            Column::live('repository', t('admin.modules.repository'), static fn (array $row): string => $row['repository'])->aside(),
            Column::live('state', t('admin.modules.status'), static fn (array $row): TranslatableInterface => $row['state']->label()),
            Column::plain('actions'),
        ], self::ORDER, t('admin.modules.empty'), filters: [
            Filter::tabs(self::SCOPE, t('admin.modules.scope.all'), $scopes),
            Filter::search(),
            Filter::cases(self::CATEGORY, t('admin.modules.category'), Category::cases()),
        ], live: true);
        $offered = $this->catalog->rows();
        $chosen = array_filter($offered['rows'], static fn (CatalogRow $row): bool => ModuleScope::tryFrom($table->value(self::SCOPE))?->admits($row) ?? true);

        return [
            'table' => $table,
            'rows' => $table->rows($this->offers($chosen, Category::tryFrom($table->value(self::CATEGORY)), $translator)),
            'taken' => $offered['at'],
            'versioned' => $this->tree->versioned(),
        ];
    }

    /** @return array<string, mixed> */
    private function sourcesTab(Request $request): array
    {
        $table = Table::of($request, 'admin_modules', [
            Column::plain('source', t('admin.sources.source')),
            Column::plain('access', t('admin.sources.access')),
            Column::plain('actions'),
        ], 'source', sticky: ['tab' => self::SOURCES], id: self::SOURCES);

        return ['table' => $table, 'rows' => $table->rows($this->origins($this->catalog->rows()['down']))];
    }

    /** @return array<string, mixed> */
    private function operationsTab(Request $request): array
    {
        $table = Table::of($request, 'admin_modules', [
            Column::plain('package', t('admin.operations.package')),
            Column::plain('action', t('admin.operations.action')),
            Column::plain('state', t('admin.operations.state')),
            Column::of('filed', t('admin.operations.filed'), 'o.id'),
            Column::plain('finished', t('admin.operations.finished'))->aside(),
        ], 'filed', t('admin.operations.empty'), ascending: false, sticky: ['tab' => self::OPERATIONS], id: self::OPERATIONS);

        return ['table' => $table, 'rows' => $this->filings->page($table)];
    }

    #[Route('/operation/{action}', name: 'admin_module_operation', methods: ['POST'])]
    public function operation(ModuleAction $action, Request $request): Response
    {
        $refusal = $this->operations->file($request->query->getString('package'), $action);
        $this->addFlash(null === $refusal ? 'success' : 'error', $refusal->reason ?? t('admin.operations.done'));

        return $this->redirectToRoute('admin_modules');
    }

    /**
     * @param array<CatalogRow> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function offers(array $rows, ?Category $category, TranslatorInterface $translator): array
    {
        $assembled = $this->assembled->modules();
        $opened = $this->filings->opened();
        $offers = [];
        foreach ($rows as $row) {
            if (null !== $category && !\in_array($category, $row->package->categories, true)) {
                continue;
            }
            $key = $row->selected && null !== $row->key ? strtolower($row->key) : null;
            $module = null === $key ? null : $assembled[$key] ?? null;
            $operation = $opened[$row->name] ?? null;
            $offers[] = [
                'name' => $row->name,
                'description' => null === $module ? $row->package->description : $module['description']->trans($translator),
                'link' => $row->package->homepage ?? $row->package->source,
                'card' => null === $module ? null : $this->generateUrl('admin_module', ['key' => $key]),
                'kind' => \is_string($row->kind) ? $row->kind : $row->kind->label()->trans($translator),
                'version' => (string) ($row->installed ?? $row->version),
                'verified' => (null === $row->installed ? $row->verified : $row->installedVerified) ? Badge::good(t('admin.modules.verified')) : null,
                'update' => $row->updatable ? $row->version : null,
                'update_verified' => $row->verified ? Badge::good(t('admin.modules.verified')) : null,
                'repository' => match ($row->repository) {
                    null => '',
                    Artifacts::REPOSITORY => $translator->trans('admin.sources.zip'),
                    default => (string) parse_url($row->repository, \PHP_URL_HOST),
                },
                'state' => $operation?->getState() ?? ModuleState::of($row),
                'operation' => $operation?->getAction()->label(),
                'actions' => [
                    ...null !== $module && [] !== $module['fields'] ? [['label' => t('admin.modules.settings'), 'href' => $this->generateUrl('admin_module', ['key' => $key]).'#settings']] : [],
                    ...array_map(static fn (ModuleAction $action): array => [
                        'label' => $action->label(),
                        'route' => 'admin_module_operation',
                        'params' => ['action' => $action->value, 'package' => $row->name],
                        'post' => true,
                        'confirm' => $action->takesVersion() && !$row->verified ? t('admin.modules.unverified', ['package' => $row->name]) : null,
                    ], null === $operation ? $this->operations->actions($row) : []),
                ],
            ];
        }

        return $offers;
    }

    /**
     * @param list<string> $down
     *
     * @return list<array{source: string, down: ?Badge, access: bool, credentials: ?int, drop: ?string, hidden: array<string, string>}>
     */
    private function origins(array $down): array
    {
        $origins = [];
        foreach (Sources::BASE as $url) {
            $origins[] = [
                'source' => $url,
                'down' => \in_array($url, $down, true) ? Badge::bad(t('admin.sources.down')) : null,
                'access' => false,
                'credentials' => null,
                'drop' => null,
                'hidden' => [],
            ];
        }
        foreach ($this->artifacts->archives() as $archive) {
            $origins[] = [
                'source' => $archive['name'].' '.$archive['version'],
                'down' => null,
                'access' => false,
                'credentials' => null,
                'drop' => $this->generateUrl('admin_module_zip_drop'),
                'hidden' => ['package' => $archive['name'], 'version' => $archive['normalized']],
            ];
        }
        foreach ($this->sources->own() as $source) {
            $origins[] = [
                'source' => $source->getUrl(),
                'down' => \in_array($source->getUrl(), $down, true) ? Badge::bad(t('admin.sources.down')) : null,
                'access' => null !== $source->getTokenCipher(),
                'credentials' => $source->getId(),
                'drop' => $this->generateUrl('admin_module_source_drop', ['id' => $source->getId()]),
                'hidden' => [],
            ];
        }

        return $origins;
    }

    #[Route('/sources/new', name: 'admin_module_source_new', methods: ['GET', 'POST'])]
    public function sourceNew(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('@Admin/admin/_source_new.html.twig');
        }

        $refusal = $this->sources->add($request->request->getString('url'));
        $this->addFlash(null === $refusal ? 'success' : 'error', $refusal->reason ?? t('admin.sources.added'));

        return $this->redirectToRoute('admin_modules', ['tab' => self::SOURCES]);
    }

    #[Route('/sources/zip', name: 'admin_module_zip', methods: ['GET', 'POST'])]
    public function zip(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('@Admin/admin/_source_zip.html.twig');
        }

        $file = $request->files->get('archive');
        $refusal = $file instanceof UploadedFile ? $this->artifacts->upload($file) : new Refusal(t('core.module.zip.unreadable'));
        $this->addFlash(null === $refusal ? 'success' : 'error', $refusal->reason ?? t('admin.sources.uploaded'));

        return $this->redirectToRoute('admin_modules', ['tab' => self::SOURCES]);
    }

    #[Route('/sources/zip/drop', name: 'admin_module_zip_drop', methods: ['POST'])]
    public function zipDrop(Request $request): Response
    {
        $this->artifacts->drop($request->request->getString('package'), $request->request->getString('version'));
        $this->addFlash('success', t('admin.sources.dropped'));

        return $this->redirectToRoute('admin_modules', ['tab' => self::SOURCES]);
    }

    #[Route('/sources/{id}/credentials', name: 'admin_module_source_credentials', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function sourceCredentials(ModuleSource $source, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if ($request->request->getBoolean('drop')) {
                $this->sources->revoke($source);
                $this->addFlash('success', t('admin.credentials.cleared'));
            } else {
                $this->sources->grant($source, $request->request->getString('login'), $request->request->getString('token'));
                $this->addFlash('success', t('admin.credentials.saved'));
            }

            return $this->redirectToRoute('admin_modules', ['tab' => self::SOURCES]);
        }

        return $this->render('@Theme/_credentials.html.twig', [
            'action' => $this->generateUrl('admin_module_source_credentials', ['id' => $source->getId()]),
            'fields' => [
                ['name' => 'login', 'label' => t('admin.sources.login'), 'secret' => false, 'value' => $source->getLogin()],
                ['name' => 'token', 'label' => t('admin.sources.token'), 'secret' => true, 'stored' => null !== $source->getTokenCipher()],
            ],
            'clearable' => null !== $source->getTokenCipher() || null !== $source->getLogin(),
        ]);
    }

    #[Route('/sources/{id}/drop', name: 'admin_module_source_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sourceDrop(ModuleSource $source): Response
    {
        $this->sources->drop($source);
        $this->addFlash('success', t('admin.sources.dropped'));

        return $this->redirectToRoute('admin_modules', ['tab' => self::SOURCES]);
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
