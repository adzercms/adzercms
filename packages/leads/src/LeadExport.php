<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\Cell;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\ModuleColumns;
use AdzerCMS\Leads\Entity\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Symfony\Component\Translation\t;

final readonly class LeadExport
{
    private const BOM = "\xEF\xBB\xBF";
    private const SEPARATOR = ';';
    private const ENCLOSURE = '"';
    private const ESCAPE = '';
    private const FILE = 'leads.csv';
    private const BATCH = 200;
    private const FORMULA = '/^[=+\-@\t\r]/';

    public function __construct(
        private LeadRows $rows,
        private ModuleColumns $modules,
        private Handling $handling,
        private EntityManagerInterface $em,
    ) {
    }

    /** @param non-empty-list<Slice> $slices */
    public function response(Request $request, string $route, array $slices): StreamedResponse
    {
        $lines = $this->lines($request, $route, $slices);

        return new StreamedResponse(static function () use ($lines): void {
            $output = fopen('php://output', 'w');
            if (false === $output) {
                throw new \RuntimeException('the export output did not open');
            }

            fwrite($output, self::BOM);
            foreach ($lines as $line) {
                fputcsv($output, $line, self::SEPARATOR, self::ENCLOSURE, self::ESCAPE);
            }
            fclose($output);
        }, headers: [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, self::FILE),
        ]);
    }

    /**
     * @param non-empty-list<Slice> $slices
     *
     * @return \Generator<list<string>>
     */
    private function lines(Request $request, string $route, array $slices): \Generator
    {
        [, $builder] = $this->rows->selection($request, $route, $slices, []);
        $extra = $this->modules->columns(LeadSubject::LISTING);
        $managers = $this->handling->managers();

        yield self::line([
            t('leads.column.number'),
            t('leads.field.name'),
            t('leads.field.topic'),
            t('leads.field.phone'),
            t('leads.field.email'),
            t('leads.field.message'),
            ...array_map(static fn (Column $column): mixed => $column->label, $extra),
            t('leads.field.amount'),
            t('leads.field.manager'),
            t('leads.field.due'),
            t('leads.field.arrived'),
            t('leads.field.stage'),
            t('leads.field.reason'),
            t('leads.field.page'),
        ]);

        $batch = [];
        foreach ($builder->getQuery()->toIterable() as $lead) {
            \assert($lead instanceof Lead);
            $batch[] = $lead;
            if (self::BATCH === \count($batch)) {
                yield from $this->batch($batch, $extra, $managers);
                $batch = [];
                $this->em->clear();
            }
        }
        yield from $this->batch($batch, $extra, $managers);
    }

    /**
     * @param list<Lead>         $leads
     * @param list<Column>       $extra
     * @param array<int, string> $managers
     *
     * @return list<list<string>>
     */
    private function batch(array $leads, array $extra, array $managers): array
    {
        $cells = $this->modules->cells(LeadSubject::LISTING, array_map(static fn (Lead $lead): int => (int) $lead->getId(), $leads));

        return array_map(static fn (Lead $lead): array => self::line([
            $lead->getId(),
            $lead->getName(),
            $lead->getTopic(),
            $lead->getPhone(),
            $lead->getEmail(),
            $lead->getMessage(),
            ...array_map(static fn (Column $column): ?string => self::cell($cells[(int) $lead->getId()][$column->key] ?? null), $extra),
            $lead->getAmount(),
            $managers[$lead->getManager()?->getId() ?? 0] ?? null,
            $lead->getDue(),
            $lead->getArrived(),
            $lead->getStage()->label(),
            $lead->getReason(),
            $lead->getPage(),
        ]), $leads);
    }

    private static function cell(?Cell $cell): ?string
    {
        return $cell?->text;
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<string>
     */
    private static function line(array $values): array
    {
        return array_map(static function (mixed $value): string {
            $text = Column::scalar($value);

            return 1 === preg_match(self::FORMULA, $text) ? "'".$text : $text;
        }, $values);
    }
}
