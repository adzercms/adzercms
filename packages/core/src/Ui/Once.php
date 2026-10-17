<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final class Once
{
    private const BAG = 'once';
    private const WINDOW = 15;
    private const KEPT = 20;

    public function __construct(private readonly RequestStack $requests)
    {
    }

    public static function repeat(): TranslatableInterface
    {
        return t('core.once.repeat');
    }

    /** @param list<scalar|null> $payload */
    public function forget(string $action, array $payload): void
    {
        $session = $this->requests->getSession();
        $seen = (array) $session->get(self::BAG, []);
        unset($seen[self::key($action, $payload)]);
        $session->set(self::BAG, $seen);
    }

    /** @param list<scalar|null> $payload */
    public function fresh(string $action, array $payload): bool
    {
        $session = $this->requests->getSession();
        $key = self::key($action, $payload);
        $now = now()->getTimestamp();

        /** @var array<string, int> $seen */
        $seen = array_filter(
            (array) $session->get(self::BAG, []),
            static fn (mixed $moment): bool => \is_int($moment) && $moment > $now - self::WINDOW,
        );

        $repeat = isset($seen[$key]);
        $seen[$key] = $now;
        $session->set(self::BAG, \array_slice($seen, -self::KEPT, null, true));

        return !$repeat;
    }

    /** @param list<scalar|null> $payload */
    private static function key(string $action, array $payload): string
    {
        return hash('sha256', $action.'|'.json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
