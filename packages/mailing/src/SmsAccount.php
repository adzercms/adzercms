<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing;

use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\Contracts\Notice\SmsGateway;
use AdzerCMS\Core\Module\Snapshot;
use AdzerCMS\Core\Module\Switches;

final readonly class SmsAccount
{
    public const FIELDS = ['id', 'name', 'email', 'phone', 'balance', 'senders'];

    public function __construct(
        private Switches $switches,
        private Snapshot $store,
    ) {
    }

    /** @return array{at: \DateTimeImmutable, rows: array<string, string>}|null */
    public function stored(): ?array
    {
        $key = $this->key();
        $held = null === $key ? null : $this->store->read($key);

        return null === $held ? null : ['at' => $held['at'], 'rows' => self::rows($held['rows'])];
    }

    /** @return array{at: \DateTimeImmutable, rows: array<string, string>}|string|null */
    public function fresh(): array|string|null
    {
        $enabled = $this->switches->enabled(SmsGateway::class);
        $key = array_key_first($enabled);

        if (null === $key) {
            return null;
        }

        [$gateway, $credentials] = $enabled[$key];

        try {
            $contract = $gateway->contract($credentials);
        } catch (SmsFailed $failure) {
            return $failure->getMessage();
        }

        return ['at' => $this->store->write(self::snapshot($key), $contract), 'rows' => self::rows($contract)];
    }

    /** @return array{at: \DateTimeImmutable, rows: array<string, string>}|string|null */
    public function of(bool $rescan): array|string|null
    {
        return $rescan ? $this->fresh() : $this->stored();
    }

    /**
     * @param array{at: \DateTimeImmutable, rows: array<string, string>}|string|null $taken
     *
     * @return array<string, mixed>
     */
    public static function context(array|string|null $taken): array
    {
        return [
            'account' => \is_array($taken) ? $taken['rows'] : null,
            'account_at' => \is_array($taken) ? $taken['at'] : null,
            'account_fault' => \is_string($taken) ? $taken : null,
            'account_fields' => self::FIELDS,
        ];
    }

    private function key(): ?string
    {
        $key = array_key_first($this->switches->enabled(SmsGateway::class));

        return null === $key ? null : self::snapshot($key);
    }

    private static function snapshot(string $key): string
    {
        return 'mailing.contract.'.$key;
    }

    /**
     * @param array<string, mixed> $held
     *
     * @return array<string, string>
     */
    private static function rows(array $held): array
    {
        $rows = [];

        foreach (self::FIELDS as $key) {
            if (\is_scalar($held[$key] ?? null)) {
                $rows[$key] = (string) $held[$key];
            }
        }

        return $rows;
    }
}
