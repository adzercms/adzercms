<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Http;

use AdzerCMS\Core\Http\ImmutableFileResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

final class ImmutableFileResponseTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/adzercms-immutable-'.bin2hex(random_bytes(4)).'.webp';
        (new Filesystem())->dumpFile($this->path, 'photo');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->path);
    }

    #[Group('ACMS-CORE-0983')]
    public function testFileIsCachedForeverAndItsTagAnswersNotModified(): void
    {
        $first = new ImmutableFileResponse($this->path, new Request());
        $again = new ImmutableFileResponse($this->path, new Request(server: ['HTTP_IF_NONE_MATCH' => (string) $first->getEtag()]));

        self::assertSame([200, true, true], [$first->getStatusCode(), $first->headers->hasCacheControlDirective('public'),
            $first->headers->hasCacheControlDirective('immutable')]);
        self::assertSame(304, $again->getStatusCode(), 'a browser holding the file is answered without it');
    }
}
