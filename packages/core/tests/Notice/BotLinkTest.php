<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice;

use AdzerCMS\Contracts\Notice\Incoming;
use AdzerCMS\Core\Notice\BotLink;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

final class BotLinkTest extends SchemaTestCase
{
    use Accounts;

    private const SERVICE = 'pager';

    #[Group('ACMS-CORE-0089')]
    #[Group('ACMS-CORE-0123')]
    public function testDialogIsLinkedOnceByCodeNamedToTheUser(): void
    {
        $user = $this->record('79991234567');
        $links = self::service(BotLink::class);
        $code = $links->request($user, self::SERVICE);

        self::assertNull($links->accept(self::SERVICE, new Incoming('FOREIGN1', '77', null)), 'a dialog is linked only by the code named to the user');
        self::assertSame($user, $links->accept(self::SERVICE, new Incoming(' '.mb_strtolower($code).' ', '77', '@human')));

        $linked = self::service(ConnectedServiceRepository::class)->of($user)[self::SERVICE] ?? self::fail('the dialog is not linked');
        self::assertSame('77', $linked->getProfile());
        self::assertSame('@human', $linked->getAccount());
        self::assertNull($links->accept(self::SERVICE, new Incoming($code, '78', null)), 'the code works once');
    }

    #[Group('ACMS-CORE-0123')]
    public function testDialogLinkedToOneRecordIsNotTakenByAnother(): void
    {
        $owner = $this->record('79991234567');
        $stranger = $this->record('79991234568');
        $links = self::service(BotLink::class);

        $links->accept(self::SERVICE, new Incoming($links->request($owner, self::SERVICE), '77', null));

        self::assertNull(
            $links->accept(self::SERVICE, new Incoming($links->request($stranger, self::SERVICE), '77', null)),
            'one messenger dialog belongs to one record',
        );
        self::assertSame($owner, self::service(ConnectedServiceRepository::class)->profile(self::SERVICE, '77')?->getUser());
    }
}
