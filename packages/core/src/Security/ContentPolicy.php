<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Contracts\Ui\ContentSource;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
final readonly class ContentPolicy
{
    private const HEADER = 'Content-Security-Policy';

    /** @param iterable<ContentSource> $sources */
    public function __construct(
        #[AutowireIterator(ContentSource::class)]
        private iterable $sources,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$event->isMainRequest() || $response->headers->has(self::HEADER)) {
            return;
        }

        $declared = [ContentSource::FORM => [], ContentSource::SCRIPT => [], ContentSource::CONNECT => [], ContentSource::FRAME => [], ContentSource::STYLE => []];
        foreach ($this->sources as $source) {
            foreach ($source->sources($event->getRequest()) as $directive => $origins) {
                $declared[$directive] = [...$declared[$directive], ...$origins];
            }
        }
        $nonce = ScriptNonce::of($event->getRequest());
        if (null !== $nonce) {
            $declared[ContentSource::SCRIPT][] = \sprintf("'nonce-%s'", $nonce);
        }
        $line = static fn (string $directive, string $base): string => implode(' ', array_unique(array_filter([$directive, $base, ...$declared[$directive]])));

        $response->headers->set(self::HEADER, implode('; ', [
            "default-src 'none'",
            "base-uri 'self'",
            $line(ContentSource::FORM, "'self'"),
            "frame-ancestors 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            $line(ContentSource::STYLE, "'self' 'unsafe-inline'"),
            $line(ContentSource::SCRIPT, "'self'"),
            $line(ContentSource::CONNECT, "'self'"),
            [] === $declared[ContentSource::FRAME] ? "frame-src 'none'" : $line(ContentSource::FRAME, ''),
        ]));
    }
}
