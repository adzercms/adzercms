<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Notice\Incoming;
use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\ConfirmationRepository;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BotLink
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';
    private const LENGTH = 8;

    public function __construct(
        private ConfirmationRepository $confirmations,
        private ConnectedServiceRepository $services,
        private EntityManagerInterface $em,
    ) {
    }

    public function request(User $user, string $service): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }

        $this->em->persist((new Confirmation($user, null, Purpose::Messenger, Kind::Bot, self::hash($code)))->forService($service));
        $this->em->flush();

        return $code;
    }

    public function accept(string $service, Incoming $message): ?User
    {
        $confirmation = $this->confirmations->bot($service, self::hash($message->text));
        $user = $confirmation?->getUser();
        if (null === $confirmation || null === $user || !$confirmation->usable()) {
            return null;
        }

        $taken = $this->services->profile($service, $message->sender);
        if (null !== $taken && $taken->getUser() !== $user) {
            return null;
        }

        $confirmation->use();
        $known = $this->services->of($user)[$service] ?? null;
        if (null === $known) {
            $this->em->persist(new ConnectedService($user, $service, $message->sender, $message->account));
        } else {
            $known->name($message->account);
        }
        $this->em->flush();

        return $user;
    }

    private static function hash(string $code): string
    {
        return hash('sha256', mb_strtoupper(trim($code)));
    }
}
