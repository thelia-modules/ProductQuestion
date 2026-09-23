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

namespace ProductQuestion\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

/**
 * The two descriptions of the product_question table have to say the same thing.
 *
 * Config/schema.xml is what Propel builds the models from; Config/TheliaMain.sql is what an
 * activation actually runs. A shop installed today gets the SQL file, so a rule declared in
 * one and missing from the other is a rule half the shops do not have.
 */
final class SchemaTest extends TestCase
{
    private string $schema;

    private string $sql;

    protected function setUp(): void
    {
        $config = \dirname(__DIR__, 3).'/Config';

        $this->schema = (string) file_get_contents($config.'/schema.xml');
        $this->sql = (string) file_get_contents($config.'/TheliaMain.sql');
    }

    /**
     * A question has no meaning without its product, and nothing else in the module cleans
     * up after a deleted one: the database does it.
     */
    public function testDeletingAProductTakesItsQuestionsAway(): void
    {
        self::assertMatchesRegularExpression(
            '#foreignTable="product"[^>]*onDelete="CASCADE"#',
            $this->schema
        );
        self::assertMatchesRegularExpression(
            '#CONSTRAINT `fk_product_question_product_id`\s*FOREIGN KEY \(`product_id`\)\s*REFERENCES `product` \(`id`\)\s*ON UPDATE RESTRICT\s*ON DELETE CASCADE#',
            $this->sql
        );
    }

    /**
     * A published answer belongs to the shop, not to the account that asked or to the one
     * that wrote it. Closing either account leaves the product page as it was.
     */
    public function testClosingAnAccountLeavesThePublishedAnswerInPlace(): void
    {
        self::assertMatchesRegularExpression(
            '#foreignTable="customer"[^>]*onDelete="SET NULL"#',
            $this->schema
        );
        self::assertMatchesRegularExpression(
            '#foreignTable="admin"[^>]*onDelete="SET NULL"#',
            $this->schema
        );

        self::assertStringContainsString('REFERENCES `customer` (`id`)', $this->sql);
        self::assertStringContainsString('REFERENCES `admin` (`id`)', $this->sql);
        self::assertSame(2, substr_count($this->sql, 'ON DELETE SET NULL'));
    }

    /**
     * The front office makes one query: the answered questions of one product in the
     * visitor's language.
     */
    public function testTheProductPageQueryIsIndexed(): void
    {
        self::assertMatchesRegularExpression(
            '#<index name="idx_product_question_product_status_locale">\s*<index-column name="product_id"\s*/>\s*<index-column name="status"\s*/>\s*<index-column name="locale"\s*/>\s*</index>#',
            $this->schema
        );
        self::assertStringContainsString(
            'INDEX `idx_product_question_product_status_locale` (`product_id`, `status`, `locale`)',
            $this->sql
        );
    }

    /**
     * The moderation list and its counters filter on the status alone.
     */
    public function testTheStatusIsIndexed(): void
    {
        self::assertMatchesRegularExpression(
            '#<index name="idx_product_question_status">\s*<index-column name="status"\s*/>\s*</index>#',
            $this->schema
        );
        self::assertStringContainsString('INDEX `idx_product_question_status` (`status`)', $this->sql);
    }

    /**
     * A row inserted without a status is a question waiting for the shop, never one that is
     * already on the product page.
     */
    public function testAQuestionStartsPending(): void
    {
        self::assertStringContainsString('name="status" default="0"', $this->schema);
        self::assertStringContainsString('`status` TINYINT DEFAULT 0 NOT NULL', $this->sql);
    }
}
