<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Account\LanguageName;
use AdzerCMS\Core\Entity\User;
use Doctrine\DBAL\Exception as DatabaseFailure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

use function Symfony\Component\Translation\t;

#[AsTaggedItem(priority: 1)]
final class Language implements Owner
{
    public const NAME = 'brand.language';

    /** @param list<string> $locales */
    public function __construct(
        private readonly Settings $settings,
        private readonly LocaleSwitcher $switcher,
        private readonly Security $security,
        #[Autowire(param: 'kernel.enabled_locales')] private readonly array $locales,
        #[Autowire(param: 'kernel.default_locale')] private readonly string $default,
    ) {
    }

    public function settingFields(): array
    {
        return [new Field(self::NAME, t('core.setting.language'), Type::Choice, choices: $this->choices(), tab: Tab::General)];
    }

    /** @return array<string, LanguageName> */
    public function choices(): array
    {
        $locales = array_values(array_unique($this->locales));

        return array_combine($locales, array_map(static fn (string $locale): LanguageName => new LanguageName($locale), $locales));
    }

    public function instance(): string
    {
        return $this->known($this->settings->get(self::NAME)) ?? $this->default;
    }

    public function of(User $user): string
    {
        return $this->known($user->getLanguage()) ?? $this->instance();
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function speak(?User $user, callable $callback): mixed
    {
        return null === $user ? $callback() : $this->switcher->runWithLocale($this->of($user), $callback);
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $user = $request->attributes->getBoolean('_stateless') ? null : $this->security->getUser();
        $locale = $user instanceof User
            ? $this->of($user)
            : (string) $request->getPreferredLanguage([$this->instance(), ...$this->locales]);

        $request->setLocale($locale);
        $this->switcher->setLocale($locale);
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onCommand(): void
    {
        try {
            $locale = $this->instance();
        } catch (DatabaseFailure) {
            return;
        }

        $this->switcher->setLocale($locale);
    }

    private function known(?string $locale): ?string
    {
        return \in_array($locale, $this->locales, true) ? $locale : null;
    }
}
