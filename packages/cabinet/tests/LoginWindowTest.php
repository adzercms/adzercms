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

    private const PAGE = 'http://localhost/fixture/account?tab=one';

    /** @return iterable<string, array{string, string, string, string}> */
    public static function pages(): iterable
    {
        yield 'the page under the window' => ['/fixture/account?tab=one', self::PAGE, 'HX-Refresh', 'true'];
        yield 'a page apart from the window' => ['/fixture/account?tab=one', 'http://localhost/', 'HX-Redirect', self::PAGE];
        yield 'a foreign address' => ['https://example.net/fixture/account', 'http://localhost/', 'HX-Redirect', 'http://localhost/fixture/account'];
        yield 'no page' => ['', 'http://localhost/', 'HX-Redirect', 'http://localhost/cabinet'];
    }

    #[DataProvider('pages')]
    #[Group('ACMS-CORE-1201')]
    #[Group('ACMS-UI-0176')]
    #[Group('ACMS-US-0002')]
    public function testWindowKeepsRefusalAndSignsInToPageOfItsLink(string $back, string $current, string $header, string $value): void
    {
        $this->record('79990000001', 'human@example.org');
        $this->client->disableReboot();

        $window = $this->fragment('/login'.('' === $back ? '' : '?back='.rawurlencode($back)));
        self::assertCount(1, $window->filter('.modal-header'), 'a fragment of the login page is a window');
        self::assertCount(1, $window->filter('.modal-body[hx-target="#record"] form[action="/login"]'), 'the form of the window stays in the window');

        $this->enter('wrong', $current);
        self::assertResponseRedirects('/login');
        self::assertCount(1, $this->client->followRedirect()->filter('.modal-body .flash-error'), 'a refusal stays in the window');

        $this->enter(self::PASSWORD, $current);
        self::assertResponseStatusCodeSame(204);
        self::assertResponseHeaderSame($header, $value);

        $this->fragment('/login');
        self::assertResponseHeaderSame('HX-Redirect', '/cabinet', 'a window of a signed in record leads to the cabinet');
    }

    private function enter(string $password, string $current): void
    {
        $this->client->request('POST', '/login', [
            '_username' => 'human@example.org',
            '_password' => $password,
            '_csrf_token' => $this->formToken('authenticate'),
        ], server: self::WINDOW + ['HTTP_HX-Current-URL' => $current]);
    }
}
