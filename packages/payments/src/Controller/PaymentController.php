<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Controller;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Ui\QrImage;
use AdzerCMS\Payments\Acquiring\PaymentFailed;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentService;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Payments\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

#[Route('/payment')]
final class PaymentController extends AbstractController
{
    public const CALLBACK = 'payment_callback';

    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentRepository $orders,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/callback', name: self::CALLBACK, defaults: [CsrfGuard::OWN => true], methods: ['POST'])]
    public function callback(Request $request): Response
    {
        try {
            return $this->payments->callback($request)
                ? $this->payments->acknowledgement()
                : new Response('', Response::HTTP_BAD_REQUEST);
        } catch (PaymentFailed) {
            return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    #[Route('/{number}', name: 'payment_code', requirements: ['number' => '[0-9a-f]{32}'], methods: ['GET'])]
    public function code(string $number, QrImage $qr): Response
    {
        $payment = $this->payment($number);
        if (PaymentStatus::Pending !== $payment->getStatus()) {
            return $this->redirect($payment->getBack());
        }

        $offer = $this->payments->start($payment);
        if ($offer instanceof Refusal) {
            $this->addFlash('error', $offer->reason);

            return $this->redirect($payment->getBack());
        }

        if (null === $offer->qr) {
            return $this->redirect((string) $offer->url);
        }

        return $this->render('@Payments/payments/qr.html.twig', [
            'payment' => $payment,
            'link' => $offer->qr,
            'image' => $qr->dataUri($offer->qr),
        ]);
    }

    #[Route('/{number}/state', name: 'payment_state', requirements: ['number' => '[0-9a-f]{32}'], methods: ['GET'])]
    public function state(string $number): Response
    {
        $payment = $this->payment($number);

        try {
            $this->payments->refresh($payment);
        } catch (PaymentFailed) {
        }

        if (PaymentStatus::Pending === $payment->getStatus()) {
            return $this->render('@Payments/payments/_wait.html.twig', ['payment' => $payment]);
        }

        $this->outcome($payment);
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('HX-Redirect', $payment->getBack());

        return $response;
    }

    #[Route('/{number}/return', name: 'payment_return', requirements: ['number' => '[0-9a-f]{32}'], methods: ['GET'])]
    public function back(string $number): Response
    {
        $payment = $this->payment($number);

        try {
            $this->payments->refresh($payment);
        } catch (PaymentFailed) {
        }

        $this->outcome($payment);

        return $this->redirect($payment->getBack());
    }

    private function outcome(Payment $payment): void
    {
        $this->addFlash(...match ($payment->getStatus()) {
            PaymentStatus::Confirmed => ['success', t($payment->isBinding() ? 'payments.flash.bound' : 'payments.flash.confirmed')],
            PaymentStatus::Pending => ['success', t('payments.flash.pending')],
            default => ['error', t($payment->isBinding() ? 'payments.flash.unbound' : 'payments.flash.rejected')],
        });
    }

    private function payment(string $number): Payment
    {
        $payment = $this->orders->findByNumber($number) ?? throw $this->createNotFoundException();
        $payer = $payment->getPayer();
        $viewer = $this->getUser();
        $owner = null === $payer || null === $viewer ? null : $this->users->identified($payer);
        if (null !== $owner && $owner->getUserIdentifier() !== $viewer->getUserIdentifier()) {
            throw $this->createNotFoundException();
        }

        return $payment;
    }
}
