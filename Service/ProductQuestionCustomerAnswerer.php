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

namespace ProductQuestion\Service;

use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Repository\ProductVisibilityInterface;
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;

/**
 * Records the answer a signed-in customer writes to someone's published question.
 *
 * Only when the shop lets customers answer, and only to a question a visitor can read. The
 * answer starts pending, like a question: nothing a customer writes reaches the product page
 * before a moderator has accepted it.
 */
final readonly class ProductQuestionCustomerAnswerer
{
    public const MINIMUM_LENGTH = 2;

    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionAnswerStorageInterface $answers,
        private ProductQuestionTextSanitizer $sanitizer,
        private ProductVisibilityInterface $products,
        private ProductQuestionSettingsInterface $settings,
    ) {
    }

    public function answer(int $questionId, int $customerId, ?string $content): ProductQuestionAnswer
    {
        if (!$this->settings->allowsCustomerAnswers()) {
            throw InvalidProductQuestionException::customerAnswersClosed();
        }

        if ($customerId <= 0) {
            throw InvalidProductQuestionException::unknownCustomer();
        }

        $clean = $this->sanitizer->sanitize($content);

        if ('' === $clean) {
            throw InvalidProductQuestionException::emptyAnswer();
        }

        $length = mb_strlen($clean);

        if ($length < self::MINIMUM_LENGTH) {
            throw InvalidProductQuestionException::answerTooShort(self::MINIMUM_LENGTH);
        }

        if ($length > ProductQuestionAnswerer::MAXIMUM_LENGTH) {
            throw InvalidProductQuestionException::answerTooLong(ProductQuestionAnswerer::MAXIMUM_LENGTH);
        }

        // Last, being the rules that cost a query: a question waiting for the shop, refused, or
        // about a product taken offline is not one a visitor can read, so not one to answer.
        $question = $questionId > 0 ? $this->storage->findById($questionId) : null;

        if (null === $question || !$question->isPublished() || !$this->products->isVisible((int) $question->getProductId())) {
            throw InvalidProductQuestionException::unknownQuestion();
        }

        $answer = new ProductQuestionAnswer();
        $answer
            ->setQuestionId($questionId)
            ->setCustomerId($customerId)
            ->setIsOfficial(false)
            ->setContent($clean)
            ->setStatusEnum(ProductQuestionStatus::Pending);

        $this->answers->save($answer);

        return $answer;
    }
}
