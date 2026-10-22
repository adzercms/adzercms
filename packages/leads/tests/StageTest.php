<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\StageGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class StageTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const LIST = '/admin/leads/stages';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    #[Group('ACMS-LEAD-0003')]
    #[Group('ACMS-US-0041')]
    public function testFunnelGoesByGroupsThenManualOrderWithinGroup(): void
    {
        self::assertSame(array_map(static fn (StageGroup $group): string => self::phrase($group->label()), StageGroup::cases()), $this->labels(), 'the migration lays an unlabeled stage per group named by the group');

        $this->post(self::LIST.'/new', ['label' => 'Звонок', 'group' => StageGroup::Work->value]);
        self::assertResponseHeaderSame('HX-Redirect', self::LIST);
        $this->post(self::LIST.'/new', ['label' => 'Замер', 'group' => StageGroup::Work->value]);
        $work = self::service(StageRepository::class)->ofGroup(StageGroup::Work);
        self::assertSame([self::phrase(StageGroup::Fresh->label()), self::phrase(StageGroup::Work->label()), 'Звонок', 'Замер', self::phrase(StageGroup::Done->label()), self::phrase(StageGroup::Refused->label())], $this->labels());

        $listing = $this->post(self::LIST.'/order', [Order::FIELD => [(string) $work[2]->getId(), (string) $work[0]->getId(), (string) $work[1]->getId()]]);
        self::assertSame(
            [self::phrase(StageGroup::Fresh->label()), 'Замер', self::phrase(StageGroup::Work->label()), 'Звонок', self::phrase(StageGroup::Done->label()), self::phrase(StageGroup::Refused->label())],
            $listing->filter('tbody tr td:nth-child(2)')->each(static fn (Crawler $cell): string => trim($cell->text())),
        );
    }

    #[Group('ACMS-LEAD-0003')]
    #[Group('ACMS-US-0041')]
    #[DataProvider('drops')]
    public function testStageWithLeadsOrLastOfGroupStays(bool $held, bool $last, ?string $refusal): void
    {
        $stage = $last ? self::service(StageRepository::class)->ofGroup(StageGroup::Done)[0] : $this->stage('Договор', StageGroup::Done);
        if ($held) {
            $this->em->persist(new Lead($stage, null, 'Анна', '79990000002', null, null));
            $this->em->flush();
        }

        $this->post(self::LIST.'/'.$stage->getId().'/drop');
        $page = $this->arrive(self::LIST);

        self::assertSame(null === $refusal ? self::phrase('leads.flash.stage_dropped') : self::phrase($refusal), self::toast($page, null === $refusal ? 'success' : 'error'));
        self::assertSame(null !== $refusal, null !== self::service(StageRepository::class)->find((int) $stage->getId()));
    }

    /** @return iterable<string, array{bool, bool, ?string}> */
    public static function drops(): iterable
    {
        yield 'stage of a group with another stage' => [false, false, null];
        yield 'stage holding a lead' => [true, false, 'leads.refusal.stage_held'];
        yield 'last stage of its group' => [false, true, 'leads.refusal.stage_last'];
    }

    private function stage(string $label, StageGroup $group): Stage
    {
        $this->post(self::LIST.'/new', ['label' => $label, 'group' => $group->value]);
        $this->arrive(self::LIST);

        return self::service(StageRepository::class)->findOneBy(['label' => $label]) ?? self::fail('the stage is not added');
    }

    /** @return list<string> */
    private function labels(): array
    {
        return $this->fragment(self::LIST)->filter('tbody tr td:nth-child(2)')->each(static fn (Crawler $cell): string => trim($cell->text()));
    }
}
