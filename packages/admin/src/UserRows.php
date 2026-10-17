<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Ui\Columns;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class UserRows
{
    public const TEMPLATE = '@Admin/admin/_users.html.twig';
    public const STATE = 'state';

    /** @param iterable<Columns> $columns */
    public function __construct(
        private UserRepository $users,
        private LoginNames $logins,
        #[AutowireIterator(Columns::class)]
        private iterable $columns,
    ) {
    }

    /**
     * @param array<string, mixed>                 $sticky
     * @param (\Closure(QueryBuilder): mixed)|null $narrow
     *
     * @return array<string, mixed>
     */
    public function listing(Request $request, string $route, bool $archive = false, array $sticky = [], ?\Closure $narrow = null): array
    {
        $filters = [Filter::search()];
        if (!$archive) {
            $filters[] = Filter::cases(self::STATE, t('admin.users.any_state'), [Status::Active, Status::Stopped]);
        }

        $modules = array_values(array_filter([...$this->columns], static fn (Columns $columns): bool => Columns::ADMIN_USERS === $columns->listing()));
        $extra = [];
        foreach ($modules as $module) {
            foreach ($module->columns() as $column) {
                $extra[] = $column->secondary ? Column::plain($column->key, $column->label)->aside() : Column::plain($column->key, $column->label);
            }
        }

        $table = Table::of($request, $route, [
            Column::of('id', t('admin.users.login'), 's.id'),
            Column::of('created', t('admin.users.created'), 's.createdAt')->aside(),
            ...$extra,
            Column::of('state', t('admin.users.state'), 's.status'),
        ], 'created', t('admin.users.empty'), false, filters: $filters, sticky: $sticky);

        $term = trim($table->term());
        $found = [];
        if ('' !== $term) {
            foreach ($modules as $module) {
                $found = [...$found, ...$module->search($term)];
            }
        }

        $builder = $this->users->listing($term, $archive, Status::tryFrom($table->value(self::STATE)), $found);
        if (null !== $narrow) {
            $narrow($builder);
        }
        $total = (int) (clone $builder)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
        $table->apply($builder)->total($total);

        /** @var list<User> $rows */
        $rows = $builder
            ->addOrderBy('s.id', \SortDirection::Ascending)
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();
        $ids = array_map(static fn (User $user): int => (int) $user->getId(), $rows);
        $cells = [];
        foreach ($modules as $module) {
            foreach ($module->cells($ids) as $id => $values) {
                $cells[$id] = ($cells[$id] ?? []) + $values;
            }
        }

        return [
            'table' => $table,
            'archive' => $archive,
            'rows' => $rows,
            'logins' => $this->logins->ofMany($rows),
            'extra' => $extra,
            'cells' => $cells,
        ];
    }
}
