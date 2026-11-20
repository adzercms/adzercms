<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Moderation;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Notice\Notifier;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Translation\t;

final readonly class Moderation
{
    public function __construct(
        private EntityManagerInterface $em,
        private Notifier $notifier,
    ) {
    }

    public function decide(Moderated $subject, ?Decision $decision, string $reason): ?Refusal
    {
        if (null === $decision || !\in_array($decision, $subject->getDecision()->next(), true)) {
            return new Refusal(t('core.moderation.refusal.decision'));
        }

        $reason = trim($reason);
        if (Decision::Published !== $decision && '' === $reason) {
            return new Refusal(t('core.moderation.refusal.reason'));
        }

        $subject->decide($decision, Decision::Published === $decision ? null : $reason);
        $notice = $subject->decisionNotice();
        $this->em->flush();
        $this->notifier->notify($notice->author, $notice->event, $notice->context + ['reason' => $subject->getReason()],
            implode("\0", [$notice->item, $decision->value, (string) $subject->getReason()]));

        return null;
    }
}
