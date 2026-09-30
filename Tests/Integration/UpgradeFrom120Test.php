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

namespace ProductQuestion\Tests\Integration;

use ProductQuestion\Install\ProductQuestionSchemaUpgrader;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Install\Database;
use Thelia\Test\IntegrationTestCase;

/**
 * A shop that ran 1.2.0 upgrades without losing an answer.
 *
 * The upgrade runs on a scratch database built from the 1.2.0 schema as it was published
 * (Tests/Fixtures/schema-1.2.0.sql), next to the test database rather than inside it: an ALTER
 * commits on its own and would end the transaction the other tests are isolated by.
 */
final class UpgradeFrom120Test extends IntegrationTestCase
{
    private const SCRATCH = 'test_product_question_upgrade';

    protected bool $useTransaction = false;

    private ConnectionInterface $con;

    private string $home;

    protected function setUp(): void
    {
        parent::setUp();

        $this->con = $this->getPropelConnection();
        $this->home = (string) $this->con->query('SELECT DATABASE()')->fetchColumn();

        $this->con->exec(\sprintf('DROP DATABASE IF EXISTS `%s`', self::SCRATCH));
        $this->con->exec(\sprintf('CREATE DATABASE `%s`', self::SCRATCH));
        $this->con->exec(\sprintf('USE `%s`', self::SCRATCH));

        // What the 1.2.0 foreign keys point at, reduced to the keys.
        foreach (['product', 'customer', 'admin'] as $table) {
            $this->con->exec(\sprintf('CREATE TABLE `%s` (`id` INTEGER NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ENGINE=InnoDB', $table));
            $this->con->exec(\sprintf('INSERT INTO `%s` (`id`) VALUES (1), (2)', $table));
        }

        (new Database($this->con))->insertSql(null, [\dirname(__DIR__).'/Fixtures/schema-1.2.0.sql']);
    }

    protected function tearDown(): void
    {
        $this->con->exec(\sprintf('USE `%s`', $this->home));
        $this->con->exec(\sprintf('DROP DATABASE IF EXISTS `%s`', self::SCRATCH));

        parent::tearDown();
    }

    public function testEveryPublishedAnswerBecomesTheOfficialAnswerOfItsQuestion(): void
    {
        $this->insert120Question(1, 1, 'Is it oak?', 1, 'Yes, solid oak.', '2026-09-01 10:00:00', 2);
        $this->insert120Question(2, 2, 'Still waiting', 0, null, null, null);
        // Answered, then refused: the draft was published once and stays the trace.
        $this->insert120Question(3, 1, 'Refused later', 2, 'We cannot say.', '2026-09-02 11:00:00', null);

        self::assertTrue((new ProductQuestionSchemaUpgrader($this->con))->upgrade());

        $questions = $this->rows('SELECT id, status, customer_id, content, helpful_count, notify_author FROM product_question ORDER BY id');
        self::assertSame([
            ['id' => 1, 'status' => 1, 'customer_id' => 1, 'content' => 'Is it oak?', 'helpful_count' => 0, 'notify_author' => 1],
            ['id' => 2, 'status' => 0, 'customer_id' => 2, 'content' => 'Still waiting', 'helpful_count' => 0, 'notify_author' => 1],
            ['id' => 3, 'status' => 2, 'customer_id' => 1, 'content' => 'Refused later', 'helpful_count' => 0, 'notify_author' => 1],
        ], $questions);

        $answers = $this->rows('SELECT question_id, admin_id, customer_id, is_official, content, status, published_at FROM product_question_answer ORDER BY question_id');
        self::assertSame([
            ['question_id' => 1, 'admin_id' => 2, 'customer_id' => null, 'is_official' => 1, 'content' => 'Yes, solid oak.', 'status' => 1, 'published_at' => '2026-09-01 10:00:00'],
            ['question_id' => 3, 'admin_id' => null, 'customer_id' => null, 'is_official' => 1, 'content' => 'We cannot say.', 'status' => 1, 'published_at' => '2026-09-02 11:00:00'],
        ], $answers);

        foreach (['answer', 'answered_at', 'answered_by'] as $column) {
            self::assertSame(0, $this->scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_question' AND COLUMN_NAME = '$column'"), $column.' is still there');
        }
        self::assertSame(1, $this->scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_question_answer_vote'"));
        self::assertSame(1, $this->scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_question_closed_product'"));
    }

    /**
     * A product closed to questions goes away with its product.
     */
    public function testAClosedProductRowCascadesFromItsProduct(): void
    {
        (new ProductQuestionSchemaUpgrader($this->con))->upgrade();

        $this->con->exec('INSERT INTO product_question_closed_product (product_id) VALUES (2)');
        $this->con->exec('DELETE FROM product WHERE id = 2');

        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM product_question_closed_product'));
    }

    /**
     * Run again, as a refresh after an interrupted upgrade would: nothing is copied twice.
     */
    public function testASecondRunChangesNothing(): void
    {
        $this->insert120Question(1, 1, 'Is it oak?', 1, 'Yes, solid oak.', '2026-09-01 10:00:00', 2);

        $upgrader = new ProductQuestionSchemaUpgrader($this->con);
        $upgrader->upgrade();
        $upgrader->upgrade();

        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM product_question_answer'));
    }

    /**
     * Deleting a question still takes its answers and their votes with it after the upgrade.
     */
    public function testTheUpgradedTablesCascadeFromTheQuestion(): void
    {
        $this->insert120Question(1, 1, 'Is it oak?', 1, 'Yes, solid oak.', '2026-09-01 10:00:00', 2);
        (new ProductQuestionSchemaUpgrader($this->con))->upgrade();

        $this->con->exec('INSERT INTO product_question_answer_vote (answer_id, customer_id) SELECT id, 2 FROM product_question_answer');
        $this->con->exec('DELETE FROM product_question WHERE id = 1');

        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM product_question_answer'));
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM product_question_answer_vote'));
    }

    public function testADatabaseWithoutTheModuleTableIsLeftAlone(): void
    {
        $this->con->exec('DROP TABLE product_question');

        self::assertFalse((new ProductQuestionSchemaUpgrader($this->con))->upgrade());
        self::assertSame(0, $this->scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'product_question%'"));
    }

    private function insert120Question(int $id, int $customerId, string $content, int $status, ?string $answer, ?string $answeredAt, ?int $answeredBy): void
    {
        $this->con->prepare('INSERT INTO product_question (id, product_id, customer_id, locale, content, status, answer, answered_at, answered_by, created_at, updated_at) VALUES (?, 1, ?, \'en_US\', ?, ?, ?, ?, ?, \'2026-08-30 09:00:00\', \'2026-08-30 09:00:00\')')
            ->execute([$id, $customerId, $content, $status, $answer, $answeredAt, $answeredBy]);
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function rows(string $sql): array
    {
        $rows = $this->con->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $row): array => array_map(
                static fn (mixed $value): int|string|null => null === $value ? null : (is_numeric($value) && !str_contains((string) $value, '-') ? (int) $value : (string) $value),
                $row,
            ),
            $rows,
        );
    }

    private function scalar(string $sql): int
    {
        return (int) $this->con->query($sql)->fetchColumn();
    }
}
