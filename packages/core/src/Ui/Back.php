<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class Back
{
    public const FIELD = 'back';

    public function __construct(private RequestStack $requests)
    {
    }

    #[AsTwigFunction('back_field', isSafe: ['html'])]
    public function field(): string
    {
        $request = $this->requests->getCurrentRequest();

        return null === $request ? '' : sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD,
            htmlspecialchars(self::here($request), \ENT_QUOTES),
        );
    }

    public static function of(Request $request, string $fallback): string
    {
        $asked = trim((string) $request->request->get(self::FIELD));

        return self::local($asked) ?? $fallback;
    }

    public static function here(Request $request): string
    {
        return self::local((string) $request->headers->get('HX-Current-URL'))
            ?? $request->getRequestUri();
    }

    public static function local(string $value): ?string
    {
        $path = parse_url($value, \PHP_URL_PATH);

        if (!\is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        $query = parse_url($value, \PHP_URL_QUERY);

        return $path.(\is_string($query) && '' !== $query ? '?'.$query : '');
    }
}
