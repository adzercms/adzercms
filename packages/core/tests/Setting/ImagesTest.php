<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Setting;

use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\Setting\Fixture\Shop;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

final class ImagesTest extends SchemaTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    #[Group('ACMS-CORE-0061')]
    #[Group('ACMS-US-0004')]
    public function testImageByPathIsServedSandboxedAndRemovedWithFile(): void
    {
        $set = self::command('adzercms:setting');
        self::assertSame(Command::SUCCESS, $set->execute(['name' => Brand::LOGO, 'value' => $this->upload((string) base64_decode(self::PNG))]));

        $this->client->request('GET', '/setting/'.Brand::LOGO);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertStringContainsString('sandbox', (string) $this->client->getResponse()->headers->get('Content-Security-Policy'));

        $stored = self::parameter('adzercms.files_dir').'/settings/'.self::service(Settings::class)->get(Brand::LOGO);
        self::assertFileExists($stored);

        self::command('adzercms:setting')->execute(['name' => Brand::LOGO, 'value' => '']);
        self::assertFileDoesNotExist($stored);

        $this->client->request('GET', '/setting/'.Brand::LOGO);
        self::assertResponseStatusCodeSame(404);
    }

    #[Group('ACMS-CORE-0061')]
    public function testFileOfOtherTypeIsRefused(): void
    {
        $set = self::command('adzercms:setting');

        self::assertSame(Command::INVALID, $set->execute(['name' => Brand::ICON, 'value' => $this->upload('not an image')]));
        self::assertStringContainsString(self::phrase('core.image.type', ['field' => self::phrase('core.setting.icon')]), $set->getDisplay());
        self::assertSame('', self::service(Settings::class)->get(Brand::ICON));
    }

    #[Group('ACMS-CORE-1058')]
    public function testPhotoOfAnySizeIsKeptWithinItsSideAsWebp(): void
    {
        $canvas = new \Imagick();
        $canvas->newImage(3000, 2000, 'gray');
        $canvas->addNoiseImage(\Imagick::NOISE_RANDOM);
        $canvas->setImageFormat('jpeg');
        $photo = $this->upload($canvas->getImageBlob());
        self::assertGreaterThan(512000, filesize($photo));

        $set = self::command('adzercms:setting');
        self::assertSame(Command::SUCCESS, $set->execute(['name' => Shop::PORTRAIT, 'value' => $photo]));

        $name = self::service(Settings::class)->get(Shop::PORTRAIT);
        $stored = new \Imagick(self::parameter('adzercms.files_dir').'/settings/'.$name);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.webp$/', $name);
        self::assertSame(['WEBP', 1920, 1280], [$stored->getImageFormat(), $stored->getImageWidth(), $stored->getImageHeight()]);
        $this->client->request('GET', '/setting/'.Shop::PORTRAIT);
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        self::assertSame(Command::INVALID, $set->execute(['name' => Shop::PORTRAIT, 'value' => $this->upload('not a photo')]));
        self::assertSame($name, self::service(Settings::class)->get(Shop::PORTRAIT));

        $set->execute(['name' => Shop::PORTRAIT, 'value' => '']);
        self::assertFileDoesNotExist(self::parameter('adzercms.files_dir').'/settings/'.$name);
    }

    private function upload(string $body): string
    {
        $path = self::parameter('adzercms.files_dir').'/upload/'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($path, $body);

        return $path;
    }
}
