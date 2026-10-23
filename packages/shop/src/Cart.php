<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Money;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\Translation\t;

final readonly class Cart
{
    public const LIMIT = 99;
    private const KEY = 'shop.cart';

    public function __construct(
        private RequestStack $requests,
        private Products $products,
    ) {
    }

    /** @return list<CartLine> */
    public function lines(): array
    {
        $held = $this->held();
        if ([] === $held) {
            return [];
        }

        $products = [];
        foreach ($this->products->active(array_values(array_unique(array_map(static fn (int|string $key): int => CartLine::ids((string) $key)[0], array_keys($held))))) as $product) {
            $products[$product->getId()] = $product;
        }

        $lines = [];
        $kept = [];
        foreach ($held as $key => $quantity) {
            [$id, $variant] = CartLine::ids((string) $key);
            $product = $products[$id] ?? null;
            $chosen = null === $variant ? null : $product?->variant($variant);
            if (null === $product || (null === $variant ? [] !== $product->getVariants() : null === $chosen)) {
                continue;
            }
            $kept[$key] = $quantity;
            $lines[] = new CartLine((string) $key, $product, $chosen, $quantity);
        }
        if ($kept !== $held) {
            $this->keep($kept);
        }

        return $lines;
    }

    #[AsTwigFunction('cart_count')]
    public function count(): int
    {
        return array_sum(array_map(static fn (CartLine $line): int => $line->quantity, $this->lines()));
    }

    /** @param list<CartLine> $lines */
    public static function total(array $lines): string
    {
        return array_reduce($lines, static fn (string $total, CartLine $line): string => Money::add($total, $line->sum()), '0');
    }

    public function add(int $id, ?int $variant, string $quantity): CartLine|Refusal
    {
        $product = $this->products->active([$id])[0] ?? null;
        if (null === $product) {
            return new Refusal(t('shop.refusal.gone'));
        }
        $chosen = null === $variant ? null : $product->variant($variant);
        if ([] !== $product->getVariants() && null === $chosen) {
            return new Refusal(t('shop.refusal.choose'));
        }
        $amount = self::quantity($quantity, 1);
        if (null === $amount) {
            return new Refusal(t('shop.refusal.quantity', ['limit' => self::LIMIT]));
        }

        $key = CartLine::key($product, $chosen);
        $held = $this->held();
        $held[$key] = min(self::LIMIT, ($held[$key] ?? 0) + $amount);
        $this->keep($held);

        return new CartLine($key, $product, $chosen, $held[$key]);
    }

    public function change(string $key, string $quantity): CartLine|Refusal|null
    {
        $line = array_find($this->lines(), static fn (CartLine $line): bool => $key === $line->key);
        if (null === $line) {
            return null;
        }
        $amount = self::quantity($quantity, 0);
        if (null === $amount) {
            return new Refusal(t('shop.refusal.quantity', ['limit' => self::LIMIT]));
        }

        $held = $this->held();
        if (0 === $amount) {
            unset($held[$key]);
        } else {
            $held[$key] = $amount;
        }
        $this->keep($held);

        return new CartLine($key, $line->product, $line->variant, $amount);
    }

    public function clear(): void
    {
        $this->keep([]);
    }

    private static function quantity(string $input, int $least): ?int
    {
        $input = trim($input);

        return ctype_digit($input) && (int) $input >= $least && (int) $input <= self::LIMIT ? (int) $input : null;
    }

    /** @return array<int|string, int> */
    private function held(): array
    {
        $request = $this->requests->getCurrentRequest();
        if (null === $request || !$request->hasPreviousSession()) {
            return [];
        }
        $held = $request->getSession()->get(self::KEY);

        return \is_array($held) ? $held : [];
    }

    /** @param array<int|string, int> $held */
    private function keep(array $held): void
    {
        $session = $this->requests->getCurrentRequest()?->getSession();
        if ([] === $held) {
            $session?->remove(self::KEY);
        } else {
            $session?->set(self::KEY, $held);
        }
    }
}
