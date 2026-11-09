<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Setting;

use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\Setting\Fixture\Shop;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[Group('ACMS-UI-0007')]
    #[DataProvider('cards')]
    public function testLinkCardIsLogoInMiddleOfBackgroundItStandsOutOn(string $fill, string $mark, string $background): void
    {
        self::assertNull(self::service(Brand::class)->card());
        $this->client->request('GET', '/brand.png');
        self::assertResponseStatusCodeSame(404);

        $logo = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="10"><rect width="40" height="10" fill="'.$fill.'"/></svg>';
        self::assertSame(Command::SUCCESS, self::command('adzercms:setting')->execute(['name' => Brand::LOGO, 'value' => $this->upload($logo)]));
        self::assertSame('http://localhost/brand.png', self::service(Brand::class)->card());

        $this->client->request('GET', '/brand.png');
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        $card = new \Imagick();
        $card->readImageBlob((string) $this->client->getResponse()->getContent());
        $color = static fn (int $x, int $y): string => $card->getImagePixelColor($x, $y)->getColorAsString();
        self::assertSame(['PNG', 1200, 630], [$card->getImageFormat(), $card->getImageWidth(), $card->getImageHeight()]);
        self::assertSame([$background, $mark, $mark, $background], [$color(119, 315), $color(121, 315), $color(1078, 315), $color(600, 190)]);

        $this->client->request('GET', '/brand.png', server: ['HTTP_IF_MODIFIED_SINCE' => $this->client->getResponse()->headers->get('Last-Modified')]);
        self::assertResponseStatusCodeSame(304);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function cards(): iterable
    {
        yield 'dark logo on white' => ['#000', 'srgb(0,0,0)', 'srgb(255,255,255)'];
        yield 'white logo on dark' => ['#fff', 'srgb(255,255,255)', 'srgb(26,26,26)'];
        yield 'text color of a logo' => ['currentColor', 'srgb(26,26,26)', 'srgb(255,255,255)'];
        yield 'scheme color of a logo' => ['var(--primary, #0b5cd5)', 'srgb(11,92,213)', 'srgb(255,255,255)'];
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
