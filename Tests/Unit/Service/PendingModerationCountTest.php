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

namespace ProductQuestion\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\BackOffice\PendingModerationCount;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;

final class PendingModerationCountTest extends TestCase
{
    public function testThePendingQuestionsAndThePendingAnswersAddUp(): void
    {
        $questions = new InMemoryProductQuestionStorage([
            $this->question(ProductQuestionStatus::Pending),
            $this->question(ProductQuestionStatus::Pending),
            $this->question(ProductQuestionStatus::Published),
            $this->question(ProductQuestionStatus::Refused),
        ]);
        $answers = new InMemoryProductQuestionAnswerStorage([
            $this->answer(1, ProductQuestionStatus::Pending),
            $this->answer(2, ProductQuestionStatus::Published),
        ]);

        self::assertSame(3, (new PendingModerationCount($questions, $answers))->total());
    }

    private function question(ProductQuestionStatus $status): ProductQuestion
    {
        return (new ProductQuestion())->setProductId(12)->setLocale('en_US')->setContent('Does it fold?')->setStatusEnum($status);
    }

    private function answer(int $id, ProductQuestionStatus $status): ProductQuestionAnswer
    {
        return (new ProductQuestionAnswer())->setId($id)->setQuestionId(1)->setContent('Yes.')->setStatusEnum($status);
    }
}
