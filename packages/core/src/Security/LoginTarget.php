<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Core\Ui\Entries;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

final class LoginTarget extends DefaultAuthenticationSuccessHandler
{
    public function __construct(
        private readonly Entries $entries,
        HttpUtils $httpUtils,
    ) {
        parent::__construct($httpUtils);
    }

    /** @param array<string, mixed> $options */
    public function setOptions(array $options): void
    {
        parent::setOptions([...$options, 'default_target_path' => $this->entries->home()]);
    }
}
