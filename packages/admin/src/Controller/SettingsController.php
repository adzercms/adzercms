<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Admin\SettingForm;
use AdzerCMS\Admin\SettingPages;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Page;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Ui\Back;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/settings')]
#[IsGranted(Role::ADMIN)]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly SettingPages $pages,
        private readonly SettingForm $form,
        private readonly Channels $channels,
    ) {
    }

    #[Route('/{page?}', name: 'admin_settings', requirements: ['page' => new EnumRequirement(Page::class)], methods: ['GET', 'POST'])]
    public function system(?Page $page, Request $request): Response
    {
        $system = $this->catalog->system();
        $page ??= Page::from((string) array_key_first($system));
        $fields = $system[$page->value] ?? throw $this->createNotFoundException();

        return $this->page($request, $fields, $page->label(), [], $this->pages->menu($page, null), $this->generateUrl('admin_settings', ['page' => $page->value]));
    }

    #[Route('/modules/{key}', name: SettingPages::MODULE_ROUTE, methods: ['GET', 'POST'])]
    public function module(string $key, Request $request): Response
    {
        $fields = $this->catalog->modules()[$key]['fields'] ?? throw $this->createNotFoundException();
        $title = $this->pages->title($key) ?? throw $this->createNotFoundException();

        return $this->page($request, $fields, $title, $this->pages->guide($key), $this->pages->menu(null, $key), $this->generateUrl(SettingPages::MODULE_ROUTE, ['key' => $key]));
    }

    #[Route('/credentials/{key}', name: 'admin_settings_credentials', methods: ['GET', 'POST'])]
    public function credential(string $key, Request $request): Response
    {
        $fields = $this->catalog->credential($key);

        if ([] === $fields) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $values = (array) $request->request->all('value');
            $drop = $request->request->getBoolean('drop');
            $kept = true;

            foreach ($fields as $field) {
                if ($drop) {
                    $this->form->clear($field);

                    continue;
                }

                $kept = $this->form->store($field, $values[$field->name] ?? null) && $kept;
            }

            $this->form->flush();

            foreach ($this->form->faults() as $fault) {
                $this->addFlash('error', $fault);
            }

            if ($kept) {
                $this->addFlash('success', t($drop ? 'admin.credentials.cleared' : 'admin.credentials.saved'));
            }

            foreach ($this->channels->subscribe() as $bot => $failure) {
                $this->addFlash('warning', t('admin.credentials.webhook', ['bot' => $bot, 'failure' => $failure]));
            }

            return $this->redirect(Back::of($request, $this->generateUrl('admin_settings')));
        }

        $values = $this->form->values($fields);

        return $this->render('@Theme/_credentials.html.twig', [
            'action' => $this->generateUrl('admin_settings_credentials', ['key' => $key]),
            'fields' => array_map(fn (Field $field): array => [
                'name' => \sprintf('value[%s]', $field->name),
                'label' => $field->label,
                'secret' => Type::Secret === $field->type,
                'stored' => Type::Secret === $field->type && $this->form->filled([$field]),
                'value' => $values[$field->name],
            ], $fields),
            'clearable' => $this->form->filled($fields),
        ]);
    }

    /**
     * @param list<Field>                 $fields
     * @param list<TranslatableInterface> $guide
     * @param list<mixed>                 $menu
     */
    private function page(Request $request, array $fields, TranslatableInterface $label, array $guide, array $menu, string $here): Response
    {
        if ($request->isMethod('POST')) {
            if ($this->form->save($fields, $request)) {
                $this->addFlash('success', t('admin.settings.saved'));
            }

            foreach ($this->form->faults() as $fault) {
                $this->addFlash('error', $fault);
            }

            return $this->redirect($here);
        }

        return $this->render('@Admin/admin/settings.html.twig', [
            'menu' => $menu,
            'label' => $label,
            'guide' => $guide,
            'values' => $this->form->values($fields),
            'sections' => $this->form->sections($fields),
        ]);
    }
}
