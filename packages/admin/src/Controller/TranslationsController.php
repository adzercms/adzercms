<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Text\Texts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/references/translations')]
#[IsGranted(Role::ADMIN)]
final class TranslationsController extends AbstractController
{
    public const string CHANGED = 'admin-translations';

    public function __construct(
        private readonly Texts $texts,
    ) {
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
