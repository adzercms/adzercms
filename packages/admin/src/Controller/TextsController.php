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

#[Route('/admin/texts')]
#[IsGranted(Role::ADMIN)]
final class TextsController extends AbstractController
{
    public const string CHANGED = 'admin-texts';
    private const ORDER = 'message';

    public function __construct(
        private readonly Texts $texts,
        private readonly Language $language,
    ) {
    }

    #[Route('', name: 'admin_texts', methods: ['GET'])]
    public function texts(Request $request): Response
    {
        $locale = $this->language->instance();
        $stored = $this->texts->stored($locale);
        $rows = [];

        foreach ($this->texts->catalogue($locale)->all() as $domain => $messages) {
            foreach ($messages as $message => $catalog) {
                $rows[] = ['domain' => $domain, 'message' => (string) $message, 'text' => $stored[$domain][$message] ?? $catalog, 'stored' => isset($stored[$domain][$message])];
            }
        }

        $table = Table::of($request, 'admin_texts', [
            Column::live('text', t('admin.texts.text'), static fn (array $row): string => $row['text']),
            Column::live('message', t('admin.texts.message'), static fn (array $row): string => $row['message'])->aside(),
        ], self::ORDER, t('admin.texts.empty'), filters: [Filter::search()], live: true);

        return $this->render('@Admin/admin/texts.html.twig', ['table' => $table, 'rows' => $table->rows($rows), 'changed' => self::CHANGED]);
    }

    #[Route('/{domain}/{message}', name: 'admin_text', requirements: ['message' => '[^/]+'], methods: ['GET', 'POST'])]
    public function text(string $domain, string $message, Request $request): Response
    {
        $locale = $this->language->instance();
        $catalogue = $this->texts->catalogue($locale);

        if (!$catalogue->defines($message, $domain)) {
            throw $this->createNotFoundException();
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $error = $this->texts->save($locale, $domain, $message, $request->request->has('reset') ? '' : $request->request->getString('value'))?->reason;
        }

        $stored = $this->texts->stored($locale)[$domain][$message] ?? null;
        $response = $this->render('@Admin/admin/_text.html.twig', [
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
