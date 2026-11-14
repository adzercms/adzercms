<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Analytics;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Page;
use AdzerCMS\Core\Entity\Origin;
use AdzerCMS\Core\Session\Logins;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Site\Hosts;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

#[AsEventListener]
final readonly class Marks implements Owner
{
    public const COOKIE = 'analytics.marks_cookie';

    /** @var array<string, string> */
    private const KEYS = [
        'utm_source' => 'source',
        'utm_medium' => 'medium',
        'utm_campaign' => 'campaign',
        'utm_term' => 'term',
        'yclid' => 'click',
    ];

    private const DIRECT = '(direct)';
    private const SESSION_KEY = 'origin.marks';
    private const SEEN_KEY = 'origin.site';
    private const PENDING = '_marks';

    public function __construct(
        private RequestStack $requests,
        private Settings $settings,
        private Hosts $hosts,
    ) {
    }

    public function settingFields(): array
    {
        return [new Field(self::COOKIE, t('core.setting.marks_cookie'), section: t('core.setting.section.analytics'), page: Page::General)];
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod(Request::METHOD_GET) || !$request->hasSession()) {
            return;
        }

        $marks = self::of($request->query->all());
        if ([] === $marks) {
            return;
        }

        if (Hosts::SITE === $request->attributes->get(Hosts::ROLE) && '' !== $this->settings->get(self::COOKIE)) {
            $request->attributes->set(self::PENDING, array_intersect_key($request->query->all(), self::KEYS));

            return;
        }

        $request->getSession()->set(self::SESSION_KEY, $marks);
        $request->getSession()->set(self::SEEN_KEY, $this->site($request));
    }

    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $marks = $request->attributes->get(self::PENDING);
        if (!$event->isMainRequest() || !\is_array($marks)) {
            return;
        }

        $event->getResponse()->headers->setCookie(Cookie::create(
            $this->settings->get(self::COOKIE),
            json_encode($marks, \JSON_THROW_ON_ERROR),
            now()->getTimestamp() + Logins::LIFETIME,
            '/',
            $this->hosts->cookieDomain(),
            $request->isSecure(),
        ));
    }

    /** @return array<string, string> */
    public function current(): array
    {
        $request = $this->requests->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return [];
        }

        $session = $request->getSession();
        $marks = $session->get(self::SESSION_KEY, []);
        $site = $this->site($request);

        if ('' !== $site && $site !== $session->get(self::SEEN_KEY)) {
            $decoded = json_decode($site, true);
            $marks = self::of(\is_array($decoded) ? $decoded : []) ?: $marks;
        }

        return \is_array($marks) ? $marks : [];
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, string>
     */
    public static function of(array $values): array
    {
        $marks = [];

        foreach (self::KEYS as $key => $field) {
            $value = \is_scalar($values[$key] ?? null) ? trim((string) $values[$key]) : '';

            if ('' !== $value) {
                $marks[$field] = mb_substr($value, 0, 'term' === $field ? Origin::TERM_LENGTH : Origin::LENGTH);
            }
        }

        return self::DIRECT === ($marks['source'] ?? null) ? [] : $marks;
    }

    private function site(Request $request): string
    {
        $name = $this->settings->get(self::COOKIE);

        return '' === $name ? '' : trim((string) $request->cookies->get($name));
    }
}
