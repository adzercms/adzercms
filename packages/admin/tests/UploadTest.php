<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Upload\Uploads;
use PHPUnit\Framework\Attributes\Group;

final class UploadTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    #[Group('ACMS-UI-0085')]
    public function testUploadedFileReachesTheFormOfItsOwnSessionByKey(): void
    {
        $admin = $this->record('79990000001')->setRole(Role::Admin);
        $this->em->flush();
        $this->client->loginUser($admin);

        $this->post('/upload', ['field' => \sprintf('file[%s]', Brand::LOGO)], ['file' => $this->picture('mark.png')]);
        self::assertResponseIsSuccessful();
        $key = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['key'];
        $form = ['tab' => Tab::Brand->value, Uploads::FIELD => [$key]];

        $stranger = $this->browser('Stranger', '192.0.2.7');
        $stranger->loginUser($admin);
        $stranger->request('POST', '/admin/settings', $form + [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID, $stranger)]);
        self::assertSame('', self::service(Settings::class)->get(Brand::LOGO), 'the key of another session brings no file');

        $this->post('/admin/settings', $form);
        self::assertResponseRedirects();
        self::assertNotSame('', self::service(Settings::class)->get(Brand::LOGO));
    }
}
