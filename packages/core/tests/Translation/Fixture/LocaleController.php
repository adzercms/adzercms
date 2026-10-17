<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Translation\Fixture;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
final class LocaleController
{
    #[Route('/fixture/locale', name: 'fixture_locale', methods: ['GET'])]
    public function locale(Request $request, TranslatorInterface $translator): Response
    {
        return new Response($request->getLocale().' '.$translator->getLocale());
    }
}
