<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Translation;

use AdzerCMS\Core\Setting\Language;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\SplitHosts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class UnroutedLanguageTest extends SchemaTestCase
{
    use Configuration;
    use SplitHosts;

    /** @return iterable<string, array{string, string, string, string}> */
    public static function hosts(): iterable
    {
        yield 'site host speaks the instance language' => [self::SITE, 'ru', 'en', 'ru'];
        yield 'cabinet host speaks the browser language to a guest' => [self::CABINET, 'en', 'ru', 'ru'];
    }

    #[Group('ACMS-CORE-0168')]
    #[Group('ACMS-CORE-1094')]
    #[DataProvider('hosts')]
    public function testAddressRoutedNowhereSpeaksLanguageOfItsHost(string $host, string $instance, string $accepted, string $spoken): void
    {
        $this->configure([Language::NAME => $instance]);

        $this->client->request('GET', $host.'/nowhere', server: ['HTTP_ACCEPT_LANGUAGE' => $accepted]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame($spoken, $this->client->getRequest()->getLocale());
    }
}
