<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Security;

use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Security\ContentPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ContentPolicyTest extends TestCase
{
    /** @return iterable<string, array{array<ContentSource::IMG|ContentSource::WORKER, list<string>>, list<string>}> */
    public static function declarations(): iterable
    {
        yield 'nothing declared' => [[], ["img-src 'self' data:"]];
        yield 'images and workers declared' => [
            [ContentSource::IMG => ['https://tiles.example'], ContentSource::WORKER => ['https://engine.example']],
            ["img-src 'self' data: https://tiles.example", 'worker-src https://engine.example'],
        ];
    }

    /**
     * @param array<ContentSource::IMG|ContentSource::WORKER, list<string>> $declared
     * @param list<string>                                                  $expected
     */
    #[DataProvider('declarations')]
    public function testDeclaredImageAndWorkerSourcesReachThePolicy(array $declared, array $expected): void
    {
        $source = new readonly class($declared) implements ContentSource {
            /** @param array<ContentSource::IMG|ContentSource::WORKER, list<string>> $declared */
            public function __construct(private array $declared)
            {
            }

            public function sources(Request $request): array
            {
                return $this->declared;
            }
        };
        $kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
        $event = new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, new Response());

        (new ContentPolicy([$source]))($event);

        $directives = array_map(trim(...), explode(';', (string) $event->getResponse()->headers->get('Content-Security-Policy')));
        self::assertSame($expected, array_values(array_filter($directives, static fn (string $directive): bool => str_starts_with($directive, 'img-src') || str_starts_with($directive, 'worker-src'))));
    }
}
