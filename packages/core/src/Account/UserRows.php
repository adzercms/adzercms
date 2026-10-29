<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Ui\Columns;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\ModuleColumns;
use AdzerCMS\Core\Ui\Table;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class UserRows
{
    public const TEMPLATE = '@Admin/admin/_users.html.twig';
    public const STATE = 'state';

    public function __construct(
        private UserRepository $users,
        private LoginNames $logins,
        private ModuleColumns $modules,
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
            $filters[] = Filter::cases(self::STATE, t('core.user.any_state'), [Status::Active, Status::Stopped])->on('s.status');
        }

        $extra = $this->modules->columns(Columns::ADMIN_USERS);

        $table = Table::of($request, $route, [
            Column::of('id', t('core.user.login'), 's.id'),
            Column::of('created', t('core.user.created'), 's.createdAt')->aside(),
            ...$extra,
            Column::of('state', t('core.user.state'), 's.status'),
        ], 'created', t('core.user.empty'), false, filters: $filters, sticky: $sticky);

        $term = trim($table->term());

        $builder = $this->users->listing($term, $archive, $this->modules->search(Columns::ADMIN_USERS, $term));
        if (null !== $narrow) {
            $narrow($builder);
        }
        $this->modules->order(Columns::ADMIN_USERS, $table, $builder, 's.id');
        $table->apply($builder);

        /** @var list<User> $rows */
        $rows = $table->paginate($builder->addOrderBy('s.id', \SortDirection::Ascending)->getQuery());

        return [
            'table' => $table,
            'archive' => $archive,
            'rows' => $rows,
            'logins' => $this->logins->ofMany($rows),
            'extra' => $extra,
            'cells' => $this->modules->cells(Columns::ADMIN_USERS, array_map(static fn (User $user): int => (int) $user->getId(), $rows)),
        ];
    }
}
