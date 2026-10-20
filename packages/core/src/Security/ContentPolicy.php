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

        $declared = [ContentSource::FORM => [], ContentSource::SCRIPT => [], ContentSource::CONNECT => [], ContentSource::FRAME => [], ContentSource::STYLE => [], ContentSource::IMG => [], ContentSource::WORKER => [], ContentSource::FRAME_ANCESTORS => []];
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

        $response->headers->set(self::HEADER, implode('; ', array_filter([
            "default-src 'none'",
            "base-uri 'self'",
            $line(ContentSource::FORM, "'self'"),
            $line(ContentSource::FRAME_ANCESTORS, "'self'"),
            $line(ContentSource::IMG, "'self' data:"),
            "font-src 'self'",
            $line(ContentSource::STYLE, "'self' 'unsafe-inline'"),
            $line(ContentSource::SCRIPT, "'self'"),
            $line(ContentSource::CONNECT, "'self'"),
            [] === $declared[ContentSource::FRAME] ? "frame-src 'none'" : $line(ContentSource::FRAME, ''),
            [] === $declared[ContentSource::WORKER] ? null : $line(ContentSource::WORKER, ''),
        ])));
    }
}
