<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Upload;

use AdzerCMS\Core\Upload\Uploads;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/uploads-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->directory, $this->directory.'-source']);
    }

    public function testSweepRemovesUploadsPastTheTerm(): void
    {
        $uploads = new Uploads($this->directory);
        $stale = $uploads->keep($this->file('stale.txt'), 'file', 'session');
        $fresh = $uploads->keep($this->file('fresh.txt'), 'file', 'session');
        foreach ([$stale, $stale.'.json'] as $name) {
            touch($this->directory.'/'.$name, strtotime('-2 days'));
        }

        self::assertSame(1, $uploads->sweep());
        self::assertNull($uploads->take($stale, 'session'));
        self::assertSame('fresh.txt', $uploads->take($fresh, 'session')[1]->getClientOriginalName());
    }

    private function file(string $name): UploadedFile
    {
        $path = $this->directory.'-source/'.$name;
        (new Filesystem())->dumpFile($path, $name);

        return new UploadedFile($path, $name, 'text/plain', null, true);
    }
}
