<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Site;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final readonly class Hosts
{
    public const ROLE = '_host';
    public const SITE = 'site';
    public const SHARED = 'shared';
    public const PARAMETER = '_hosts';

    private const DOMAIN_LABELS = 2;

    private ?RequestContext $site;
    private RequestContext $default;

    public function __construct(
        #[Autowire('%env(default::SITE_URI)%')] ?string $site,
        #[Autowire('%env(DEFAULT_URI)%')] string $default,
    ) {
        $this->site = null === $site || '' === $site ? null : RequestContext::fromUri($site);
        $this->default = RequestContext::fromUri($default);
    }

    public static function bind(RouteCollection $routes): void
    {
        foreach ($routes as $route) {
            $role = $route->getDefault(self::ROLE);
            if (self::SHARED === $role) {
                continue;
            }
            $served = \sprintf('context.getParameter("%s").serves(%s, context.getHost())', self::PARAMETER, self::SITE === $role ? '"'.self::SITE.'"' : 'null');
            $condition = $route->getCondition();
            $route->setCondition('' === $condition ? $served : '('.$condition.') and '.$served);
        }
    }

    public function serves(?string $role, string $host): bool
    {
        return null === $this->site || self::SHARED === $role || (self::SITE === $role) === ($this->site->getHost() === $host);
    }

    public function target(?string $role, RequestContext $current): ?RequestContext
    {
        if ($this->serves($role, $current->getHost())) {
            return null;
        }

        return self::with(self::SITE === $role ? $this->site ?? $this->default : $this->default, $current);
    }

    public function other(RequestContext $current): ?RequestContext
    {
        return null === $this->site ? null : self::with($this->site->getHost() === $current->getHost() ? $this->default : $this->site, $current);
    }

    public function cookieDomain(): ?string
    {
        if (null === $this->site) {
            return null;
        }

        $other = array_reverse(explode('.', $this->default->getHost()));
        $common = [];
        foreach (array_reverse(explode('.', $this->site->getHost())) as $index => $label) {
            if (($other[$index] ?? null) !== $label) {
                break;
            }
            $common[] = $label;
        }

        return \count($common) < self::DOMAIN_LABELS ? null : implode('.', array_reverse($common));
    }

    public function root(RequestContext $current): string
    {
        $site = $this->target(self::SITE, $current);

        return null === $site ? $current->getBaseUrl().'/' : self::url($site, '/');
    }

    /** @return array<string, mixed> */
    public static function match(RouterInterface $router, RequestContext $host, string $path): array
    {
        $own = $router->getContext();
        $router->setContext($host);
        try {
            return $router->match($path);
        } finally {
            $router->setContext($own);
        }
    }

    public static function url(RequestContext $host, string $path): string
    {
        $port = match ($host->getScheme()) {
            'http' => 80 === $host->getHttpPort() ? '' : ':'.$host->getHttpPort(),
            'https' => 443 === $host->getHttpsPort() ? '' : ':'.$host->getHttpsPort(),
            default => '',
        };

        return $host->getScheme().'://'.$host->getHost().$port.$host->getBaseUrl().$path;
    }

    private static function with(RequestContext $host, RequestContext $current): RequestContext
    {
        return (clone $host)->setParameters($current->getParameters())->setMethod($current->getMethod());
    }
}
