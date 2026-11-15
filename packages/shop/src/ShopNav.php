<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Shop\Controller\CheckoutController;
use AdzerCMS\Shop\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ShopNav implements Fragment
{
    public function __construct(private RequestStack $requests)
    {
    }

    public function place(): string
    {
        return self::SITE_NAV;
    }

    public function fragment(?int $subject): array
    {
        $route = $this->requests->getMainRequest()?->attributes->get('_route');

        return ['template' => '@Shop/_nav.html.twig', 'context' => [
            'storefront' => \in_array($route, [StorefrontController::PAGE, StorefrontController::PRODUCT], true),
            'cart' => CheckoutController::CART === $route,
        ]];
    }
}
