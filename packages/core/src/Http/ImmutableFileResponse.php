<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;

final class ImmutableFileResponse extends BinaryFileResponse
{
    private const AGE = 31536000;

    public function __construct(string $path, Request $request)
    {
        parent::__construct($path, autoEtag: true);
        $this->setPublic();
        $this->setMaxAge(self::AGE);
        $this->setImmutable();
        $this->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $this->isNotModified($request);
    }
}
