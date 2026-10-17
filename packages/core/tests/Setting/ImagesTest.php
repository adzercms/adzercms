<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Setting;

use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\SchemaTestCase;
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
        self::assertStringContainsString('Icon accepts a png, jpeg, svg, webp or ico image', $set->getDisplay());
        self::assertSame('', self::service(Settings::class)->get(Brand::ICON));
    }

    private function upload(string $body): string
    {
        $path = self::parameter('adzercms.files_dir').'/upload/'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($path, $body);

        return $path;
    }
}
