<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Admin\AssembledModules;
use AdzerCMS\Admin\ModuleScope;
use AdzerCMS\Admin\ModuleState;
use AdzerCMS\Admin\SettingPages;
use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Contracts\Ui\Badge;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Build\PreviewMark;
use AdzerCMS\Core\Composer\Artifacts;
use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\ModuleAction;
use AdzerCMS\Core\Module\Operations;
use AdzerCMS\Core\Module\OperationState;
use AdzerCMS\Core\Repository\ModuleOperationRepository;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Entries;
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
    private const ALL = 'all';
    private const STATUS = 'status';
    private const CATEGORY = 'category';
    private const OPERATIONS = 'operations';

    public function __construct(
        private readonly AssembledModules $assembled,
        private readonly Catalog $catalog,
        private readonly Operations $operations,
        private readonly ModuleOperationRepository $filings,
        private readonly PreviewMark $mark,
    ) {
    }

    #[Route('', name: 'admin_modules', methods: ['GET'])]
    public function modules(Request $request, TranslatorInterface $translator): Response
    {
        $catalog = $this->catalog->rows();
        $types = array_values(array_filter(ExtensionType::cases(), static fn (ExtensionType $type): bool => array_any($catalog['rows'], static fn (CatalogRow $row): bool => $type === $row->type)));
        $tabs = [
            self::ALL => ExtensionType::all(),
            ...array_combine(array_column($types, 'value'), array_map(static fn (ExtensionType $type): TranslatableInterface => $type->tab(), $types)),
            self::OPERATIONS => t('admin.operations.title'),
        ];
        $asked = $request->query->getString('tab');
        $tab = isset($tabs[$asked]) ? $asked : self::ALL;

        return $this->render('@Admin/admin/modules.html.twig', ['tabs' => $tabs, 'tab' => $tab, ...match ($tab) {
            self::OPERATIONS => $this->operationsTab($request),
            default => $this->catalogTab($request, $translator, $catalog['rows'], ExtensionType::tryFrom($tab)),
        }]);
    }

    /**
     * @param list<CatalogRow> $offered
     *
     * @return array<string, mixed>
     */
    private function catalogTab(Request $request, TranslatorInterface $translator, array $offered, ?ExtensionType $type): array
    {
        $table = Table::of($request, 'admin_modules', [
            Column::live('name', t('admin.modules.name'), static fn (array $row): string => $row['name'].' '.$row['description']),
            Column::live('version', t('admin.modules.version'), static fn (array $row): string => $row['version'])->aside(),
            Column::live('repository', t('admin.modules.repository'), static fn (array $row): string => $row['repository'])->aside(),
            Column::live('state', t('core.column.status'), static fn (array $row): TranslatableInterface => $row['state']->label()),
            Column::plain('actions'),
        ], '', t('admin.modules.empty'), filters: [
            Filter::search(),
            Filter::cases(self::STATUS, t('core.column.status'), ModuleScope::cases()),
            Filter::cases(self::CATEGORY, t('admin.modules.category'), Category::cases()),
        ], sticky: ['tab' => $type?->value], live: true);
        $scope = ModuleScope::tryFrom($table->value(self::STATUS));
        $chosen = array_filter($offered, static fn (CatalogRow $row): bool => (null === $type || $type === $row->type) && ($scope?->admits($row) ?? true));

        return [
            'table' => $table,
            'tiles' => ExtensionType::Theme === $type,
            'rows' => $table->rows($this->offers($chosen, $offered, Category::tryFrom($table->value(self::CATEGORY)), $type, $translator)),
        ];
    }

    /** @return array<string, mixed> */
    private function operationsTab(Request $request): array
    {
        $table = Table::of($request, 'admin_modules', [
            Column::of('package', t('admin.operations.package'), 'o.package'),
            Column::of('action', t('admin.operations.action'), 'o.action'),
            Column::of('state', t('core.column.status'), 'o.state'),
            Column::of('filed', t('admin.operations.filed'), 'o.id'),
            Column::of('finished', t('admin.operations.finished'), 'o.finished')->aside(),
        ], 'filed', t('admin.operations.empty'), ascending: false, filters: [
            Filter::cases('action', t('admin.operations.action'), ModuleAction::cases())->on('o.action'),
            Filter::cases('state', t('core.column.status'), OperationState::cases())->on('o.state'),
        ], sticky: ['tab' => self::OPERATIONS], id: self::OPERATIONS);

        return ['table' => $table, 'rows' => $this->filings->page($table)];
    }

    #[Route('/operation/{action}', name: 'admin_module_operation', methods: ['POST'])]
    public function operation(ModuleAction $action, Request $request): Response
    {
        $refusal = $this->operations->file($request->query->getString('package'), $action);
        $this->addFlash(null === $refusal ? 'success' : 'error', $refusal->reason ?? t('admin.operations.done'));

        return $this->redirectToRoute('admin_modules', ['tab' => ExtensionType::tryFrom($request->query->getString('tab'))?->value]);
    }

    #[Route('/theme/preview', name: 'admin_module_preview', methods: ['POST'])]
    public function preview(Request $request, Entries $entries): Response
    {
        $row = $this->catalog->row($request->query->getString('package'));
        if (null === $row || !$this->operations->previewed($row)) {
            $this->addFlash('error', t('core.module.refusal.action'));

            return $this->redirectToRoute('admin_modules', ['tab' => ExtensionType::Theme->value]);
        }

        $response = $this->redirect($entries->home());
        $this->mark->open($response, (string) $row->key);

        return $response;
    }

    /**
     * @param array<CatalogRow> $rows
     * @param list<CatalogRow>  $all
     *
     * @return list<array<string, mixed>>
     */
    private function offers(array $rows, array $all, ?Category $category, ?ExtensionType $type, TranslatorInterface $translator): array
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
            $replaced = Operations::replaced($row, $all);
            $offers[] = [
                'name' => $row->name,
                'description' => $row->package->description->trans($translator),
                'link' => $row->package->homepage ?? $row->package->source,
                'demo' => $row->demo,
                'card' => null === $module ? null : $this->generateUrl('admin_module', ['key' => $key]),
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
                    ...null !== $module && $module['settings'] ? [['label' => t('admin.modules.settings'), 'href' => $this->generateUrl(SettingPages::MODULE_ROUTE, ['key' => $key])]] : [],
                    ...null === $operation && $this->operations->previewed($row) ? [['label' => t('admin.modules.preview'), 'route' => 'admin_module_preview', 'params' => ['package' => $row->name], 'post' => true]] : [],
                    ...array_map(static fn (ModuleAction $action): array => [
                        'label' => $action->label(),
                        'route' => 'admin_module_operation',
                        'params' => ['action' => $action->value, 'package' => $row->name, 'tab' => $type?->value],
                        'post' => true,
                        'confirm' => match (true) {
                            $action->enables() && null !== $replaced => t('admin.modules.replace', [
                                'action' => $action->value,
                                'package' => $row->name,
                                'replaced' => $replaced,
                                'unverified' => $action->takesVersion() && !$row->verified ? 'yes' : 'no',
                            ]),
                            $action->takesVersion() && !$row->verified => t('admin.modules.unverified', ['package' => $row->name]),
                            default => null,
                        },
                    ], null === $operation ? $this->operations->actions($row) : []),
                ],
            ];
        }

        return $offers;
    }

    #[Route('/{key}', name: 'admin_module', methods: ['GET'])]
    public function module(string $key): Response
    {
        $module = $this->assembled->modules()[$key] ?? throw $this->createNotFoundException();

        return $this->render('@Admin/admin/module.html.twig', [
            'module' => $module,
            'key' => $key,
            'schema' => $this->assembled->schema($module['namespace']),
        ]);
    }
}
