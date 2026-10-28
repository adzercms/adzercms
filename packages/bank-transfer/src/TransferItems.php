<?php

declare(strict_types=1);

namespace AdzerCMS\BankTransfer;

use AdzerCMS\BankTransfer\Controller\Admin\TransferController;
use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Payments\Repository\PaymentRepository;

use function Symfony\Component\Translation\t;

final readonly class TransferItems implements Menu
{
    public function __construct(private PaymentRepository $payments)
    {
    }

    public function items(Area $area): array
    {
        return Area::Admin === $area
            ? [new MenuItem(TransferController::LIST, t('bank_transfer.menu'), 590, [TransferController::ENTRY], count: $this->payments->countOfMethod(BankTransferService::METHOD, PaymentStatus::Pending))]
            : [];
    }
}
