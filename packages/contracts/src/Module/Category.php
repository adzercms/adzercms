<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Module;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Category: string implements Titled
{
    case Payments = 'payments';
    case Accounting = 'accounting';
    case Billing = 'billing';
    case Security = 'security';
    case Messaging = 'messaging';
    case Commerce = 'commerce';
    case Content = 'content';
    case Support = 'support';
    case Marketing = 'marketing';
    case Data = 'data';
    case Other = 'other';

    private const LIMIT = 3;

    public function label(): TranslatableInterface
    {
        return t('core.category.'.$this->value);
    }

    /**
     * @param array<mixed> $keys
     *
     * @return list<self>
     */
    public static function of(array $keys): array
    {
        $chosen = [];
        foreach ($keys as $key) {
            $category = \is_string($key) ? self::tryFrom($key) : null;
            if (null !== $category && self::Other !== $category && !\in_array($category, $chosen, true)) {
                $chosen[] = $category;
            }
        }
        $chosen = \array_slice($chosen, 0, self::LIMIT);

        return [] === $chosen ? [self::Other] : self::ordered($chosen);
    }

    /**
     * @param array<self> $categories
     *
     * @return list<self>
     */
    public static function ordered(array $categories): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => \in_array($case, $categories, true)));
    }
}
