<?php

declare(strict_types=1);

namespace AdzerCMS\TBank;

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
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final readonly class TBankService implements AcquiringService, Guided
{
    public const API_URL = 'payment.tbank.api_url';
    public const TERMINAL_KEY = 'payment.tbank.terminal_key';
    public const TERMINAL_PASSWORD = 'payment.tbank.terminal_password';
    public const TAXATION = 'payment.tbank.taxation';
    public const VAT = 'payment.tbank.vat';
    public const PAYMENT_METHOD = 'payment.tbank.payment_method';
    public const PAYMENT_OBJECT = 'payment.tbank.payment_object';
    public const CLOSING_OBJECT = 'payment.tbank.closing_object';
    public const FEE_QR = 'payment.tbank.fee_qr';
    public const FEE_PAGE = 'payment.tbank.fee_page';

    private const TERMINAL = 'payment.tbank.terminal';
    private const FLOOR = '10.00';
    private const LIFETIME = 'P1D';
    private const ORIGINS = [
        'https://pay.tbank.ru',
        'https://pay.tbank-online.com',
        'https://securepay.tbank.ru',
        'https://securepay.tinkoff.ru',
        'https://qr.nspk.ru',
    ];
    private const METHOD_QR = 'qr';
    private const METHOD_PAGE = 'page';
    private const CARD_ACTIVE = 'A';
    private const TIMEOUT = 20.0;
    private const DESCRIPTION_LENGTH = 140;
    private const NAME_LENGTH = 128;
    private const CLOSING = '../cashbox/SendClosingReceipt';
    private const FINAL_FAILURES = ['REJECTED', 'CANCELED', 'DEADLINE_EXPIRED', 'AUTH_FAIL'];
    private const TAXATIONS = ['osn', 'usn_income', 'usn_income_outcome', 'esn', 'patent'];
    private const VATS = ['none', 'vat0', 'vat5', 'vat7', 'vat10', 'vat22', 'vat105', 'vat107', 'vat110', 'vat122'];
    private const PAYMENT_METHODS = ['full_prepayment', 'prepayment', 'advance', 'full_payment', 'partial_payment', 'credit', 'credit_payment'];
    private const ADVANCES = ['full_prepayment', 'prepayment', 'advance'];
    private const PAYMENT_OBJECTS = ['commodity', 'excise', 'job', 'service', 'gambling_bet', 'gambling_prize', 'lottery', 'lottery_prize', 'intellectual_activity', 'payment', 'agent_commission', 'composite', 'another'];

    public function __construct(
        private HttpClientInterface $http,
        private Settings $settings,
    ) {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::API_URL, t('tbank.api_url'), required: true),
            new Field(self::TERMINAL_KEY, t('tbank.terminal_key'), required: true, group: self::TERMINAL),
            new Field(self::TERMINAL_PASSWORD, t('tbank.terminal_password'), Type::Secret, true, group: self::TERMINAL),
            new Field(self::TAXATION, t('tbank.taxation'), Type::Choice, choices: self::choices('tbank.taxation', self::TAXATIONS)),
            new Field(self::VAT, t('tbank.vat'), Type::Choice, choices: self::choices('tbank.vat', self::VATS)),
            new Field(self::PAYMENT_METHOD, t('tbank.payment_method'), Type::Choice, choices: self::choices('tbank.payment_method', self::PAYMENT_METHODS)),
            new Field(self::PAYMENT_OBJECT, t('tbank.payment_object'), Type::Choice, choices: self::choices('tbank.payment_object', self::PAYMENT_OBJECTS)),
            new Field(self::CLOSING_OBJECT, t('tbank.closing_object'), Type::Choice, choices: self::choices('tbank.payment_object', self::PAYMENT_OBJECTS)),
            new Field(self::FEE_QR, t('tbank.fee_qr'), Type::Decimal),
            new Field(self::FEE_PAGE, t('tbank.fee_page'), Type::Decimal),
        ];
    }

    public function guide(): array
    {
        return [t('tbank.guide.terminals'), t('tbank.guide.terminal'), t('tbank.guide.api')];
    }

    public function ready(): bool
    {
        foreach ([self::API_URL, self::TERMINAL_KEY, self::TERMINAL_PASSWORD] as $name) {
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
        return [self::METHOD_QR => t('tbank.method.qr'), self::METHOD_PAGE => t('tbank.method.page')];
    }

    public function fees(): array
    {
        return [self::METHOD_QR => $this->settings->get(self::FEE_QR), self::METHOD_PAGE => $this->settings->get(self::FEE_PAGE)];
    }

    public function saves(): bool
    {
        return true;
    }

    public function binds(): bool
    {
        return false;
    }

    public function lifetime(): \DateInterval
    {
        return new \DateInterval(self::LIFETIME);
    }

    public function start(Payment $payment, Addresses $addresses): PaymentOffer
    {
        $data = $this->init($payment, $addresses);

        if (self::METHOD_QR === $payment->getMethod()) {
            $qr = $this->call('GetQr', ['PaymentId' => $payment->getOperationId(), 'DataType' => 'PAYLOAD']);
            $payload = $qr['Data'] ?? null;

            return \is_string($payload) && '' !== $payload
                ? PaymentOffer::qr($payload)
                : throw new PaymentFailed(self::fault($qr, 'the bank gave no payment code'));
        }

        $url = $data['PaymentURL'] ?? null;

        return \is_string($url) && '' !== $url
            ? PaymentOffer::redirect($url)
            : throw new PaymentFailed(self::fault($data, 'the bank gave no payment page address'));
    }

    public function bind(Payment $binding, Addresses $addresses): PaymentOffer
    {
        throw new PaymentFailed('the bank saves a card only by a payment');
    }

    public function state(Payment $payment): ?PaymentCallback
    {
        $operation = $payment->getOperationId();
        if (null === $operation) {
            return null;
        }

        $data = $this->call('GetState', ['PaymentId' => $operation]);
        $status = self::status((string) ($data['Status'] ?? ''));
        $amount = $data['Amount'] ?? null;

        return null === $status ? null : new PaymentCallback(
            $payment->getNumber(),
            $status,
            $operation,
            is_numeric($amount) ? Money::fromMinorUnits((int) $amount) : null,
        );
    }

    public function callback(Request $request): ?PaymentCallback
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            $payload = $request->request->all();
        }

        if (!$this->verify($payload)) {
            return null;
        }

        $operation = (string) ($payload['PaymentId'] ?? '');
        $amount = $payload['Amount'] ?? null;
        $card = (string) ($payload['CardId'] ?? '');
        $pan = (string) ($payload['Pan'] ?? '');
        $date = (string) ($payload['ExpDate'] ?? '');

        return new PaymentCallback(
            (string) ($payload['OrderId'] ?? ''),
            self::status((string) ($payload['Status'] ?? '')) ?? PaymentStatus::Pending,
            '' !== $operation ? $operation : null,
            is_numeric($amount) ? Money::fromMinorUnits((int) $amount) : null,
            '' !== $card && !empty($payload['RebillId']) ? new BoundCard($card, $pan, self::expiry($date)) : null,
            4 === \strlen($date) ? CardPrint::of(substr($pan, 0, 6), substr($pan, -4), substr($date, 0, 2), '20'.substr($date, 2)) : null,
        );
    }

    public function acknowledgement(): Response
    {
        return new Response('OK');
    }

    public function charge(Payment $payment, string $token, Addresses $addresses): PaymentCallback
    {
        $rebill = $this->rebill((string) $payment->getPayer(), $token) ?? throw new PaymentFailed('the saved card is not found');
        $this->init($payment, $addresses);
        $answer = $this->call('Charge', ['PaymentId' => $payment->getOperationId(), 'RebillId' => $rebill]);

        return new PaymentCallback(
            $payment->getNumber(),
            self::status((string) ($answer['Status'] ?? '')) ?? PaymentStatus::Rejected,
            $payment->getOperationId(),
        );
    }

    public function forget(string $payer, string $token): void
    {
        $this->call('RemoveCard', ['CustomerKey' => $payer, 'CardId' => $token]);
    }

    public function close(Payment $payment, Closing $closing): void
    {
        $minor = Money::toMinorUnits($closing->amount);
        $answer = $this->call(self::CLOSING, [
            'PaymentId' => $payment->getOperationId(),
            'Receipt' => $this->receipt($closing->phone, $closing->email, [$this->item($closing->item, $minor, 1, 'full_payment', $this->settings->get(self::CLOSING_OBJECT))]) + [
                'Payments' => ['Electronic' => 0, 'AdvancePayment' => $minor],
            ],
        ]);

        if (true !== ($answer['Success'] ?? null)) {
            throw new PaymentFailed(self::fault($answer, 'the bank did not accept the closing receipt'));
        }
    }

    /** @return array<string, mixed> */
    private function init(Payment $payment, Addresses $addresses): array
    {
        if (null !== $payment->getOperationId()) {
            return [];
        }

        $method = $this->settings->get(self::PAYMENT_METHOD);
        $items = array_map(fn (array $item): array => $this->item(
            $item['name'],
            Money::toMinorUnits($item['price']),
            $item['quantity'],
            $method,
            $this->settings->get(self::PAYMENT_OBJECT),
        ), $payment->getItems());

        $request = [
            'Amount' => Money::toMinorUnits($payment->getAmount()),
            'OrderId' => $payment->getNumber(),
            'Description' => mb_substr($payment->getDescription(), 0, self::DESCRIPTION_LENGTH),
            'NotificationURL' => $addresses->callback,
            'SuccessURL' => $addresses->return,
            'FailURL' => $addresses->return,
            'Receipt' => $this->receipt($payment->getPhone(), $payment->getEmail(), $items),
        ];
        if (null !== $payment->getPayer()) {
            $request['CustomerKey'] = $payment->getPayer();
        }
        if ($payment->remembers()) {
            $request['Recurrent'] = 'Y';
        }

        $data = $this->call('Init', $request);
        $operation = $data['PaymentId'] ?? null;
        if (null === $operation) {
            throw new PaymentFailed(self::fault($data, 'the bank did not accept the order'));
        }

        $payment->setOperationId((string) $operation)->markAdvance(\in_array($method, self::ADVANCES, true));

        return $data;
    }

    private function rebill(string $payer, string $card): ?string
    {
        foreach ($this->call('GetCardList', ['CustomerKey' => $payer]) as $bound) {
            if (\is_array($bound) && (string) ($bound['CardId'] ?? '') === $card && !empty($bound['RebillId'])
                && self::CARD_ACTIVE === ($bound['Status'] ?? '')) {
                return (string) $bound['RebillId'];
            }
        }

        return null;
    }

    private static function status(string $status): ?PaymentStatus
    {
        return match (true) {
            'CONFIRMED' === $status => PaymentStatus::Confirmed,
            \in_array($status, self::FINAL_FAILURES, true) => PaymentStatus::Rejected,
            '' === $status => null,
            default => PaymentStatus::Pending,
        };
    }

    private static function expiry(string $date): ?string
    {
        return 4 === \strlen($date) ? substr($date, 0, 2).'/'.substr($date, 2) : null;
    }

    /**
     * @param list<array<string, int|string>> $items
     *
     * @return array<string, mixed>
     */
    private function receipt(?string $phone, ?string $email, array $items): array
    {
        $contacts = array_filter(['Phone' => null === $phone ? null : '+'.$phone, 'Email' => $email]);
        if ([] === $contacts) {
            throw new PaymentFailed('the receipt has neither phone nor email');
        }

        return $contacts + ['Taxation' => $this->settings->get(self::TAXATION), 'Items' => $items];
    }

    /** @return array<string, int|string> */
    private function item(string $name, int $price, int $quantity, string $method, string $object): array
    {
        return [
            'Name' => mb_substr($name, 0, self::NAME_LENGTH),
            'Price' => $price,
            'Quantity' => $quantity,
            'Amount' => $price * $quantity,
            'Tax' => $this->settings->get(self::VAT),
            'PaymentMethod' => $method,
            'PaymentObject' => $object,
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<mixed>
     */
    private function call(string $method, array $request): array
    {
        if (!$this->ready()) {
            throw new PaymentFailed('the terminal is not set up');
        }

        $request = ['TerminalKey' => $this->settings->get(self::TERMINAL_KEY)] + $request;
        $request['Token'] = $this->sign(array_filter($request, static fn (mixed $value): bool => !\is_array($value)));

        try {
            return $this->http->request('POST', $method, [
                'base_uri' => rtrim($this->settings->get(self::API_URL), '/').'/',
                'json' => $request,
                'timeout' => self::TIMEOUT,
            ])->toArray(false);
        } catch (ExceptionInterface $failure) {
            throw new PaymentFailed($failure->getMessage(), previous: $failure);
        }
    }

    /** @param array<mixed> $payload */
    private function verify(array $payload): bool
    {
        $token = (string) ($payload['Token'] ?? '');
        unset($payload['Token']);

        return hash_equals($this->sign(array_filter($payload, static fn (mixed $value): bool => !\is_array($value))), $token);
    }

    /** @param array<mixed> $values */
    private function sign(array $values): string
    {
        $values['Password'] = $this->settings->get(self::TERMINAL_PASSWORD);
        ksort($values);

        $source = '';
        foreach ($values as $value) {
            $source .= \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return hash('sha256', $source);
    }

    /** @param array<mixed> $answer */
    private static function fault(array $answer, string $otherwise): string
    {
        return (string) ($answer['Details'] ?? $answer['Message'] ?? $otherwise);
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
