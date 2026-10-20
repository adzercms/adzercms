<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Analytics;

use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Account\Consents;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Analytics\Fixture\Tally\TallyCounter;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class ConsentTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;

    private const FRAME = '/fixture/frame';
    private const POLICY = 'https://site.test/privacy';
    private const SITE = 'shop';

    /** @return iterable<string, array{bool, ?Role, bool, bool}> */
    public static function viewers(): iterable
    {
        yield 'counter switched off' => [false, null, false, false];
        yield 'visitor' => [true, null, false, true];
        yield 'administrator' => [true, Role::Admin, false, false];
        yield 'administrator under an impersonated record' => [true, Role::Admin, true, false];
    }

    #[DataProvider('viewers')]
    #[Group('ACMS-CORE-0948')]
    #[Group('ACMS-CORE-0949')]
    #[Group('ACMS-US-0036')]
    #[Group('ACMS-US-0037')]
    public function testWindowAndCounterReachOnlyVisitors(bool $enabled, ?Role $role, bool $impersonating, bool $shown): void
    {
        $this->configure([Consents::PRIVACY_URL => self::POLICY] + ($enabled ? [
            TallyCounter::SETTINGS.Switches::ENABLED => '1',
            TallyCounter::SETTINGS.TallyCounter::SITE => self::SITE,
        ] : []));
        $viewer = $this->record('+7 900 000-00-03');
        if (null !== $role) {
            $viewer->setRole($role);
        }
        $record = $this->record('+7 900 000-00-04');
        $this->em->flush();
        $this->client->loginUser($viewer);

        $page = $this->arrive(self::FRAME.($impersonating ? '?_switch_user='.$record->getUserIdentifier() : ''));

        $window = $page->filter('script[data-consent]');
        $config = 0 === $window->count() ? null : json_decode((string) $window->attr('data-consent'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(
            $shown ? [['entry' => TallyCounter::ENTRY, 'settings' => ['site' => self::SITE], 'cookies' => TallyCounter::COOKIES]] : null,
            $config['counters'] ?? null,
        );
        if ($shown) {
            self::assertStringContainsString('Tally &amp; Co', $config['texts']['description']);
            self::assertStringContainsString('href="'.self::POLICY.'"', $config['texts']['description']);
        }

        $policy = self::directives((string) $this->client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertSame(
            $shown ? ["'self'", TallyCounter::ORIGIN] : ["'self'"],
            array_values(array_filter($policy[ContentSource::SCRIPT], static fn (string $source): bool => !str_starts_with($source, "'nonce-"))),
        );
        self::assertSame($shown ? ["'self'", TallyCounter::ORIGIN] : ["'self'"], $policy[ContentSource::CONNECT]);
        self::assertSame($shown ? ["'self'", TallyCounter::BOARD] : ["'self'"], $policy[ContentSource::FRAME_ANCESTORS]);
    }

    /** @return array<string, list<string>> */
    private static function directives(string $policy): array
    {
        $directives = [];
        foreach (array_filter(array_map(trim(...), explode(';', $policy))) as $directive) {
            $parts = explode(' ', $directive);
            $directives[array_shift($parts)] = $parts;
        }

        return $directives;
    }
}
