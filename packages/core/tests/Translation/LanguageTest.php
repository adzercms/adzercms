<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Translation;

use AdzerCMS\Core\Setting\Language;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Translation\LocaleSwitcher;

final class LanguageTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;

    private const INSTANCE = 'ru';

    /** @return iterable<string, array{bool, ?string, string, string, bool}> */
    public static function requests(): iterable
    {
        yield 'record speaks its choice' => [true, 'en', 'ru-RU,ru;q=0.9', 'en', false];
        yield 'record without choice speaks the instance language' => [true, null, 'en', self::INSTANCE, false];
        yield 'guest speaks the browser language' => [false, null, 'de-DE,en;q=0.8', 'en', false];
        yield 'guest of an unknown language hears the instance one' => [false, null, 'de', self::INSTANCE, false];
        yield 'site speaks the instance language to a record of its choice' => [true, 'en', 'en', self::INSTANCE, true];
        yield 'site speaks the instance language to a browser of another' => [false, null, 'en', self::INSTANCE, true];
    }

    #[Group('ACMS-CORE-0168')]
    #[Group('ACMS-CORE-1094')]
    #[DataProvider('requests')]
    public function testRequestSpeaksLanguageOfRecordElseOfBrowserElseOfInstance(bool $record, ?string $chosen, string $accepted, string $spoken, bool $site): void
    {
        $this->configure([Language::NAME => self::INSTANCE]);
        if ($record) {
            $user = $this->record('79990000010')->setLanguage($chosen);
            $this->em->flush();
            $this->client->loginUser($user);
        }

        $this->client->request('GET', $site ? '/fixture/site/locale' : '/fixture/locale', server: ['HTTP_ACCEPT_LANGUAGE' => $accepted]);

        self::assertSame($spoken.' '.$spoken, $this->client->getResponse()->getContent());
    }

    #[Group('ACMS-CORE-0168')]
    public function testTaskSpeaksInstanceLanguage(): void
    {
        $this->configure([Language::NAME => self::INSTANCE]);
        $console = new Application(self::$kernel ?? self::bootKernel());
        $console->setAutoExit(false);
        $console->setCatchExceptions(false);

        self::assertSame(Command::SUCCESS, $console->run(new ArrayInput(['command' => 'list']), new NullOutput()));
        self::assertSame(self::INSTANCE, self::service(LocaleSwitcher::class)->getLocale());
    }
}
