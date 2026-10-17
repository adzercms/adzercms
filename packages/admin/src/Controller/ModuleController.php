<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Admin\ModuleCatalog;
use AdzerCMS\Admin\SettingForm;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/modules')]
#[IsGranted(Role::ADMIN)]
final class ModuleController extends AbstractController
{
    private const ORDER = 'name';

    public function __construct(
        private readonly ModuleCatalog $catalog,
        private readonly SettingForm $form,
    ) {
    }

    #[Route('', name: 'admin_modules', methods: ['GET'])]
    public function modules(Request $request, TranslatorInterface $translator): Response
    {
        $rows = [];

        foreach ($this->catalog->modules() as $key => $module) {
            $rows[] = [
                'key' => $key,
                'name' => $module['name'],
                'kind' => $module['kind']->trans($translator),
                'schema' => (string) $this->catalog->schema($module['namespace']),
            ];
        }

        $table = Table::of($request, 'admin_modules', [
            Column::live('name', t('admin.modules.name'), static fn (array $row): string => $row['name']),
            Column::live('kind', t('admin.modules.kind'), static fn (array $row): string => $row['kind']),
            Column::live('schema', t('admin.modules.schema'), static fn (array $row): string => $row['schema'])->aside(),
        ], self::ORDER, t('admin.modules.empty'), filters: [Filter::search()], live: true);

        return $this->render('@Admin/admin/modules.html.twig', ['table' => $table, 'rows' => $table->rows($rows)]);
    }

    #[Route('/{key}', name: 'admin_module', methods: ['GET', 'POST'])]
    public function module(string $key, Request $request): Response
    {
        $module = $this->catalog->modules()[$key] ?? throw $this->createNotFoundException();
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
            'schema' => $this->catalog->schema($module['namespace']),
            'values' => $this->form->values($fields),
            'sections' => $this->form->sections($fields),
        ]);
    }
}
