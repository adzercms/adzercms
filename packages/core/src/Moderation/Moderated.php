<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Moderation;

interface Moderated
{
    public function getDecision(): Decision;

    public function getReason(): ?string;

    public function decide(Decision $decision, ?string $reason = null): void;

    public function decisionNotice(): DecisionNotice;
}
