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

namespace ProductQuestion\Model;

/**
 * The three states a question, and each of its answers, goes through.
 *
 * Stored as the TINYINT `status` of product_question and product_question_answer, so the
 * integers are part of the data and never change: a shop upgrading the module keeps the rows
 * it already has. A state a shop administrator would create does not exist here, which is why
 * this is an enum and not a status table.
 */
enum ProductQuestionStatus: int
{
    /** Written by a customer, waiting for the shop. Not on the product page. */
    case Pending = 0;

    /** Accepted by the shop. This is the only state the product page shows. */
    case Published = 1;

    /** Turned down by an administrator. Not on the product page, and kept as the trace of
     *  that decision. */
    case Refused = 2;

    /**
     * The name of the published state up to 1.2.0, when a question was published by answering
     * it and by nothing else. Kept so that code written against that version still compiles.
     */
    public const Answered = self::Published;
}
