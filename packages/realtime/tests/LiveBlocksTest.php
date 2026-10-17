<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Realtime\Tests\Fixture\HeraldHub;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;

final class LiveBlocksTest extends SchemaTestCase
{
    use Accounts;
    use Live;

    private const STAFF = 'staff:localhost';
    private const ENDPOINT = 'wss://herald.example.org/localhost';

    private HeraldHub $hub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hub = self::service(HeraldHub::class);
    }

    #[Group('ACMS-UI-0060')]
    #[Group('ACMS-UI-0061')]
    #[Group('ACMS-UI-0063')]
    public function testHeadSubscribesUserToOwnChannelAndOperatorToStaffChannel(): void
    {
        $user = $this->record('79990000020');
        $operator = $this->record('79990000021')->setRole(Role::Admin);
        $this->em->flush();
        $subject = $user->getId().'@localhost';

        self::assertSame(['template' => '@Realtime/_head.html.twig', 'context' => [
            'client' => 'bundles/herald/client.js',
            'endpoint' => self::ENDPOINT,
            'token' => 'connection '.$subject,
            'channels' => [['channel' => 'herald:'.$subject]],
        ]], $this->head($user), 'the user connects as the own record named with the instance and listens to the own channel only');

        $staff = $operator->getId().'@localhost';
        self::assertSame([
            ['channel' => 'herald:'.$staff],
            ['channel' => self::STAFF, 'token' => 'subscription '.$staff.' '.self::STAFF],
        ], $this->head($operator)['context']['channels'] ?? null, 'the operator listens to the staff channel of the instance by its subscription token');
    }

    #[Group('ACMS-UI-0060')]
    #[Group('ACMS-UI-0063')]
    public function testTokenRouteRenewsTokensAndGivesStaffChannelOnlyToOperator(): void
    {
        $user = $this->record('79990000020');
        $operator = $this->record('79990000021')->setRole(Role::Admin);
        $this->em->flush();
        $staff = $operator->getId().'@localhost';

        $this->client->request('GET', '/realtime/token');
        self::assertResponseRedirects(message: 'the guest gets no token');

        $this->client->loginUser($operator);
        self::assertSame('connection '.$staff, $this->tokenFrom('/realtime/token'));
        self::assertSame('subscription '.$staff.' '.self::STAFF, $this->tokenFrom('/realtime/token?channel='.urlencode(self::STAFF)));
        self::assertStringContainsString(' '.self::ENDPOINT, (string) $this->client->getResponse()->headers->get('Content-Security-Policy'), 'the policy lets the page connect to the hub');

        $this->client->request('GET', '/realtime/token?channel='.urlencode('staff:other'));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'the staff channel of another instance is not given');

        $this->client->loginUser($user);
        $this->client->request('GET', '/realtime/token?channel='.urlencode(self::STAFF));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'the user gets no token of the staff channel');
    }

    #[Group('ACMS-UI-0059')]
    #[Group('ACMS-UI-0062')]
    public function testStatusChangeReachesOwnerChannelOnlyAfterCommitAndOtherFieldsDoNot(): void
    {
        $user = $this->record('79990000020');
        $this->released();

        $user->setStatus(Status::Stopped);
        $this->em->flush();

        self::assertSame([], $this->hub->released(), 'the hub is not called inside an uncommitted transaction');
        self::assertSame([['herald:'.$user->getId().'@localhost']], $this->released());

        $user->setComment('Village shop');
        $this->em->flush();

        self::assertSame([], $this->released(), 'a change outside the declared fields gives no event');
    }

    #[Group('ACMS-UI-0058')]
    #[Group('ACMS-UI-0059')]
    public function testPageGoesWithoutSubscriptionWhileTheHubIsNotReady(): void
    {
        $user = $this->record('79990000020');
        $this->released();
        $this->hub->ready = false;

        self::assertNull($this->head($user), 'the page has no client of live blocks');

        $this->client->loginUser($user);
        $this->client->request('GET', '/realtime/token');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'the token route is closed');
        self::assertStringNotContainsString(self::ENDPOINT, (string) $this->client->getResponse()->headers->get('Content-Security-Policy'), 'the policy opens no hub');

        $user->setStatus(Status::Stopped);
        $this->em->flush();
        self::assertSame([], $this->released(), 'a change gives no event');
    }

    private function tokenFrom(string $url): string
    {
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($body) && \is_string($body['token'] ?? null) ? $body['token'] : self::fail('the token route answered without a token');
    }

    /**
     * @phpstan-impure
     *
     * @return list<list<string>>
     */
    private function released(): array
    {
        $this->terminate();

        return $this->hub->released();
    }
}
