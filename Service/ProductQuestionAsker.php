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

use ProductQuestion\Event\ProductQuestionCreatedEvent;
use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Repository\ProductVisibilityInterface;
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Records the question a signed-in customer asks about a product.
 *
 * The question starts pending, which is the only state this service ever writes: nothing a
 * visitor sends reaches the product page without an administrator having answered it.
 */
final readonly class ProductQuestionAsker
{
    /** Below this, the text is not a question. */
    public const MINIMUM_LENGTH = 5;

    /** The column is a LONGTEXT; this is what a product page can carry and a moderator read. */
    public const MAXIMUM_LENGTH = 2000;

    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionTextSanitizer $sanitizer,
        private EventDispatcherInterface $dispatcher,
        private ProductVisibilityInterface $products,
    ) {
    }

    public function ask(int $productId, int $customerId, string $locale, ?string $content): ProductQuestion
    {
        if ($productId <= 0) {
            throw InvalidProductQuestionException::unknownProduct();
        }

        // The back office links to the customer record from every question, and an account is
        // what makes that link exist. It is also what keeps the form off the page for someone
        // who is only browsing.
        if ($customerId <= 0) {
            throw InvalidProductQuestionException::unknownCustomer();
        }

        $locale = trim($locale);

        if ('' === $locale) {
            throw InvalidProductQuestionException::emptyLocale();
        }

        $clean = $this->sanitizer->sanitize($content);

        if ('' === $clean) {
            throw InvalidProductQuestionException::emptyQuestion();
        }

        // Counted in characters, not bytes: an accented question is not two thirds of one.
        $length = mb_strlen($clean);

        if ($length < self::MINIMUM_LENGTH) {
            throw InvalidProductQuestionException::questionTooShort(self::MINIMUM_LENGTH);
        }

        if ($length > self::MAXIMUM_LENGTH) {
            throw InvalidProductQuestionException::questionTooLong(self::MAXIMUM_LENGTH);
        }

        // Last, being the one rule that costs a query. Without it an unknown product reaches the
        // foreign key as a 500, and a product the shop keeps offline gets questions and mails.
        if (!$this->products->isVisible($productId)) {
            throw InvalidProductQuestionException::unknownProduct();
        }

        $question = new ProductQuestion();
        $question
            ->setProductId($productId)
            ->setCustomerId($customerId)
            ->setLocale($locale)
            ->setContent($clean)
            ->setStatusEnum(ProductQuestionStatus::Pending);

        $this->storage->save($question);

        $this->dispatcher->dispatch(new ProductQuestionCreatedEvent($question));

        return $question;
    }
}
