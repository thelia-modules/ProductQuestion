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

use ProductQuestion\Event\ProductQuestionCreatedEvent;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Repository\ProductTitleSourceInterface;
use ProductQuestion\Service\Notification\ProductQuestionNotification;
use ProductQuestion\Service\Notification\ShopContextInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Mailer\MailerFactory;

/**
 * Tells the shop that a customer has asked a question.
 *
 * One mail per question, to the shop's notification addresses, in the shop's language. It
 * runs inside the dispatch that ProductQuestionAsker makes after the row is saved, from the
 * product page and from the API alike: a mail that cannot leave — no recipient configured,
 * a template set with no layout, a transport down — is logged and never takes the question
 * down with it.
 */
final readonly class ProductQuestionNotifier implements EventSubscriberInterface
{
    public function __construct(
        private MailerFactory $mailer,
        private ProductQuestionNotification $notification,
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
            ProductQuestionCreatedEvent::class => 'onQuestionCreated',
        ];
    }

    public function onQuestionCreated(ProductQuestionCreatedEvent $event): void
    {
        $question = $event->getQuestion();
        $questionId = (int) $question->getId();

        if ($questionId <= 0) {
            return;
        }

        try {
            $shopLocale = $this->shop->defaultLocale();

            $this->mailer->sendEmailToShopManagers(
                ProductQuestionModule::MESSAGE_ADMIN_NOTIFICATION,
                $this->notification->parameters(
                    $questionId,
                    (string) $question->getContent(),
                    (string) $question->getLocale(),
                    self::customerName($question),
                    $this->productTitles->titleFor((int) $question->getProductId(), $shopLocale),
                    $this->shop->adminUrlOfQuestion($questionId),
                    $shopLocale,
                ),
            );
        } catch (\Throwable $exception) {
            $this->logger->error(\sprintf('ProductQuestion: the shop could not be told about question %d: %s', $questionId, $exception->getMessage()));
        }
    }

    private static function customerName(ProductQuestion $question): ?string
    {
        $customer = $question->getCustomer();

        if (null === $customer) {
            return null;
        }

        $name = trim(($customer->getFirstname() ?? '').' '.($customer->getLastname() ?? ''));

        return '' === $name ? $customer->getEmail() : $name;
    }
}
