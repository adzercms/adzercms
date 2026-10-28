<?php

declare(strict_types=1);

namespace AdzerCMS\YooKassa;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Payments\Acquiring\AcquiringService;
use AdzerCMS\Payments\Acquiring\Addresses;
use AdzerCMS\Payments\Acquiring\BoundCard;
use AdzerCMS\Payments\Acquiring\CardPrint;
use AdzerCMS\Payments\Acquiring\Closing;
use AdzerCMS\Payments\Acquiring\PaymentCallback;
use AdzerCMS\Payments\Acquiring\PaymentFailed;
use AdzerCMS\Payments\Acquiring\PaymentOffer;
use AdzerCMS\Payments\Controller\PaymentController;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentStatus;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final readonly class YooKassaService implements AcquiringService, Guided
{
    public const API = 'https://api.yookassa.ru/v3/';
    public const SHOP_ID = 'payment.yookassa.shop_id';
    public const SECRET_KEY = 'payment.yookassa.secret_key';
    public const TAXATION = 'payment.yookassa.taxation';
    public const VAT = 'payment.yookassa.vat';
    public const PAYMENT_MODE = 'payment.yookassa.payment_mode';
    public const PAYMENT_SUBJECT = 'payment.yookassa.payment_subject';
    public const CLOSING_SUBJECT = 'payment.yookassa.closing_subject';

    public const METHOD_SBP = 'sbp';
    public const METHOD_PAGE = 'page';

    private const SHOP = 'payment.yookassa.shop';
    private const FLOOR = '1.00';
    private const LIFETIME = 'P1D';
    private const TIMEOUT = 20.0;
    private const CARD = 'bank_card';
    private const METHOD_EVENT = 'payment_method.active';
    private const INACTIVE = 'inactive';
    private const CANCELED = 'canceled';
    private const MASK = '******';
    private const ORIGINS = ['https://yoomoney.ru', 'https://yookassa.ru', 'https://qr.nspk.ru'];
    private const SENDERS = ['185.71.76.0/27', '185.71.77.0/27', '77.75.153.0/25', '77.75.154.128/25', '77.75.156.11/32', '77.75.156.35/32', '2a02:5180::/32'];
    private const TAXATIONS = ['1', '2', '3', '4', '5', '6'];
    private const VATS = ['1', '2', '7', '8', '3', '4', '11', '9', '10', '5', '6', '12'];
    private const MODES = ['full_prepayment', 'partial_prepayment', 'advance', 'full_payment', 'partial_payment', 'credit', 'credit_payment'];
    private const ADVANCES = ['full_prepayment', 'partial_prepayment', 'advance'];
    private const SUBJECTS = ['commodity', 'excise', 'job', 'service', 'gambling_bet', 'gambling_prize', 'lottery', 'lottery_prize', 'intellectual_activity', 'payment', 'agent_commission', 'property_right', 'non_operating_gain', 'insurance_premium', 'sales_tax', 'resort_fee', 'composite', 'another'];

    public function __construct(
        private HttpClientInterface $http,
        private Settings $settings,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::SHOP_ID, t('yookassa.shop_id'), required: true, group: self::SHOP),
            new Field(self::SECRET_KEY, t('yookassa.secret_key'), Type::Secret, true, group: self::SHOP),
            new Field(self::TAXATION, t('yookassa.taxation'), Type::Choice, choices: self::choices('yookassa.taxation', self::TAXATIONS)),
            new Field(self::VAT, t('yookassa.vat'), Type::Choice, choices: self::choices('yookassa.vat', self::VATS)),
            new Field(self::PAYMENT_MODE, t('yookassa.payment_mode'), Type::Choice, choices: self::choices('yookassa.payment_mode', self::MODES)),
            new Field(self::PAYMENT_SUBJECT, t('yookassa.payment_subject'), Type::Choice, choices: self::choices('yookassa.payment_subject', self::SUBJECTS)),
            new Field(self::CLOSING_SUBJECT, t('yookassa.closing_subject'), Type::Choice, choices: self::choices('yookassa.payment_subject', self::SUBJECTS)),
        ];
    }

    public function guide(): array
    {
        return [
            t('yookassa.guide.shop'),
            t('yookassa.guide.key'),
            t('yookassa.guide.notifications', ['url' => $this->urls->generate(PaymentController::CALLBACK, [], UrlGeneratorInterface::ABSOLUTE_URL)]),
        ];
    }

    public function ready(): bool
    {
        foreach ([self::SHOP_ID, self::SECRET_KEY] as $name) {
            if ('' === $this->settings->get($name)) {
                return false;
            }
        }

        return true;
    }

    public function floor(): string
    {
        return self::FLOOR;
    }

    public function origins(): array
    {
        return self::ORIGINS;
    }

    public function methods(): array
    {
        return [self::METHOD_SBP => t('yookassa.method.sbp'), self::METHOD_PAGE => t('yookassa.method.page')];
    }

    public function fees(): array
    {
        return [];
    }

    public function saves(): bool
    {
        return true;
    }

    public function binds(): bool
    {
        return true;
    }

    public function lifetime(): \DateInterval
    {
        return new \DateInterval(self::LIFETIME);
    }

    public function start(Payment $payment, Addresses $addresses): PaymentOffer
    {
        $request = $this->order($payment, $addresses);
        if (self::METHOD_SBP === $payment->getMethod()) {
            $request['payment_method_data'] = ['type' => 'sbp'];
            $request['confirmation'] = ['type' => 'qr'];
        }

        $answer = $this->register($payment, 'payments', $request);
        $confirmation = \is_array($answer['confirmation'] ?? null) ? $answer['confirmation'] : [];
        $code = $confirmation['confirmation_data'] ?? null;
        $url = $confirmation['confirmation_url'] ?? null;

        return match (true) {
            \is_string($code) && '' !== $code => PaymentOffer::qr($code),
            \is_string($url) && '' !== $url => PaymentOffer::redirect($url),
            default => throw new PaymentFailed('YooKassa gave neither a payment address nor a code'),
        };
    }

    public function bind(Payment $binding, Addresses $addresses): PaymentOffer
    {
        $answer = $this->register($binding, 'payment_methods', [
            'type' => self::CARD,
            'confirmation' => ['type' => 'redirect', 'return_url' => $addresses->return],
        ]);
        $url = \is_array($answer['confirmation'] ?? null) ? ($answer['confirmation']['confirmation_url'] ?? null) : null;

        return \is_string($url) && '' !== $url
            ? PaymentOffer::redirect($url)
            : throw new PaymentFailed('YooKassa gave no card saving address');
    }

    public function state(Payment $payment): ?PaymentCallback
    {
        $operation = $payment->getOperationId();
        if (null === $operation) {
            return null;
        }

        return $payment->isBinding()
            ? self::bound($payment->getNumber(), $this->call('GET', 'payment_methods/'.$operation))
            : self::paid($payment->getNumber(), $this->call('GET', 'payments/'.$operation));
    }

    public function callback(Request $request): ?PaymentCallback
    {
        if (!IpUtils::checkIp((string) $request->getClientIp(), self::SENDERS)) {
            return null;
        }

        $payload = json_decode($request->getContent(), true);
        $object = \is_array($payload) && \is_array($payload['object'] ?? null) ? $payload['object'] : null;
        $operation = \is_string($object['id'] ?? null) ? $object['id'] : '';
        if ('' === $operation) {
            return null;
        }

        return self::METHOD_EVENT === ($payload['event'] ?? null)
            ? self::bound('', $this->call('GET', 'payment_methods/'.$operation))
            : self::paid(null, $this->call('GET', 'payments/'.$operation));
    }

    public function acknowledgement(): Response
    {
        return new Response('', Response::HTTP_OK);
    }

    public function charge(Payment $payment, string $token, Addresses $addresses): PaymentCallback
    {
        return self::paid($payment->getNumber(), $this->register($payment, 'payments', ['payment_method_id' => $token] + $this->order($payment, $addresses)));
    }

    public function forget(string $payer, string $token): void
    {
    }

    public function close(Payment $payment, Closing $closing): void
    {
        $sum = ['value' => Money::normalize($closing->amount), 'currency' => Money::CURRENCY];
        $answer = $this->call('POST', 'receipts', [
            'type' => 'payment',
            'payment_id' => $payment->getOperationId(),
            'send' => true,
            'settlements' => [['type' => 'prepayment', 'amount' => $sum]],
        ] + $this->receipt($closing->phone, $closing->email, [$this->item($closing->item, '1', $sum, 'full_payment', $this->settings->get(self::CLOSING_SUBJECT))]), $closing->key);

        if (!\is_string($answer['id'] ?? null)) {
            throw new PaymentFailed(self::fault($answer));
        }
        if (self::CANCELED === ($answer['status'] ?? null)) {
            throw new PaymentFailed('YooKassa cancelled the closing receipt');
        }
    }

    /** @param array<mixed> $answer */
    private static function paid(?string $number, array $answer): PaymentCallback
    {
        $named = \is_array($answer['metadata'] ?? null) ? ($answer['metadata']['number'] ?? null) : null;
        $amount = self::value($answer, 'amount');
        $income = self::value($answer, 'income_amount');
        $method = \is_array($answer['payment_method'] ?? null) ? $answer['payment_method'] : [];

        return new PaymentCallback(
            $number ?? (\is_string($named) ? $named : ''),
            self::status($answer) ?? PaymentStatus::Pending,
            \is_string($answer['id'] ?? null) ? $answer['id'] : null,
            $amount,
            true === ($method['saved'] ?? null) ? self::card($method) : null,
            self::print($method),
            null !== $amount && null !== $income ? Money::sub($amount, $income) : null,
        );
    }

    /** @param array<mixed> $answer */
    private static function value(array $answer, string $field): ?string
    {
        $value = \is_array($answer[$field] ?? null) ? ($answer[$field]['value'] ?? null) : null;

        return \is_string($value) ? Money::normalize($value) : null;
    }

    /** @param array<mixed> $answer */
    private static function bound(string $number, array $answer): ?PaymentCallback
    {
        $operation = $answer['id'] ?? null;
        if (!\is_string($operation) || '' === $operation) {
            return null;
        }

        $status = match (true) {
            true === ($answer['saved'] ?? null) => PaymentStatus::Confirmed,
            self::INACTIVE === ($answer['status'] ?? null) => PaymentStatus::Rejected,
            default => PaymentStatus::Pending,
        };
        $confirmed = PaymentStatus::Confirmed === $status;

        return new PaymentCallback($number, $status, $operation, null, $confirmed ? self::card($answer) : null, $confirmed ? self::print($answer) : null);
    }

    /** @param array<mixed> $method */
    private static function print(array $method): ?string
    {
        $card = \is_array($method['card'] ?? null) ? $method['card'] : [];
        $part = static fn (string $field): string => \is_string($card[$field] ?? null) ? $card[$field] : '';

        return CardPrint::of($part('first6'), $part('last4'), $part('expiry_month'), $part('expiry_year'));
    }

    /** @param array<mixed> $method */
    private static function card(array $method): ?BoundCard
    {
        $id = $method['id'] ?? null;
        $card = \is_array($method['card'] ?? null) ? $method['card'] : [];
        $last = $card['last4'] ?? null;
        if (!\is_string($id) || '' === $id || !\is_string($last)) {
            return null;
        }

        $first = \is_string($card['first6'] ?? null) ? $card['first6'] : '';
        $issuer = \is_string($card['issuer_country'] ?? null) ? mb_strtoupper($card['issuer_country']) : null;
        $month = $card['expiry_month'] ?? null;
        $year = $card['expiry_year'] ?? null;

        return new BoundCard($id, $first.self::MASK.$last, \is_string($month) && \is_string($year) ? $month.'/'.substr($year, -2) : null, $issuer);
    }

    /** @return array<string, mixed> */
    private function order(Payment $payment, Addresses $addresses): array
    {
        $mode = $this->settings->get(self::PAYMENT_MODE);
        $items = array_map(fn (array $item): array => $this->item(
            $item['name'],
            $item['quantity'].'.00',
            ['value' => Money::normalize($item['price']), 'currency' => Money::CURRENCY],
            $mode,
            $this->settings->get(self::PAYMENT_SUBJECT),
        ), $payment->getItems());

        $request = [
            'amount' => ['value' => Money::normalize($payment->getAmount()), 'currency' => Money::CURRENCY],
            'capture' => true,
            'description' => $payment->getDescription(),
            'metadata' => ['number' => $payment->getNumber()],
            'confirmation' => ['type' => 'redirect', 'return_url' => $addresses->return],
            'receipt' => $this->receipt($payment->getPhone(), $payment->getEmail(), $items),
        ];
        if ($payment->remembers()) {
            $request['save_payment_method'] = true;
        }
        $payment->markAdvance(\in_array($mode, self::ADVANCES, true));

        return $request;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function receipt(?string $phone, ?string $email, array $items): array
    {
        $customer = array_filter(['email' => $email, 'phone' => $phone], static fn (?string $value): bool => null !== $value && '' !== $value);
        if ([] === $customer) {
            throw new PaymentFailed('the receipt has neither phone nor email');
        }

        $taxation = $this->settings->get(self::TAXATION);

        return ['customer' => $customer] + ('' === $taxation ? [] : ['tax_system_code' => (int) $taxation]) + ['items' => $items];
    }

    /**
     * @param array<string, string> $amount
     *
     * @return array<string, mixed>
     */
    private function item(string $description, string $quantity, array $amount, string $mode, string $subject): array
    {
        return [
            'description' => $description,
            'quantity' => $quantity,
            'amount' => $amount,
            'vat_code' => (int) $this->settings->get(self::VAT),
            'payment_mode' => $mode,
            'payment_subject' => $subject,
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<mixed>
     */
    private function register(Payment $payment, string $path, array $request): array
    {
        $answer = $this->call('POST', $path, $request, $payment->getNumber());
        $operation = $answer['id'] ?? null;
        if (!\is_string($operation) || '' === $operation) {
            throw new PaymentFailed(self::fault($answer));
        }
        $payment->setOperationId($operation);

        return $answer;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<mixed>
     */
    private function call(string $method, string $path, array $request = [], ?string $idempotence = null): array
    {
        if (!$this->ready()) {
            throw new PaymentFailed('the shop is not set up');
        }

        $options = ['auth_basic' => [$this->settings->get(self::SHOP_ID), $this->settings->get(self::SECRET_KEY)], 'timeout' => self::TIMEOUT];
        if ('GET' !== $method) {
            $options['json'] = $request;
            $options['headers'] = ['Idempotence-Key' => $idempotence ?? bin2hex(random_bytes(16))];
        }

        try {
            return $this->http->request($method, self::API.$path, $options)->toArray(false);
        } catch (ExceptionInterface $failure) {
            throw new PaymentFailed($failure->getMessage(), previous: $failure);
        }
    }

    /** @param array<mixed> $answer */
    private static function status(array $answer): ?PaymentStatus
    {
        return match ($answer['status'] ?? '') {
            'succeeded' => PaymentStatus::Confirmed,
            self::CANCELED => PaymentStatus::Rejected,
            'pending', 'waiting_for_capture' => PaymentStatus::Pending,
            default => null,
        };
    }

    /** @param array<mixed> $answer */
    private static function fault(array $answer): string
    {
        $description = $answer['description'] ?? $answer['code'] ?? null;

        return \is_string($description) ? $description : 'YooKassa did not accept the request';
    }

    /**
     * @param list<string> $codes
     *
     * @return array<string, TranslatableInterface>
     */
    private static function choices(string $prefix, array $codes): array
    {
        $choices = [];
        foreach ($codes as $code) {
            $choices[$code] = t($prefix.'.'.$code);
        }

        return $choices;
    }
}
