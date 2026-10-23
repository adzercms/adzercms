<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Payments\PaymentService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function Symfony\Component\Translation\t;

final readonly class ShopSettings implements Owner
{
    public const PICKUP = 'shop.pickup';
    public const DELIVERY = 'shop.delivery';
    public const DELIVERY_PRICE = 'shop.delivery_price';
    public const ONLINE = 'shop.online';
    public const ON_RECEIPT = 'shop.on_receipt';

    /** @param iterable<PickupPoints> $sources */
    public function __construct(
        private Settings $settings,
        #[AutowireIterator(PickupPoints::class)]
        private iterable $sources,
        private ?PaymentService $acquiring = null,
    ) {
    }

    public function settingFields(): array
    {
        return array_values(array_filter([
            new Field(self::PICKUP, t('shop.setting.pickup'), Type::Toggle),
            new Field(self::DELIVERY, t('shop.setting.delivery'), Type::Toggle),
            new Field(self::DELIVERY_PRICE, t('shop.setting.delivery_price'), Type::Decimal),
            null === $this->acquiring ? null : new Field(self::ONLINE, t('shop.setting.online'), Type::Toggle),
            new Field(self::ON_RECEIPT, t('shop.setting.on_receipt'), Type::Toggle),
        ]));
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

    /** @return list<PaymentChoice> */
    public function payments(): array
    {
        $choices = [];
        if (null !== $this->acquiring && $this->settings->on(self::ONLINE)) {
            foreach ($this->acquiring->methods() as $method => $label) {
                $choices[] = new PaymentChoice(PaymentMethod::Online, $method, $label);
            }
        }
        if ($this->settings->on(self::ON_RECEIPT)) {
            $choices[] = new PaymentChoice(PaymentMethod::OnReceipt, null, PaymentMethod::OnReceipt->label());
        }

        return $choices;
    }

    public function deliveryPrice(): ?string
    {
        $price = Money::parse($this->settings->get(self::DELIVERY_PRICE));

        return null !== $price && Money::isPositive($price) ? $price : null;
    }
}
