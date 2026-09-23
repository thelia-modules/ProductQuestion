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
 * The three states a question goes through.
 *
 * Stored as the TINYINT `product_question.status`, so the integers are part of the data and
 * never change: a shop upgrading the module keeps the rows it already has. A state a shop
 * administrator would create does not exist here, which is why this is an enum and not a
 * `product_question_status` table.
 */
enum ProductQuestionStatus: int
{
    /** Asked by a customer, waiting for the shop. Not on the product page. */
    case Pending = 0;

    /** Answered by an administrator. This is the only state the product page shows. */
    case Answered = 1;

    /** Turned down by an administrator. Not on the product page, and kept as the trace of
     *  that decision. */
    case Refused = 2;
}
