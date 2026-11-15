<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Once;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\TranslatableMessage;

final class OnceTest extends TestCase
{
    #[Group('ACMS-UI-0067')]
    public function testRepeatIsSkippedUnlessTheActionRefused(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requests = new RequestStack();
        $requests->push($request);
        $once = new Once($requests);
        $refused = new Refusal(new TranslatableMessage('refused'));

        self::assertSame($refused, $once->act('order', [1], static fn (): Refusal => $refused));
        self::assertSame('done', $once->act('order', [1], static fn (): string => 'done'), 'a refused action opens the repeat');
        self::assertEquals(new Refusal(Once::repeat()), $once->act('order', [1], static fn (): string => 'again'), 'a repeat of a done action is skipped');
        self::assertSame('other', $once->act('order', [2], static fn (): string => 'other'), 'other values are another action');
    }
}
