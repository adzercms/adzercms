<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use ZBateson\MailMimeParser\IMessage;

final class DeliveryStatus
{
    private const TYPES = ['message/delivery-status', 'message/global-delivery-status'];
    private const CODE = '/^(?:(\d{3})[ -])?#?(?:[245]\.(\d{1,3})\.\d{1,3}(?!\d))?/';
    private const POLICY_SUBJECT = '7';
    private const POLICY_REPLY = '554';

    /** @param list<array<string, string>> $groups */
    private function __construct(private readonly array $groups)
    {
    }

    public static function of(IMessage $message): ?self
    {
        foreach (self::TYPES as $type) {
            $part = $message->getPartByMimeType($type);
            if (null !== $part) {
                return self::parse((string) $part->getContent());
            }
        }

        return null;
    }

    private static function parse(string $report): self
    {
        $groups = [];
        $group = [];
        $field = null;
        foreach (preg_split('/\R/', $report) ?: [] as $line) {
            if ('' === trim($line)) {
                if ([] !== $group) {
                    $groups[] = $group;
                }
                $group = [];
                $field = null;
            } elseif (null !== $field && (' ' === $line[0] || "\t" === $line[0])) {
                $group[$field] .= ' '.trim($line);
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $field = strtolower(trim($name));
                $group[$field] = trim($value);
            }
        }
        if ([] !== $group) {
            $groups[] = $group;
        }

        return new self($groups);
    }

    public function failure(): ?string
    {
        foreach ($this->groups as $group) {
            if ('failed' === strtolower($group['action'] ?? '')) {
                $diagnostic = trim((string) preg_replace('/^[^;]*;/', '', $group['diagnostic-code'] ?? ''));

                return '' !== $diagnostic ? $diagnostic : ($group['status'] ?? $group['action']);
            }
        }

        return null;
    }

    public static function policy(string $reason): bool
    {
        preg_match(self::CODE, $reason, $code);
        $subject = $code[2] ?? '';

        return '' !== $subject ? self::POLICY_SUBJECT === $subject : self::POLICY_REPLY === ($code[1] ?? '');
    }
}
