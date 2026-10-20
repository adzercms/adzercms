<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Component\HttpFoundation\Request;

interface ContentSource
{
    public const FORM = 'form-action';
    public const SCRIPT = 'script-src';
    public const CONNECT = 'connect-src';
    public const FRAME = 'frame-src';
    public const STYLE = 'style-src';
    public const IMG = 'img-src';
    public const WORKER = 'worker-src';

    /** @return array<self::FORM|self::SCRIPT|self::CONNECT|self::FRAME|self::STYLE|self::IMG|self::WORKER, list<string>> */
    public function sources(Request $request): array;
}
