<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Admin\SettingForm;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Ui\Back;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/settings')]
#[IsGranted(Role::ADMIN)]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly SettingForm $form,
        private readonly Channels $channels,
    ) {
    }

    #[Route('', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $groups = $this->catalog->groups();
        $tab = (string) $request->query->get('tab', array_key_first($groups));

        if ($request->isMethod('POST')) {
            $tab = (string) $request->request->get('tab', array_key_first($groups));

            if ($this->form->save($groups[$tab]['fields'] ?? [], $request)) {
                $this->addFlash('success', t('admin.settings.saved'));
            }

            foreach ($this->form->faults() as $fault) {
                $this->addFlash('error', $fault);
            }

            return $this->redirectToRoute('admin_settings', ['tab' => $tab]);
        }

        if (!isset($groups[$tab])) {
            $tab = array_key_first($groups) ?? throw new \LogicException('the settings form has no group');
        }

        return $this->render('@Admin/admin/settings.html.twig', [
            'groups' => $groups,
            'tab' => $tab,
            'values' => $this->form->values($groups[$tab]['fields']),
            'sections' => $this->form->sections($groups[$tab]['fields']),
        ]);
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
                'name' => sprintf('value[%s]', $field->name),
                'label' => $field->label,
                'secret' => Type::Secret === $field->type,
                'stored' => Type::Secret === $field->type && $this->form->filled([$field]),
                'value' => $values[$field->name],
            ], $fields),
            'clearable' => $this->form->filled($fields),
        ]);
    }
}
