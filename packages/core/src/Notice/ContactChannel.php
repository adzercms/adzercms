<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Core\Account\ContactKind;

abstract class ContactChannel implements Channel
{
    abstract protected function kind(): ContactKind;

    public function key(): string
    {
        return $this->kind()->channel();
    }

    public function recipients(array $users): array
    {
        if (!$this->ready()) {
            return [];
        }

        $recipients = [];
        foreach ($users as $at => $user) {
            $contact = $user->getContact($this->kind());
            if (null !== $contact) {
                $recipients[$at] = $contact;
            }
        }

        return $recipients;
    }
}
