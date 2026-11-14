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
    private const CABINET = '/fixture/locale';
    private const SITE = '/fixture/site/locale';
    private const VISITOR_SITE = '/fixture/site/visitor/locale';

    /** @return iterable<string, array{bool, ?string, string, string, string}> */
    public static function requests(): iterable
    {
        yield 'record speaks its choice' => [true, 'en', 'ru-RU,ru;q=0.9', 'en', self::CABINET];
        yield 'record without choice speaks the instance language' => [true, null, 'en', self::INSTANCE, self::CABINET];
        yield 'guest speaks the browser language' => [false, null, 'de-DE,en;q=0.8', 'en', self::CABINET];
        yield 'guest of an unknown language hears the instance one' => [false, null, 'de', self::INSTANCE, self::CABINET];
        yield 'site speaks the instance language to a record of its choice' => [true, 'en', 'en', self::INSTANCE, self::SITE];
        yield 'site speaks the instance language to a browser of another' => [false, null, 'en', self::INSTANCE, self::SITE];
        yield 'site page of the visitor language speaks the browser one' => [false, null, 'en', 'en', self::VISITOR_SITE];
    }

    #[Group('ACMS-CORE-0168')]
    #[Group('ACMS-CORE-1094')]
    #[DataProvider('requests')]
    public function testRequestSpeaksLanguageOfRecordElseOfBrowserElseOfInstance(bool $record, ?string $chosen, string $accepted, string $spoken, string $path): void
    {
        $this->configure([Language::NAME => self::INSTANCE]);
        if ($record) {
            $user = $this->record('79990000010')->setLanguage($chosen);
            $this->em->flush();
            $this->client->loginUser($user);
        }

        $this->client->request('GET', $path, server: ['HTTP_ACCEPT_LANGUAGE' => $accepted]);

        $response = $this->client->getResponse();
        self::assertSame($spoken.' '.$spoken, $response->getContent());
        if (self::CABINET !== $path) {
            self::assertSame(self::VISITOR_SITE === $path, \in_array('Accept-Language', $response->getVary(), true), 'a site page varies by the browser language only when it speaks it');
        }
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
