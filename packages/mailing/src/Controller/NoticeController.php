<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing\Controller;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Module\Snapshot;
use AdzerCMS\Core\Notice\Channel;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Notice\Status;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Mailing\Broadcast;
use AdzerCMS\Mailing\SmsAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/notices')]
#[IsGranted(Role::ADMIN)]
final class NoticeController extends AbstractController
{
    public function __construct(
        private readonly NoticeRepository $notices,
        private readonly Channels $channels,
        private readonly Broadcast $broadcast,
        private readonly SmsAccount $account,
    ) {
    }

    #[Route('', name: 'admin_notices', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $channels = $this->channels->all();
        $keys = array_map(static fn (Channel $channel): string => $channel->key(), $channels);
        $tab = $request->query->getString('tab');
        if (!\in_array($tab, $keys, true)) {
            $tab = ($this->channels->offered()[0] ?? $channels[0])->key();
        }

        if ('journal' !== $request->query->get('view')) {
            return $this->render('@Mailing/admin/notices.html.twig', ['view' => 'broadcast', 'tab' => $tab, 'limit' => Broadcast::LIMIT] + $this->scope($request));
        }

        $events = [];
        foreach ($this->notices->events() as $event) {
            $events[$event] = t($event);
        }

        $table = Table::of($request, 'admin_notices', [
            Column::of('at', t('mailing.column.at'), 'n.createdAt'),
            Column::of('event', t('mailing.column.event'), 'n.event'),
            Column::of('user', t('mailing.column.user'), 'n.user'),
            Column::of('recipient', t('mailing.column.recipient'), 'n.recipient')->aside(),
            Column::plain('body', t('mailing.column.body'))->aside(),
            Column::of('status', t('mailing.column.status'), 'n.status'),
        ], 'at', t('mailing.empty'), false,
            filters: [
                Filter::search(),
                Filter::select('event', t('mailing.column.event'), $events),
                Filter::cases('status', t('mailing.filter.status'), Status::cases()),
            ],
            sticky: ['tab' => $tab, 'view' => 'journal'],
        );

        $sms = ContactKind::Phone->channel();
        $context = [
            'tab' => $tab,
            'view' => 'journal',
            'channels' => $channels,
            'table' => $table,
            'rows' => $this->notices->journal($tab, $table, Status::tryFrom($table->value('status'))),
            'configured' => null !== $this->channels->ready($tab),
            'sms' => $sms,
        ];

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Mailing/admin/_notices.html.twig', $context);
        }

        return $this->render('@Mailing/admin/notices.html.twig', $context + SmsAccount::context(
            $sms === $tab && $context['configured'] ? $this->account->stored() : null,
        ));
    }

    #[Route('/gateway', name: 'admin_notice_gateway', methods: ['GET'])]
    public function gateway(Request $request): Response
    {
        return $this->render('@Mailing/admin/_sms_account.html.twig',
            SmsAccount::context($this->account->of($request->headers->has(Snapshot::RESCAN))));
    }

    #[Route('/recipients', name: 'admin_broadcast_recipients', methods: ['GET'])]
    public function recipients(Request $request): Response
    {
        return $this->render('@Mailing/admin/_broadcast_scope.html.twig', $this->scope($request));
    }

    #[Route('/broadcast', name: 'admin_broadcast', methods: ['POST'])]
    public function send(Request $request): Response
    {
        [$scope, , $value] = $this->audience($request->request);
        $channel = $this->channels->ready($request->request->getString('channel'));
        $outcome = $this->broadcast->send($request->request->getString('body'), $scope, $value, $channel);

        if ($outcome instanceof Refusal) {
            $this->addFlash('error', $outcome->reason);

            return $this->redirectToRoute('admin_notices');
        }

        $this->addFlash($outcome['failed'] > 0 ? 'error' : 'success', t('mailing.flash.sent', $outcome));

        return $this->redirectToRoute('admin_notices', array_filter(['view' => 'journal', 'tab' => $channel?->key()]));
    }

    /** @return array<string, mixed> */
    private function scope(Request $request): array
    {
        [$scope, $filter, $value] = $this->audience($request->query);
        $channel = $this->channels->ready($request->query->getString('channel'));

        return [
            'scope' => $scope,
            'everyone' => Broadcast::EVERYONE,
            'audiences' => $this->broadcast->audiences(),
            'filter' => $filter,
            'value' => $value,
            'channel' => $channel?->key(),
            'sendable' => $this->channels->offered(),
            'recipients' => \count($this->broadcast->reachable($scope, $value, $channel)),
        ];
    }

    /**
     * @param InputBag<string> $input
     *
     * @return array{string, ?Filter, string}
     */
    private function audience(InputBag $input): array
    {
        $scope = $input->getString('scope', Broadcast::EVERYONE);
        $filter = ($this->broadcast->audiences()[$scope] ?? null)?->filter();

        return [$scope, $filter, null === $filter ? '' : $filter->pick($input->getString($filter->name))];
    }
}
