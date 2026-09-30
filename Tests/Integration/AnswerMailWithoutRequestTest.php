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
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\ProductQuestionAnswerer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Email;
use Thelia\Test\IntegrationTestCase;

/**
 * An answer published from the command line, where no HTTP request exists, still tells its author,
 * with the product page and a signed unsubscribe link written as absolute URLs.
 */
final class AnswerMailWithoutRequestTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The base class pushes a request for the listeners that expect one: a command has none.
        $requestStack = $this->getService(RequestStack::class);
        while (null !== $requestStack->pop()) {
        }
    }

    public function testAnAnswerPublishedWithoutARequestTellsTheAuthorWithAbsoluteLinks(): void
    {
        self::assertNull($this->getService(RequestStack::class)->getMainRequest());

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['email' => 'author-without-request@example.com']);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 1]);
        $question = (new ProductQuestion())
            ->setProductId((int) $product->getId())
            ->setCustomerId((int) $customer->getId())
            ->setLocale('en_US')
            ->setContent('Does it fold?')
            ->setStatusEnum(ProductQuestionStatus::Pending);
        $question->save();

        $this->getService(ProductQuestionAnswerer::class)->answer($question, 'Yes, in two.', (int) $factory->admin()->getId());

        $mails = array_values(array_filter(
            self::getMailerMessages(),
            static fn (object $message): bool => $message instanceof Email
                && \in_array('author-without-request@example.com', array_map(static fn ($address): string => $address->getAddress(), $message->getTo()), true),
        ));

        self::assertCount(1, $mails, 'The author is told once, without any HTTP request.');
        $body = (string) $mails[0]->getTextBody();
        self::assertStringContainsString('Yes, in two.', $body);
        self::assertMatchesRegularExpression('#https?://[^\s/]+/product-question/'.$question->getId().'/unsubscribe\?expires=\d+&signature=[0-9a-f]{64}#', $body, 'The unsubscribe link is absolute and signed.');
    }
}
