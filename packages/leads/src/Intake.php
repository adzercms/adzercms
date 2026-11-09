<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Attachment\Attachments;
use AdzerCMS\Core\Entity\StoredFile;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Ui\Once;
use AdzerCMS\Core\Ui\PublicForm;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\LeadAttachment;
use AdzerCMS\Leads\Repository\StageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

use function Symfony\Component\Translation\t;

final readonly class Intake
{
    public const TOPIC = 'topic';
    public const NAME = 'name';
    public const PHONE = 'phone';
    public const EMAIL = 'email';
    public const MESSAGE = 'message';
    public const FORM = 'form';
    public const FILES = 5;

    public function __construct(
        private Attachments $attachments,
        private LeadSettings $settings,
        private StageRepository $stages,
        private Subjects $subjects,
        private EntityManagerInterface $em,
        private PublicForm $form,
        private Once $once,
        private Notifier $notifier,
        private UserRepository $users,
    ) {
    }

    public static function topic(ParameterBag $input): ?string
    {
        $topic = mb_substr(trim($input->getString(self::TOPIC)), 0, Lead::TOPIC);

        return '' === $topic ? null : $topic;
    }

    /**
     * @param array<mixed> $files
     *
     * @return array<string, Refusal>
     */
    public function take(ParameterBag $input, array $files, string $page, string $address): array
    {
        if (PublicForm::trapped($input)) {
            return [];
        }

        $refusals = self::refusals($input);
        if ([] !== $refusals) {
            return $refusals;
        }

        $refusal = $this->subjects->check($input) ?? $this->form->often(self::class, $address);
        if (null !== $refusal) {
            return [self::FORM => $refusal];
        }

        $kept = $this->kept($files);
        if ($kept instanceof Refusal) {
            return [self::FORM => $kept];
        }

        $lead = $this->lead($input, mb_substr($page, 0, Lead::PAGE));
        $lead->price($this->subjects->attach($lead, $input));
        foreach ($kept as $file) {
            $this->em->persist(new LeadAttachment($lead, $file));
        }
        $this->em->flush();
        $this->notifier->each($this->users->admins(), LeadsEvent::NewLead, ['lead' => $lead], (string) $lead->getId());

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

    /**
     * @param array<mixed> $files
     *
     * @return list<StoredFile>|Refusal
     */
    private function kept(array $files): array|Refusal
    {
        $allowance = $this->settings->attachments();
        if (null === $allowance) {
            return [];
        }

        return \count($files) > self::FILES
            ? new Refusal(t('leads.refusal.files', ['limit' => self::FILES]))
            : $this->attachments->receive($files, $allowance, LeadAttachment::class);
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
