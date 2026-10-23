<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Photo;

use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Core\Tests\Container;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

final class PhotosTest extends KernelTestCase
{
    use Container;

    private const PLACE = 'photos-test';

    protected function tearDown(): void
    {
        (new Filesystem())->remove([self::parameter('adzercms.files_dir').'/'.self::PLACE, self::parameter('adzercms.files_dir').'/scratch']);
        parent::tearDown();
    }

    #[Group('ACMS-CORE-0984')]
    public function testPhotoStandsUprightWithinItsSideAsWebpWithoutMetadataAndLeaves(): void
    {
        $photos = self::service(Photos::class, 'test.'.Photos::class);
        $canvas = new \Imagick();
        $canvas->newImage(300, 100, 'red');
        $canvas->setImageFormat('jpeg');
        $jpeg = $canvas->getImageBlob();
        $exif = "Exif\0\0MM\0\x2A\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0\x06\0\0\0\0\0\0";

        $name = $photos->store($this->scratch('turned.jpg', substr($jpeg, 0, 2)."\xFF\xE1".pack('n', \strlen($exif) + 2).$exif.substr($jpeg, 2)), self::PLACE, 150);

        $path = $photos->path(self::PLACE, $name) ?? self::fail('the photo is stored');
        $stored = new \Imagick($path);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.webp$/', $name);
        self::assertSame(['WEBP', 50, 150, []], [$stored->getImageFormat(), $stored->getImageWidth(), $stored->getImageHeight(), $stored->getImageProfiles('exif')]);

        $photos->drop(self::PLACE, [$name]);
        self::assertFileDoesNotExist($path);
    }

    #[Group('ACMS-CORE-0984')]
    public function testFileOtherThanPictureIsRefusedByName(): void
    {
        $refusal = Photos::refusal([$this->scratch('price.txt', 'price list')]) ?? self::fail('the text is refused');

        self::assertSame(self::phrase('core.photo.type', ['name' => 'price.txt']), self::phrase($refusal->reason));
    }

    private function scratch(string $name, string $content): File
    {
        $path = self::parameter('adzercms.files_dir').'/scratch/'.$name;
        (new Filesystem())->dumpFile($path, $content);

        return new File($path);
    }
}
