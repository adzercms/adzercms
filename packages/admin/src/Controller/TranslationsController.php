<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Setting\Language;
use AdzerCMS\Core\Text\Texts;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/translations')]
#[IsGranted(Role::ADMIN)]
final class TranslationsController extends AbstractController
{
    public const string CHANGED = 'admin-translations';
    private const ORDER = 'message';
    private const LANGUAGE = 'language';

    public function __construct(
        private readonly Texts $texts,
        private readonly Language $language,
    ) {
    }

    #[Route('', name: 'admin_translations', methods: ['GET'])]
    public function translations(Request $request): Response
    {
        $language = Filter::choice(self::LANGUAGE, $this->language->choices(), $this->language->instance());
        $table = Table::of($request, 'admin_translations', [
            Column::live('text', t('admin.translations.text'), static fn (array $row): string => $row['text']),
            Column::live('message', t('admin.translations.message'), static fn (array $row): string => $row['message'])->aside(),
        ], self::ORDER, t('admin.translations.empty'), filters: [Filter::search(), $language], live: true);

        $locale = $language->pick($table->value(self::LANGUAGE));
        $stored = $this->texts->stored($locale);
        $rows = [];

        foreach ($this->texts->catalogue($locale)->all() as $domain => $messages) {
            foreach ($messages as $message => $catalog) {
                $rows[] = ['domain' => $domain, 'message' => (string) $message, 'text' => $stored[$domain][$message] ?? $catalog, 'stored' => isset($stored[$domain][$message])];
            }
        }

        return $this->render('@Admin/admin/translations.html.twig', ['table' => $table, 'rows' => $table->rows($rows), 'locale' => $locale, 'changed' => self::CHANGED]);
    }

    #[Route('/{locale}/{domain}/{message}', name: 'admin_translation', requirements: ['message' => '[^/]+'], methods: ['GET', 'POST'])]
    public function translation(string $locale, string $domain, string $message, Request $request): Response
    {
        $catalogue = $this->texts->catalogue($locale);

        if (!$catalogue->defines($message, $domain)) {
            throw $this->createNotFoundException();
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $error = $this->texts->save($locale, $domain, $message, $request->request->has('reset') ? '' : $request->request->getString('value'))?->reason;
        }

        $stored = $this->texts->stored($locale)[$domain][$message] ?? null;
        $response = $this->render('@Admin/admin/_translation.html.twig', [
            'locale' => $locale,
            'domain' => $domain,
            'message' => $message,
            'catalog' => $catalogue->get($message, $domain),
            'value' => null === $error ? $stored ?? $catalogue->get($message, $domain) : $request->request->getString('value'),
            'stored' => null !== $stored,
            'error' => $error,
        ]);

        if ($request->isMethod('POST') && null === $error) {
            $response->headers->set('HX-Trigger', self::CHANGED);
        }

        return $response;
    }
}
