<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Ui\Once;
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
        private Once $once,
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

        $refusals = self::refusals($input);
        if ([] !== $refusals) {
            return $refusals;
        }

        $refusal = $this->subjects->check($input)
            ?? ($this->leadsLimiter->create($address)->consume()->isAccepted() ? null : new Refusal(t('leads.refusal.often')));
        if (null !== $refusal) {
            return [self::FORM => $refusal];
        }

        $lead = $this->lead($input, mb_substr($page, 0, Lead::PAGE));
        $lead->price($this->subjects->attach($lead, $input));
        $this->em->flush();

        return [];
    }

    /** @return Lead|array<string, Refusal> */
    public function add(ParameterBag $input): Lead|array
    {
        $refusals = self::refusals($input);
        if ([] !== $refusals) {
            return $refusals;
        }

        $fields = array_map(static fn (string $field): string => trim($input->getString($field)), [self::TOPIC, self::NAME, self::PHONE, self::EMAIL, self::MESSAGE]);
        if (!$this->once->fresh('leads.add', $fields)) {
            return [self::FORM => new Refusal(Once::repeat())];
        }

        $lead = $this->lead($input, null);
        $this->em->flush();

        return $lead;
    }

    /** @return array<string, Refusal> */
    private static function refusals(ParameterBag $input): array
    {
        $phone = trim($input->getString(self::PHONE));
        $email = trim($input->getString(self::EMAIL));

        return array_filter([
            self::NAME => mb_strlen(trim($input->getString(self::NAME))) > Lead::NAME ? new Refusal(t('leads.refusal.name', ['limit' => Lead::NAME])) : null,
            self::PHONE => '' === $phone && '' === $email
                ? new Refusal(t('leads.refusal.contact'))
                : ('' === $phone || null !== ContactKind::Phone->normalize($phone) ? null : new Refusal(t('leads.refusal.phone'))),
            self::EMAIL => '' === $email || null !== ContactKind::Email->normalize($email) ? null : new Refusal(t('leads.refusal.email')),
            self::MESSAGE => mb_strlen(trim($input->getString(self::MESSAGE))) > Lead::MESSAGE ? new Refusal(t('leads.refusal.message', ['limit' => Lead::MESSAGE])) : null,
        ]);
    }

    private function lead(ParameterBag $input, ?string $page): Lead
    {
        $name = trim($input->getString(self::NAME));
        $message = trim($input->getString(self::MESSAGE));
        $lead = new Lead(
            $this->stages->first(StageGroup::Fresh),
            self::topic($input),
            '' === $name ? null : $name,
            ContactKind::Phone->normalize(trim($input->getString(self::PHONE))),
            ContactKind::Email->normalize(trim($input->getString(self::EMAIL))),
            '' === $message ? null : $message,
            $page,
        );
        $this->em->persist($lead);

        return $lead;
    }
}
