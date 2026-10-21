<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Http;

use Symfony\Component\HttpFoundation\Response;

final class TextResponse extends Response
{
    private const TYPE = 'text/plain; charset=UTF-8';

    /** @param array<string, string|list<string>> $headers */
    public function __construct(string $content, int $status = self::HTTP_OK, array $headers = [])
    {
        parent::__construct($content, $status, $headers);
        $this->headers->set('Content-Type', self::TYPE);
    }
}
