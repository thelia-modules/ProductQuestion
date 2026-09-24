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

namespace ProductQuestion\Tests\Unit\Api;

use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\TestCase;
use ProductQuestion\Api\Resource\ProductQuestion as ProductQuestionResource;
use ProductQuestion\Api\State\ProductQuestionPostProcessor;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Api\ProductQuestionPayloadMapper;
use ProductQuestion\Service\Front\ProductQuestionAskLimiter;
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;
use ProductQuestion\Service\ProductQuestionAsker;
use ProductQuestion\Tests\Double\FixedCurrentCustomer;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use ProductQuestion\Tests\Double\RateLimiters;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Asking through the front API: the same rules as the theme's form, with the customer read
 * off the token and never off the body.
 *
 * The token itself is read by TokenCurrentCustomer, which needs a Customer row and so a built
 * Propel model tree; it is stood in for here and proven against the running shop.
 */
final class ProductQuestionPostProcessorTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage();
    }

    private function processor(?int $customerId, int $perProductLimit = 3, string $requestLocale = 'fr_FR'): ProductQuestionPostProcessor
    {
        $requestStack = new RequestStack();
        $request = Request::create('/api/front/account/product_questions', 'POST');
        $request->setLocale($requestLocale);
        $requestStack->push($request);

        return new ProductQuestionPostProcessor(
            new ProductQuestionAsker($this->storage, new ProductQuestionTextSanitizer(), new EventDispatcher()),
            new ProductQuestionAskLimiter(
                RateLimiters::slidingWindow('per_customer', 100),
                RateLimiters::slidingWindow('per_product', $perProductLimit),
            ),
            new ProductQuestionPayloadMapper(),
            new FixedCurrentCustomer($customerId),
            $requestStack,
        );
    }

    private function input(string $content = 'Est-ce compatible avec le modele 2024 ?', ?string $locale = null): ProductQuestionResource
    {
        $resource = new ProductQuestionResource();
        $resource->productId = 12;
        $resource->content = $content;
        $resource->locale = $locale;

        return $resource;
    }

    public function testAQuestionIsStoredPendingForTheCustomerTheTokenNames(): void
    {
        $result = $this->processor(42)->process($this->input(), new Post());

        self::assertCount(1, $this->storage->saved);
        $saved = $this->storage->saved[0];
        self::assertSame(42, $saved->getCustomerId());
        self::assertSame(12, $saved->getProductId());
        self::assertSame('fr_FR', $saved->getLocale());
        self::assertSame(ProductQuestionStatus::Pending, $saved->getStatusEnum());

        self::assertFalse($result->published);
        self::assertNull($result->answer);
        self::assertSame('Est-ce compatible avec le modele 2024 ?', $result->content);
    }

    public function testTheLanguageInTheBodyWinsOverTheRequests(): void
    {
        $this->processor(42)->process($this->input(locale: 'en_US'), new Post());

        self::assertSame('en_US', $this->storage->saved[0]->getLocale());
    }

    public function testNoTokenMeansNoQuestion(): void
    {
        try {
            $this->processor(null)->process($this->input(), new Post());
            self::fail('An anonymous request has to be refused');
        } catch (AccessDeniedHttpException) {
            self::assertSame([], $this->storage->saved);
        }
    }

    public function testAFloodIsRefusedBeforeAnythingIsWritten(): void
    {
        $processor = $this->processor(42, perProductLimit: 2);

        $processor->process($this->input(), new Post());
        $processor->process($this->input(), new Post());

        try {
            $processor->process($this->input(), new Post());
            self::fail('The third question about one product has to be refused');
        } catch (TooManyRequestsHttpException) {
            self::assertCount(2, $this->storage->saved);
        }
    }

    /**
     * The validator has already checked the raw length; what the service refuses is a text
     * the sanitizer emptied — markup and nothing else.
     */
    public function testATextTheSanitizerEmptiesIsUnprocessable(): void
    {
        try {
            $this->processor(42)->process($this->input('<b></b><i></i>'), new Post());
            self::fail('An empty question has to be refused');
        } catch (UnprocessableEntityHttpException) {
            self::assertSame([], $this->storage->saved);
        }
    }
}
