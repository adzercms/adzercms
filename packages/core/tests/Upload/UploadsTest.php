<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Upload;

use AdzerCMS\Core\Upload\Uploads;
use PHPUnit\Framework\Attributes\Group;
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

    #[Group('ACMS-UI-0105')]
    public function testSweepRemovesUploadsPastTheTerm(): void
    {
        $uploads = new Uploads($this->directory);
        $stale = (string) $uploads->keep($this->file('stale.txt'), 'file', 'session');
        $fresh = (string) $uploads->keep($this->file('fresh.txt'), 'file', 'session');
        foreach (glob($this->directory.'/*/'.$stale.'*') ?: [] as $path) {
            touch($path, (int) strtotime('-2 days'));
        }

        self::assertSame(1, $uploads->sweep());
        self::assertNull($uploads->take($stale, 'session'));
        $taken = $uploads->take($fresh, 'session');
        self::assertNotNull($taken);
        self::assertSame('fresh.txt', $taken[1]->getClientOriginalName());
    }

    #[Group('ACMS-UI-0105')]
    public function testOwnerKeepsNoMoreUploadsThanThePendingCap(): void
    {
        $uploads = new Uploads($this->directory);
        for ($index = 0; $index < Uploads::PENDING; ++$index) {
            self::assertNotNull($uploads->keep($this->file($index.'.txt'), 'file', 'session'));
        }

        self::assertNull($uploads->keep($this->file('over.txt'), 'file', 'session'));
        self::assertNotNull($uploads->keep($this->file('other.txt'), 'file', 'another session'));
    }

    private function file(string $name): UploadedFile
    {
        $path = $this->directory.'-source/'.$name;
        (new Filesystem())->dumpFile($path, $name);

        return new UploadedFile($path, $name, 'text/plain', null, true);
    }
}
