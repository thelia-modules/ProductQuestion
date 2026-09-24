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

namespace ProductQuestion\EventListener;

use ProductQuestion\Event\ProductQuestionAnsweredEvent;
use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Repository\ProductTitleSourceInterface;
use ProductQuestion\Service\Notification\CustomerMailerInterface;
use ProductQuestion\Service\Notification\ProductQuestionAnswerNotification;
use ProductQuestion\Service\Notification\ShopContextInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tells the customer their question has been answered.
 *
 * Once, when the answer is first published: an answer edited later changes the page, not the
 * news. A question whose account is gone has nobody to write to. Like the shop's own
 * notification, a mail that cannot leave is logged and never fails the moderator's action.
 */
final readonly class ProductQuestionCustomerNotifier implements EventSubscriberInterface
{
    public function __construct(
        private CustomerMailerInterface $mailer,
        private ProductQuestionAnswerNotification $notification,
        private ProductTitleSourceInterface $productTitles,
        private ShopContextInterface $shop,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductQuestionAnsweredEvent::class => 'onQuestionAnswered',
        ];
    }

    public function onQuestionAnswered(ProductQuestionAnsweredEvent $event): void
    {
        if (!$event->isFirstAnswer()) {
            return;
        }

        $question = $event->getQuestion();
        $questionId = (int) $question->getId();
        $customerId = $question->getCustomerId();

        if ($questionId <= 0 || null === $customerId || $customerId <= 0) {
            return;
        }

        $locale = (string) $question->getLocale();
        $productId = (int) $question->getProductId();

        try {
            $this->mailer->sendToCustomer(
                ProductQuestionModule::MESSAGE_CUSTOMER_ANSWERED,
                $customerId,
                $locale,
                $this->notification->parameters(
                    $questionId,
                    (string) $question->getContent(),
                    (string) $question->getAnswer(),
                    $this->productTitles->titleFor($productId, $locale),
                    $this->shop->productUrl($productId, $locale),
                    $locale,
                ),
            );
        } catch (\Throwable $exception) {
            $this->logger->error(\sprintf('ProductQuestion: the customer could not be told about the answer to question %d: %s', $questionId, $exception->getMessage()));
        }
    }
}
