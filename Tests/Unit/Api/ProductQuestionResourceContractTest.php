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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\TestCase;
use ProductQuestion\Api\Resource\ProductQuestion as ProductQuestionResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * What the front API of the module promises, read off the resource itself.
 *
 * Two of these are security rules rather than shape: the API refuses nothing by default, so
 * an operation without an explicit `security` is open by accident rather than on purpose,
 * and posting has to sit under /front/account, which the firewall locks to a customer.
 */
final class ProductQuestionResourceContractTest extends TestCase
{
    /**
     * @return list<HttpOperation>
     */
    private function operations(): array
    {
        $attributes = (new \ReflectionClass(ProductQuestionResource::class))->getAttributes(ApiResource::class);

        self::assertCount(1, $attributes, 'The resource carries no #[ApiResource]');

        $operations = $attributes[0]->newInstance()->getOperations();

        self::assertNotNull($operations);

        $http = [];

        foreach ($operations as $operation) {
            self::assertInstanceOf(HttpOperation::class, $operation);
            $http[] = $operation;
        }

        return $http;
    }

    public function testEveryOperationIsUnderTheFrontPrefixAndCarriesItsOwnSecurity(): void
    {
        $operations = $this->operations();

        self::assertNotEmpty($operations);

        foreach ($operations as $operation) {
            $uriTemplate = (string) $operation->getUriTemplate();

            self::assertStringStartsWith('/front/', $uriTemplate);
            self::assertNotNull($operation->getSecurity(), $uriTemplate.' has no security rule');
        }
    }

    public function testPostingIsAnAccountOperationLockedToACustomer(): void
    {
        $posts = array_values(array_filter($this->operations(), static fn ($operation): bool => $operation instanceof Post));

        self::assertCount(1, $posts);
        self::assertSame('/front/account/product_questions', $posts[0]->getUriTemplate());
        self::assertSame("is_granted('ROLE_CUSTOMER')", $posts[0]->getSecurity());
    }

    /**
     * Every write constraint carries the write group. An operation that does not name it
     * validates the Default group, which holds nothing: the body would reach the processor
     * unchecked, and the rate limiter would pay for what the service then refuses.
     */
    public function testPostingValidatesTheWriteGroup(): void
    {
        $posts = array_values(array_filter($this->operations(), static fn ($operation): bool => $operation instanceof Post));

        self::assertSame(['groups' => [ProductQuestionResource::GROUP_FRONT_WRITE]], $posts[0]->getValidationContext());
    }

    /**
     * Neither who asked nor who answered has a place in the payload, in any group.
     */
    public function testNoPropertyNamesTheCustomerOrTheAdministrator(): void
    {
        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new \ReflectionClass(ProductQuestionResource::class))->getProperties(),
        );

        self::assertNotContains('customerId', $properties);
        self::assertNotContains('customer', $properties);
        self::assertNotContains('answeredBy', $properties);
        self::assertNotContains('admin', $properties);
    }

    /**
     * The answer belongs to the shop: a client cannot write one, nor publish its own question.
     */
    public function testTheAnswerAndThePublicationAreReadOnly(): void
    {
        foreach (['answer', 'answeredAt', 'published', 'id', 'createdAt'] as $name) {
            $groups = $this->groupsOf($name);

            self::assertContains(ProductQuestionResource::GROUP_FRONT_READ, $groups, $name.' is not readable');
            self::assertNotContains(ProductQuestionResource::GROUP_FRONT_WRITE, $groups, $name.' is writable');
        }
    }

    /**
     * @return list<string>
     */
    private function groupsOf(string $property): array
    {
        $attributes = (new \ReflectionProperty(ProductQuestionResource::class, $property))->getAttributes(Groups::class);

        self::assertCount(1, $attributes, $property.' carries no #[Groups]');

        $groups = $attributes[0]->newInstance()->getGroups();

        return array_values($groups);
    }
}
