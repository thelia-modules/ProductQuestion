<?php

declare(strict_types=1);

/*
 * This file is part of the ProductQuestion module for Thelia 3.
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProductQuestion\Install;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Brings a database created by an earlier version of the module to the current schema,
 * keeping every row it holds.
 *
 * Up to 1.2.0 a question carried its one answer in three columns of its own (`answer`,
 * `answered_at`, `answered_by`). From 1.3.0 answers live in product_question_answer, several
 * per question, the shop's flagged official, with their helpful votes in
 * product_question_answer_vote, and the products closed to new questions in
 * product_question_closed_product. The answer a shop published becomes the official answer of
 * its question, published, with its date and its author; only then are the old columns
 * dropped.
 *
 * Every step checks the database before acting rather than trusting the version number: MySQL
 * commits each ALTER on its own, so an upgrade stopped half way leaves the module on its new
 * version with part of the work done, and the next run has to finish it without doing any
 * step twice. That is also why this is PHP and not an update SQL file: MySQL 8 has no
 * ADD COLUMN IF NOT EXISTS.
 */
final readonly class ProductQuestionSchemaUpgrader
{
    private const CREATE_ANSWER_TABLE = <<<'SQL'
        CREATE TABLE `product_question_answer`
        (
            `id` INTEGER NOT NULL AUTO_INCREMENT,
            `question_id` INTEGER NOT NULL,
            `customer_id` INTEGER,
            `admin_id` INTEGER,
            `is_official` TINYINT(1) DEFAULT 0 NOT NULL,
            `content` LONGTEXT NOT NULL,
            `status` TINYINT DEFAULT 0 NOT NULL,
            `helpful_count` INTEGER DEFAULT 0 NOT NULL,
            `published_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_product_question_answer_question_status` (`question_id`, `status`),
            INDEX `idx_product_question_answer_status` (`status`),
            INDEX `idx_product_question_answer_customer_id` (`customer_id`),
            INDEX `fi_product_question_answer_admin_id` (`admin_id`),
            CONSTRAINT `fk_product_question_answer_question_id`
                FOREIGN KEY (`question_id`)
                REFERENCES `product_question` (`id`)
                ON UPDATE RESTRICT
                ON DELETE CASCADE,
            CONSTRAINT `fk_product_question_answer_customer_id`
                FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`)
                ON UPDATE RESTRICT
                ON DELETE SET NULL,
            CONSTRAINT `fk_product_question_answer_admin_id`
                FOREIGN KEY (`admin_id`)
                REFERENCES `admin` (`id`)
                ON UPDATE RESTRICT
                ON DELETE SET NULL
        ) ENGINE=InnoDB
        SQL;

    private const CREATE_VOTE_TABLE = <<<'SQL'
        CREATE TABLE `product_question_answer_vote`
        (
            `id` INTEGER NOT NULL AUTO_INCREMENT,
            `answer_id` INTEGER NOT NULL,
            `customer_id` INTEGER,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `uq_product_question_answer_vote_answer_customer` (`answer_id`, `customer_id`),
            INDEX `idx_product_question_answer_vote_customer_id` (`customer_id`),
            CONSTRAINT `fk_product_question_answer_vote_answer_id`
                FOREIGN KEY (`answer_id`)
                REFERENCES `product_question_answer` (`id`)
                ON UPDATE RESTRICT
                ON DELETE CASCADE,
            CONSTRAINT `fk_product_question_answer_vote_customer_id`
                FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`)
                ON UPDATE RESTRICT
                ON DELETE SET NULL
        ) ENGINE=InnoDB
        SQL;

    private const CREATE_CLOSED_PRODUCT_TABLE = <<<'SQL'
        CREATE TABLE `product_question_closed_product`
        (
            `product_id` INTEGER NOT NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            PRIMARY KEY (`product_id`),
            CONSTRAINT `fk_product_question_closed_product_product_id`
                FOREIGN KEY (`product_id`)
                REFERENCES `product` (`id`)
                ON UPDATE RESTRICT
                ON DELETE CASCADE
        ) ENGINE=InnoDB
        SQL;

    /**
     * The answer of each 1.2.0 question that has one, as its official answer. It was published
     * when it was written, and its author was told then: published_at carries that date so that
     * nobody is told a second time. A question refused afterwards keeps its status and its
     * answer, which stays off the product page with it.
     */
    private const COPY_ANSWERS = <<<'SQL'
        INSERT INTO `product_question_answer`
            (`question_id`, `customer_id`, `admin_id`, `is_official`, `content`, `status`, `helpful_count`, `published_at`, `created_at`, `updated_at`)
        SELECT q.`id`, NULL, q.`answered_by`, 1, q.`answer`, 1, 0,
               COALESCE(q.`answered_at`, q.`updated_at`, q.`created_at`),
               COALESCE(q.`answered_at`, q.`updated_at`, q.`created_at`),
               COALESCE(q.`answered_at`, q.`updated_at`, q.`created_at`)
        FROM `product_question` q
        WHERE q.`answer` IS NOT NULL
          AND TRIM(q.`answer`) <> ''
          AND NOT EXISTS (
              SELECT 1 FROM `product_question_answer` a
              WHERE a.`question_id` = q.`id` AND a.`is_official` = 1
          )
        SQL;

    public function __construct(
        private ConnectionInterface|\PDO $connection,
    ) {
    }

    /**
     * Returns false when there is nothing to upgrade: the module never created its table, and
     * postActivation() will create the current schema whole.
     */
    public function upgrade(): bool
    {
        if (!$this->tableExists('product_question')) {
            return false;
        }

        if (!$this->tableExists('product_question_answer')) {
            $this->execute(self::CREATE_ANSWER_TABLE);
        }

        if (!$this->tableExists('product_question_answer_vote')) {
            $this->execute(self::CREATE_VOTE_TABLE);
        }

        if (!$this->tableExists('product_question_closed_product')) {
            $this->execute(self::CREATE_CLOSED_PRODUCT_TABLE);
        }

        if (!$this->columnExists('product_question', 'helpful_count')) {
            $this->execute('ALTER TABLE `product_question` ADD COLUMN `helpful_count` INTEGER DEFAULT 0 NOT NULL AFTER `status`');
        }

        if (!$this->columnExists('product_question', 'notify_author')) {
            $this->execute('ALTER TABLE `product_question` ADD COLUMN `notify_author` TINYINT(1) DEFAULT 1 NOT NULL AFTER `helpful_count`');
        }

        if ($this->columnExists('product_question', 'answer')) {
            // Copied first, dropped after: a copy that fails stops here, with the old columns
            // and every answer still in them.
            $this->execute(self::COPY_ANSWERS);
            $this->dropLegacyAnswerColumns();
        }

        return true;
    }

    private function dropLegacyAnswerColumns(): void
    {
        if ($this->foreignKeyExists('product_question', 'fk_product_question_answered_by')) {
            $this->execute('ALTER TABLE `product_question` DROP FOREIGN KEY `fk_product_question_answered_by`');
        }

        foreach (['answered_by', 'answered_at', 'answer'] as $column) {
            if ($this->columnExists('product_question', $column)) {
                // Dropping the column drops the one-column index Propel made for the foreign key.
                $this->execute(\sprintf('ALTER TABLE `product_question` DROP COLUMN `%s`', $column));
            }
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->count(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table],
        ) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->count(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            ['table' => $table, 'column' => $column],
        ) > 0;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return $this->count(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            ['table' => $table, 'constraint' => $constraint],
        ) > 0;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function count(string $sql, array $parameters): int
    {
        // A COUNT always yields one row: Propel's statement wrapper answers null, not false,
        // when there is no row to fetch.
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function execute(string $sql): void
    {
        $this->connection->prepare($sql)->execute();
    }
}
