<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ContactKind: string implements Titled
{
    case Phone = 'phone';
    case Email = 'email';

    private const TOLL_FREE = '800';

    public static function of(string $contact): self
    {
        return null === self::Phone->normalize($contact) ? self::Email : self::Phone;
    }

    public function normalize(?string $contact): ?string
    {
        return match ($this) {
            self::Phone => self::phone((string) $contact),
            self::Email => self::email((string) $contact),
        };
    }

    public function label(): TranslatableInterface
    {
        return t('core.contact.'.$this->value);
    }

    public function channel(): string
    {
        return match ($this) {
            self::Phone => 'sms',
            self::Email => 'mail',
        };
    }

    public function href(string $contact): string
    {
        return match ($this) {
            self::Phone => 'tel:+'.$contact,
            self::Email => 'mailto:'.$contact,
        };
    }

    public function display(string $contact): string
    {
        if (self::Email === $this || 1 !== preg_match('/^7(\d{3})(\d{3})(\d{2})(\d{2})$/', $contact, $parts)) {
            return $contact;
        }

        return \sprintf('%s %s %s-%s-%s', self::TOLL_FREE === $parts[1] ? '8' : '+7', $parts[1], $parts[2], $parts[3], $parts[4]);
    }

    private static function phone(string $contact): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', $contact);
        if (11 === \strlen($digits) && str_starts_with($digits, '8')) {
            $digits = '7'.substr($digits, 1);
        }

        return 1 === preg_match('/^7\d{10}$/', $digits) ? $digits : null;
    }

    private static function email(string $contact): ?string
    {
        $email = mb_strtolower(trim($contact));

        return false !== filter_var($email, \FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
