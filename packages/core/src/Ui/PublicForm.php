<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Consents;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\Translation\t;

final readonly class PublicForm
{
    public const LIMITER = 'public_form';
    public const LIMIT = 5;
    public const INTERVAL = '10 minutes';
    public const TRAP = 'website';

    public function __construct(
        private RateLimiterFactoryInterface $publicFormLimiter,
        private Consents $consents,
        private TranslatorInterface $translator,
    ) {
    }

    public static function trapped(ParameterBag $input): bool
    {
        return '' !== trim($input->getString(self::TRAP));
    }

    public function often(string $form, string $address): ?Refusal
    {
        return $this->publicFormLimiter->create($form.' '.$address)->consume()->isAccepted() ? null : new Refusal(t('core.form.often'));
    }

    #[AsTwigFunction('trap_field', isSafe: ['html'])]
    public function trap(): string
    {
        return sprintf('<input class="trap" type="text" name="%s" tabindex="-1" autocomplete="off" aria-hidden="true">', self::TRAP);
    }

    #[AsTwigFunction('privacy_note', isSafe: ['html'])]
    public function privacy(): string
    {
        return sprintf(
            '<p class="hint">%s</p>',
            $this->translator->trans('core.form.privacy', ['policy' => $this->consents->legal('privacy', $this->translator->trans('core.form.policy'))]),
        );
    }
}
