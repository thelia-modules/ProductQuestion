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

use ProductQuestion\Model\Base\ProductQuestion as BaseProductQuestion;

class ProductQuestion extends BaseProductQuestion
{
    use ProductQuestionStatusTrait;

    /**
     * @deprecated since 1.3.0, a question is published on its own and may have no answer yet:
     *             use isPublished()
     */
    public function isAnswered(): bool
    {
        return $this->isPublished();
    }
}
