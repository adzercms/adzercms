<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Entries;
use AdzerCMS\Core\Ui\Fragment;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

final class LoginTarget extends DefaultAuthenticationSuccessHandler
{
    public const FIREWALL = 'main';

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

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $response = parent::onAuthenticationSuccess($request, $token);
        if (!$request->headers->has('HX-Request') || !$response instanceof RedirectResponse) {
            return $response;
        }
        $target = $response->getTargetUrl();

        return Back::local($target) === Back::local((string) $request->headers->get('HX-Current-URL'))
            ? Fragment::refresh()
            : Fragment::go($target);
    }
}
