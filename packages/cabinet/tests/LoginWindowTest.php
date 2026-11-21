<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class LoginWindowTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const WINDOW = ['HTTP_HX-Request' => 'true'];

    /** @return iterable<string, array{string, string}> */
    public static function pages(): iterable
    {
        yield 'a page of the site' => ['/fixture/account?tab=one', 'http://localhost/fixture/account?tab=one'];
        yield 'a foreign address' => ['https://example.net/fixture/account', 'http://localhost/fixture/account'];
        yield 'no page' => ['', 'http://localhost/cabinet'];
    }

    #[DataProvider('pages')]
    #[Group('ACMS-CORE-1201')]
    #[Group('ACMS-UI-0176')]
    #[Group('ACMS-US-0002')]
    public function testWindowKeepsRefusalAndSignsInToPageOfItsLink(string $back, string $target): void
    {
        $this->record('79990000001', 'human@example.org');
        $this->client->disableReboot();

        $window = $this->fragment('/login'.('' === $back ? '' : '?back='.rawurlencode($back)));
        self::assertCount(1, $window->filter('.modal-header'), 'a fragment of the login page is a window');
        self::assertCount(1, $window->filter('.modal-body[hx-target="#record"] form[action="/login"]'), 'the form of the window stays in the window');

        $this->enter('wrong');
        self::assertResponseRedirects('/login');
        self::assertCount(1, $this->client->followRedirect()->filter('.modal-body .flash-error'), 'a refusal stays in the window');

        $this->enter(self::PASSWORD);
        self::assertResponseStatusCodeSame(204);
        self::assertResponseHeaderSame('HX-Redirect', $target);

        $this->fragment('/login');
        self::assertResponseHeaderSame('HX-Redirect', '/cabinet', 'a window of a signed in record leads to the cabinet');
    }

    private function enter(string $password): void
    {
        $this->client->request('POST', '/login', [
            '_username' => 'human@example.org',
            '_password' => $password,
            '_csrf_token' => $this->formToken('authenticate'),
        ], server: self::WINDOW);
    }
}
