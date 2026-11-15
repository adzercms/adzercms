<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use Psalm\Issue\SecurityIssue;
use Psalm\Plugin\EventHandler\BeforeAddIssueInterface;
use Psalm\Plugin\EventHandler\Event\BeforeAddIssueEvent;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;

final class SecurityIssues implements PluginEntryPointInterface, BeforeAddIssueInterface
{
    #[\Override]
    public function __invoke(RegistrationInterface $registration, ?\SimpleXMLElement $config = null): void
    {
        $registration->registerHooksFromClass(self::class);
    }

    #[\Override]
    public static function beforeAddIssue(BeforeAddIssueEvent $event): ?bool
    {
        return $event->getIssue() instanceof SecurityIssue ? null : false;
    }
}
