<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Repository\StageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

use function Symfony\Component\Translation\t;

final readonly class Intake
{
    public const LIMITER = 'leads';
    public const LIMIT = 5;
    public const INTERVAL = '10 minutes';
    public const TOPIC = 'topic';
    public const NAME = 'name';
    public const PHONE = 'phone';
    public const EMAIL = 'email';
    public const MESSAGE = 'message';
    public const TRAP = 'website';
    public const FORM = 'form';

    public function __construct(
        private StageRepository $stages,
        private Subjects $subjects,
        private EntityManagerInterface $em,
        private RateLimiterFactoryInterface $leadsLimiter,
    ) {
    }

    public static function topic(ParameterBag $input): ?string
    {
        $topic = mb_substr(trim($input->getString(self::TOPIC)), 0, Lead::TOPIC);

        return '' === $topic ? null : $topic;
    }

    /** @return array<string, Refusal> */
    public function take(ParameterBag $input, string $page, string $address): array
    {
        if ('' !== trim($input->getString(self::TRAP))) {
            return [];
        }

        $name = trim($input->getString(self::NAME));
        $message = trim($input->getString(self::MESSAGE));
        $phone = trim($input->getString(self::PHONE));
        $email = trim($input->getString(self::EMAIL));
        $refusals = array_filter([
            self::NAME => mb_strlen($name) > Lead::NAME ? new Refusal(t('leads.refusal.name', ['limit' => Lead::NAME])) : null,
            self::PHONE => '' === $phone && '' === $email
                ? new Refusal(t('leads.refusal.contact'))
                : ('' === $phone || null !== ContactKind::Phone->normalize($phone) ? null : new Refusal(t('leads.refusal.phone'))),
            self::EMAIL => '' === $email || null !== ContactKind::Email->normalize($email) ? null : new Refusal(t('leads.refusal.email')),
            self::MESSAGE => mb_strlen($message) > Lead::MESSAGE ? new Refusal(t('leads.refusal.message', ['limit' => Lead::MESSAGE])) : null,
        ]);
        if ([] !== $refusals) {
            return $refusals;
        }

        $refusal = $this->subjects->check($input)
            ?? ($this->leadsLimiter->create($address)->consume()->isAccepted() ? null : new Refusal(t('leads.refusal.often')));
        if (null !== $refusal) {
            return [self::FORM => $refusal];
        }

        $lead = new Lead(
            $this->stages->first(StageGroup::Fresh),
            self::topic($input),
            '' === $name ? null : $name,
            ContactKind::Phone->normalize($phone),
            ContactKind::Email->normalize($email),
            '' === $message ? null : $message,
            mb_substr($page, 0, Lead::PAGE),
        );
        $this->em->persist($lead);
        $lead->price($this->subjects->attach($lead, $input));
        $this->em->flush();

        return [];
    }
}
