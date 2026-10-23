<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Money;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function Symfony\Component\Translation\t;

final readonly class ShopSettings implements Owner
{
    public const PICKUP = 'shop.pickup';
    public const DELIVERY = 'shop.delivery';
    public const DELIVERY_PRICE = 'shop.delivery_price';
    public const ON_RECEIPT = 'shop.on_receipt';

    /** @param iterable<PickupPoints> $sources */
    public function __construct(
        private Settings $settings,
        #[AutowireIterator(PickupPoints::class)]
        private iterable $sources,
    ) {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::PICKUP, t('shop.setting.pickup'), Type::Toggle),
            new Field(self::DELIVERY, t('shop.setting.delivery'), Type::Toggle),
            new Field(self::DELIVERY_PRICE, t('shop.setting.delivery_price'), Type::Decimal),
            new Field(self::ON_RECEIPT, t('shop.setting.on_receipt'), Type::Toggle),
        ];
    }

    /** @return list<PickupPoint> */
    public function points(): array
    {
        $points = [];
        foreach ($this->sources as $source) {
            array_push($points, ...$source->points());
        }

        return $points;
    }

    /** @return list<Receipt> */
    public function receipts(): array
    {
        return array_values(array_filter([
            $this->settings->on(self::PICKUP) && [] !== $this->points() ? Receipt::Pickup : null,
            $this->settings->on(self::DELIVERY) ? Receipt::Delivery : null,
        ]));
    }

    /** @return list<PaymentMethod> */
    public function payments(): array
    {
        return $this->settings->on(self::ON_RECEIPT) ? [PaymentMethod::OnReceipt] : [];
    }

    public function deliveryPrice(): ?string
    {
        $price = Money::parse($this->settings->get(self::DELIVERY_PRICE));

        return null !== $price && Money::isPositive($price) ? $price : null;
    }
}
