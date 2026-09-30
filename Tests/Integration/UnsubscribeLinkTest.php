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

namespace ProductQuestion\Tests\Integration;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Notification\ProductQuestionUnsubscribeLink;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The unsubscribe link of an answer mail, followed over HTTP as a mail reader would.
 */
final class UnsubscribeLinkTest extends WebIntegrationTestCase
{
    private ProductQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $this->question = (new ProductQuestion())
            ->setProductId((int) $product->getId())
            ->setCustomerId((int) $customer->getId())
            ->setLocale('en_US')
            ->setContent('Does it fold?')
            ->setStatusEnum(ProductQuestionStatus::Published);
        $this->question->save();

        // createFixtureFactory() pushed a bare request for the model listeners. Left in the stack
        // it would be the main request of the page rendered next, one with no session, which the
        // shop's layout reads.
        static::getContainer()->get(RequestStack::class)->pop();
    }

    /**
     * Opening the link asks and changes nothing; the button on the page stops the mails.
     */
    public function testFollowingTheLinkThenConfirmingStopsTheMails(): void
    {
        $parameters = $this->link()->parameters((int) $this->question->getId());
        $path = '/product-question/'.$this->question->getId().'/unsubscribe';

        $this->client->request('GET', $path.'?'.http_build_query($parameters));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('data-state="confirm"', (string) $this->client->getResponse()->getContent());
        self::assertTrue($this->storedNotifyAuthor());

        $this->client->request('POST', $path, $parameters);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('data-state="done"', (string) $this->client->getResponse()->getContent());
        self::assertFalse($this->storedNotifyAuthor());
    }

    public function testAForgedLinkIsRefusedAndChangesNothing(): void
    {
        $parameters = $this->link()->parameters((int) $this->question->getId());
        $parameters['signature'] = str_repeat('0', 64);

        $this->client->request('POST', '/product-question/'.$this->question->getId().'/unsubscribe', $parameters);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('data-state="invalid"', (string) $this->client->getResponse()->getContent());
        self::assertTrue($this->storedNotifyAuthor());
    }

    /**
     * The link of another question does not reach this one.
     */
    public function testTheLinkOfAnotherQuestionIsRefused(): void
    {
        $parameters = $this->link()->parameters((int) $this->question->getId() + 1);

        $this->client->request('POST', '/product-question/'.$this->question->getId().'/unsubscribe', $parameters);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertTrue($this->storedNotifyAuthor());
    }

    public function testAnExpiredLinkIsRefusedAndChangesNothing(): void
    {
        $parameters = $this->link()->parameters((int) $this->question->getId(), time() - ProductQuestionUnsubscribeLink::LIFETIME_SECONDS - 60);

        $this->client->request('POST', '/product-question/'.$this->question->getId().'/unsubscribe', $parameters);

        self::assertSame(410, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('data-state="expired"', (string) $this->client->getResponse()->getContent());
        self::assertTrue($this->storedNotifyAuthor());
    }

    private function link(): ProductQuestionUnsubscribeLink
    {
        return $this->getService(ProductQuestionUnsubscribeLink::class);
    }

    private function storedNotifyAuthor(): ?bool
    {
        return ProductQuestionQuery::create()->findPk($this->question->getId())?->getNotifyAuthor();
    }
}
