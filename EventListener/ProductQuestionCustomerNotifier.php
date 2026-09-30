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
use ProductQuestion\Service\Notification\MailFailure;
use ProductQuestion\Service\Notification\ProductQuestionAnswerNotification;
use ProductQuestion\Service\Notification\ProductQuestionUnsubscribeLink;
use ProductQuestion\Service\Notification\ShopContextInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tells the customer their question has been answered, the shop's answer or another customer's.
 *
 * Once per answer, when it is first published: an answer edited later changes the page, not the
 * news. A question whose account is gone has nobody to write to; an author who followed the
 * unsubscribe link of an earlier mail is not written to again; an author answering their own
 * question is not told about it. Every mail carries that link, signed and dated. Like the shop's own
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
        private ProductQuestionUnsubscribeLink $unsubscribeLink,
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
        $answer = $event->getAnswer();
        $questionId = (int) $question->getId();
        $customerId = $question->getCustomerId();

        if (null === $answer || $questionId <= 0 || null === $customerId || $customerId <= 0) {
            return;
        }

        if (false === $question->getNotifyAuthor() || $answer->getCustomerId() === $customerId) {
            return;
        }

        $locale = (string) $question->getLocale();
        $productId = (int) $question->getProductId();

        try {
            $this->mailer->sendToCustomer(
                $answer->isOfficialAnswer() ? ProductQuestionModule::MESSAGE_CUSTOMER_ANSWERED : ProductQuestionModule::MESSAGE_CUSTOMER_ANSWERED_BY_CUSTOMER,
                $customerId,
                $locale,
                $this->notification->parameters(
                    $questionId,
                    (string) $question->getContent(),
                    (string) $answer->getContent(),
                    $this->productTitles->titleFor($productId, $locale),
                    $this->shop->productUrl($productId, $locale),
                    $locale,
                    $answer->isOfficialAnswer(),
                    $this->unsubscribeLink->urlFor($questionId),
                ),
            );
        } catch (\Throwable $exception) {
            $this->logger->error(\sprintf('ProductQuestion: the customer could not be told about the answer to question %d: %s', $questionId, MailFailure::describe($exception)));
        }
    }
}
