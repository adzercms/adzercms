<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\StageGroup;
use Symfony\Component\DomCrawler\Crawler;

abstract class LeadsTestCase extends SchemaTestCase
{
    use Accounts;
    use Browser;

    protected const LIST = '/admin/leads';

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = $this->record('79990000001')->setRole(Role::Admin);
        $this->client->loginUser($this->operator);
        $this->em->flush();
    }

    protected function lead(StageGroup $group, string $name, ?string $phone = null, ?string $email = null, ?string $message = null, ?string $topic = null, ?Stage $stage = null, ?string $page = null): Lead
    {
        $lead = new Lead($stage ?? self::service(StageRepository::class)->first($group), $topic, $name, $phone, $email, $message, $page);
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    protected function assign(Lead $lead, ?User $manager, ?\DateTimeImmutable $due = null): void
    {
        $lead->handle($lead->getStage(), null === $manager ? null : $this->reread($manager), $due, $lead->getAmount(), $lead->getReason());
        $this->em->flush();
    }

    /** @return list<string> */
    protected static function names(Crawler $listing): array
    {
        return $listing->filter('tbody tr td:first-child .clip')->each(static fn (Crawler $name): string => trim($name->text()));
    }
}
