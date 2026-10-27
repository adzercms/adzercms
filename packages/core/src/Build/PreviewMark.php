<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Build;

use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Core\Kernel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Clock\now;

final readonly class PreviewMark implements Fragment
{
    public function __construct(
        #[Autowire(service: 'kernel')]
        private Kernel $kernel,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function ready(): ?string
    {
        return $this->kernel->preview()->read()['theme'] ?? null;
    }

    public function open(Response $response, string $theme): void
    {
        $until = now()->getTimestamp() + Preview::TERM;
        $response->headers->setCookie(Cookie::create(Preview::COOKIE, Preview::mark($theme, $until, $this->secret), $until));
    }

    public function close(Response $response): void
    {
        $response->headers->clearCookie(Preview::COOKIE);
    }

    public function place(): string
    {
        return self::END;
    }

    public function fragment(?int $subject): ?array
    {
        $theme = $this->kernel->previewed();

        return null === $theme ? null : ['template' => '@Core/preview/bar.html.twig', 'context' => ['theme' => $theme]];
    }
}
