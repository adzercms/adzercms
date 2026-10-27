<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Build\PreviewMark;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Entries;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class PreviewController
{
    public function __construct(
        private PreviewMark $mark,
        private Entries $entries,
    ) {
    }

    #[Route('/preview/exit', name: 'preview_exit', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $response = new RedirectResponse(Back::of($request, $this->entries->home()));
        $this->mark->close($response);

        return $response;
    }
}
