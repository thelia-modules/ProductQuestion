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

use ProductQuestion\Model\Base\ProductQuestionAnswer as BaseProductQuestionAnswer;

/**
 * One answer to a question: the shop's own, flagged official, or one a customer wrote.
 *
 * An answer goes through the same three states as a question. The shop's answer is published
 * as it is written; a customer's waits for a moderator.
 */
class ProductQuestionAnswer extends BaseProductQuestionAnswer
{
    use ProductQuestionStatusTrait;

    public function isOfficialAnswer(): bool
    {
        return true === $this->getIsOfficial();
    }
}
