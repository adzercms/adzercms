<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class ScriptNonce
{
    private const ATTRIBUTE = '_script_nonce';
    private const BYTES = 16;

    public function __construct(private RequestStack $requests)
    {
    }

    #[AsTwigFunction('script_nonce')]
    public function value(): string
    {
        $request = $this->requests->getMainRequest();
        if (null === $request) {
            return '';
        }

        $nonce = self::of($request);
        if (null === $nonce) {
            $nonce = base64_encode(random_bytes(self::BYTES));
            $request->attributes->set(self::ATTRIBUTE, $nonce);
        }

        return $nonce;
    }

    public static function of(Request $request): ?string
    {
        $nonce = $request->attributes->get(self::ATTRIBUTE);

        return \is_string($nonce) ? $nonce : null;
    }
}
