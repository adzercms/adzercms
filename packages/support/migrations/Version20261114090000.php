<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114090000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $categories = Table::editor()
            ->setUnquotedName('help_category')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('slug')->setTypeName(Types::STRING)->setLength(64)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_help_category_position')->setUnquotedColumnNames('position')->create(),
                Index::editor()->setUnquotedName('uniq_help_category_slug')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('slug')->create(),
            )
            ->create();

        $articles = Table::editor()
            ->setUnquotedName('help_article')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('tags')->setTypeName(Types::JSON)->create(),
                Column::editor()->setUnquotedName('body')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('published')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('heading')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('search')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('category_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('slug')->setTypeName(Types::STRING)->setLength(64)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_help_article_category')->setUnquotedColumnNames('category_id', 'position')->create(),
                Index::editor()->setUnquotedName('uniq_help_article_slug')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('category_id', 'slug')->create(),
                Index::editor()->setUnquotedName('idx_5c20f1ba12469de2')->setUnquotedColumnNames('category_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_5c20f1ba12469de2')
                    ->setUnquotedReferencingColumnNames('category_id')
                    ->setUnquotedReferencedTableName('help_category')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        $paths = Table::editor()
            ->setUnquotedName('help_article_path')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('article_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('path')->setTypeName(Types::STRING)->setLength(190)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_help_article_path_path')->setUnquotedColumnNames('path')->create(),
                Index::editor()->setUnquotedName('idx_1c9fcedb7294869c')->setUnquotedColumnNames('article_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_1c9fcedb7294869c')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('help_article')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$categories, $articles, $paths]) as $sql) {
            $this->addSql($sql);
        }
    }
}
