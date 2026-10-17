<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Session;

use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Entity\Login;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\LoginRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

use function Symfony\Component\Clock\now;

#[AsEntityListener(event: Events::postUpdate, method: 'remark', entity: User::class)]
final class Logins
{
    public const LIFETIME = 2592000;
    public const STEP = 300;
    private const HANDLE = '_login';
    private const RENEW = '_login_renew';

    public function __construct(
        private readonly LoginRepository $logins,
        private readonly EntityManagerInterface $em,
        private readonly Device $device,
        private readonly Place $place,
        private readonly RequestStack $requests,
    ) {
    }

    public function open(Request $request, User $user): void
    {
        $address = $request->getClientIp();
        $login = new Login($user, $this->device->label((string) $request->headers->get('User-Agent')), $this->place->of($address), $address);
        $this->em->persist($login);
        $request->getSession()->set(self::HANDLE, $login->getHandle());
    }

    public function live(Request $request): bool
    {
        $handle = self::current($request);
        if (null === $handle) {
            return true;
        }

        $seen = $this->logins->seen($handle);
        if (null === $seen) {
            return false;
        }

        if ($seen <= now()->modify(\sprintf('-%d seconds', self::STEP))) {
            $this->logins->see($handle);
            $request->attributes->set(self::RENEW, true);
        }

        return true;
    }

    public function remark(User $user): void
    {
        $request = $this->requests->getMainRequest();
        $handle = null === $request ? null : self::current($request);
        if (null === $handle) {
            return;
        }

        if (Status::Active === $user->getStatus()) {
            $this->logins->remark($handle, $user);
        } else {
            $this->logins->drop($user, [$handle]);
        }
    }

    #[AsEventListener]
    public function renew(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (true !== $request->attributes->get(self::RENEW)) {
            return;
        }

        $session = $request->getSession();
        $cookie = session_get_cookie_params();
        $event->getResponse()->headers->setCookie(Cookie::create(
            $session->getName(),
            $session->getId(),
            now()->getTimestamp() + self::LIFETIME,
            $cookie['path'],
            $cookie['domain'],
            $cookie['secure'],
            $cookie['httponly'],
            false,
            strtolower($cookie['samesite']),
        ));
    }

    /** @return list<Login> */
    public function of(User $user): array
    {
        $outdated = self::outdated();
        $live = $dropped = [];
        foreach ($this->logins->of($user) as $login) {
            if ($login->getSeenAt() < $outdated || !$user->keeps($login->mark())) {
                $dropped[] = $login->getHandle();
            } else {
                $live[] = $login;
            }
        }
        $this->logins->drop($user, $dropped);

        return $live;
    }

    public function end(User $user, string $handle): bool
    {
        return 0 < $this->logins->drop($user, [$handle]);
    }

    public function endOthers(User $user, Request $request): int
    {
        return $this->logins->dropOthers($user, self::current($request));
    }

    public function close(Request $request): void
    {
        $handle = self::current($request);
        if (null !== $handle) {
            $this->logins->close($handle);
        }
    }

    public function sweep(): int
    {
        return $this->logins->sweep(self::outdated());
    }

    public static function current(Request $request): ?string
    {
        $handle = $request->hasSession() ? $request->getSession()->get(self::HANDLE) : null;

        return \is_string($handle) ? $handle : null;
    }

    private static function outdated(): \DateTimeImmutable
    {
        return now()->modify(\sprintf('-%d seconds', self::LIFETIME + self::STEP));
    }
}
