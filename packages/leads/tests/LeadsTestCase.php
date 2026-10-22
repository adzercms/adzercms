<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\StageGroup;

abstract class LeadsTestCase extends SchemaTestCase
{
    use Accounts;
    use Browser;

    protected const LIST = '/admin/leads';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    protected function lead(StageGroup $group, string $name, ?string $phone = null, ?string $email = null, ?string $message = null, ?string $topic = null, ?Stage $stage = null, ?string $page = null): Lead
    {
        $lead = new Lead($stage ?? self::service(StageRepository::class)->first($group), $topic, $name, $phone, $email, $message, $page);
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
