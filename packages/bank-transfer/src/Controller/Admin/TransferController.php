<?php

declare(strict_types=1);

namespace AdzerCMS\BankTransfer\Controller\Admin;

use AdzerCMS\BankTransfer\BankTransferService;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Core\Ui\Once;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Payments\Acquiring\PaymentCallback;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentService;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Payments\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/transfers')]
#[IsGranted(Role::ADMIN)]
final class TransferController extends AbstractController
{
    public const LIST = 'admin_bank_transfers';
    public const ENTRY = 'admin_bank_transfer';

    private const STATUS = 'status';
    private const AWAITING = [PaymentStatus::Pending, PaymentStatus::Cancelled];

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentService $service,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $table = Table::of($request, self::LIST, [
            Column::plain('comment', t('bank_transfer.column.comment')),
            Column::of('amount', t('bank_transfer.column.amount'), 'p.amount'),
            Column::of('created', t('bank_transfer.column.created'), 'p.createdAt'),
        ], 'created', t('bank_transfer.empty'), false, filters: [
            Filter::tabs(self::STATUS, $this->tab(PaymentStatus::Pending), [PaymentStatus::Cancelled->value => $this->tab(PaymentStatus::Cancelled)]),
            Filter::search(),
        ]);

        $builder = $this->payments->ofMethod(BankTransferService::METHOD, PaymentStatus::tryFrom($table->value(self::STATUS)) ?? PaymentStatus::Pending, trim($table->term()));
        $total = (int) (clone $builder)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $table->apply($builder)->total($total);

        /** @var list<Payment> $rows */
        $rows = $builder
            ->addOrderBy('p.id', \SortDirection::Descending)
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();

        $comments = [];
        foreach ($rows as $row) {
            $comments[$row->getNumber()] = BankTransferService::comment($row);
        }

        return $this->render(
            $request->headers->has('HX-Request') ? '@BankTransfer/admin/_transfers.html.twig' : '@BankTransfer/admin/transfers.html.twig',
            ['table' => $table, 'rows' => $rows, 'comments' => $comments],
        );
    }

    #[Route('/{number}', name: self::ENTRY, requirements: ['number' => '[0-9a-f]{32}'], methods: ['GET', 'POST'])]
    public function entry(string $number, Request $request, Once $once): Response
    {
        $payment = $this->payments->findByNumber($number);
        if (null === $payment || BankTransferService::METHOD !== $payment->getMethod() || !\in_array($payment->getStatus(), self::AWAITING, true)) {
            throw $this->createNotFoundException();
        }

        $comment = BankTransferService::comment($payment);
        $amount = $payment->getAmount();
        $error = null;

        if ($request->isMethod('POST')) {
            $amount = Money::parse($request->request->getString('amount')) ?? Money::normalize('0');
            $error = match (true) {
                !Money::isPositive($amount) => t('bank_transfer.confirm.positive'),
                !$once->fresh('bank_transfer.confirm', [$number]) => Once::repeat(),
                default => null,
            };

            if (null === $error) {
                $this->service->conclude(new PaymentCallback($number, PaymentStatus::Confirmed, amount: $amount));
                $this->addFlash('success', t('bank_transfer.confirm.done', ['comment' => $comment, 'amount' => Money::rubles($amount)]));

                return Fragment::go($this->generateUrl(self::LIST));
            }
        }

        return $this->render(
            '@BankTransfer/admin/_transfer.html.twig',
            ['payment' => $payment, 'comment' => $comment, 'amount' => $amount, 'error' => $error],
            new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }

    private function tab(PaymentStatus $status): TranslatableInterface
    {
        return t('bank_transfer.tab.'.$status->value, ['count' => $this->payments->countOfMethod(BankTransferService::METHOD, $status)]);
    }
}
