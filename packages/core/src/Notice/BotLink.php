<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Notice\BotListener;
use AdzerCMS\Contracts\Notice\Incoming;
use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\ConfirmationRepository;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[AsTaggedItem(priority: 1)]
final readonly class BotLink implements BotListener
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';
    private const LENGTH = 8;

    public function __construct(
        private ConfirmationRepository $confirmations,
        private ConnectedServiceRepository $services,
        private EntityManagerInterface $em,
    ) {
    }

    public static function code(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    public static function hash(string $code): string
    {
        return hash('sha256', mb_strtoupper(trim($code)));
    }

    public function request(User $user, string $service): string
    {
        $code = self::code();
        $this->em->persist((new Confirmation($user, null, Purpose::Messenger, Kind::Bot, self::hash($code)))->forService($service));
        $this->em->flush();

        return $code;
    }

    public function heard(string $bot, string $service, Incoming $message): ?TranslatableInterface
    {
        return null === $this->accept($service, $message) ? null : t('core.bot.linked');
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
}
