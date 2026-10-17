<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Component\HttpFoundation\Response;

final class Fragment
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array{template: string, context: array<string, mixed>}|null
     */
    public static function live(array $context): ?array
    {
        $fragment = $context['fragment'] ?? null;

        if (!\is_array($fragment) || !\is_string($fragment['live'] ?? null)) {
            return null;
        }

        return ['template' => $fragment['live'], 'context' => $fragment['context'] + $context];
    }

    public static function go(string $url): Response
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('HX-Redirect', $url);

        return $response;
    }
}
