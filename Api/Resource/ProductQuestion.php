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

namespace ProductQuestion\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ProductQuestion\Api\State\ProductQuestionPostProcessor;
use ProductQuestion\Api\State\ProductQuestionProvider;
use ProductQuestion\Service\ProductQuestionAsker;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The questions of a product, for a front office that talks to the API rather than to Twig.
 *
 * What comes out is what a shop puts on a product page, built from answered questions only:
 * the question, the shop's answer and its date. Neither who asked nor who answered is ever
 * readable — the answer is the shop's, unsigned, and the list is public.
 *
 * Posting sits under /front/account, which the firewall locks to a signed-in customer, and
 * says so again in its own security rule: an operation without one is open by accident.
 *
 * Neither operation is backed by the Propel bridge: the list has a status filter a client
 * must not be able to lift, and posting goes through ProductQuestionAsker so that the rules
 * of the theme's form apply here too.
 */
#[ApiResource(
    shortName: 'ProductQuestion',
    operations: [
        new GetCollection(
            uriTemplate: '/front/product_questions',
            provider: ProductQuestionProvider::class,
            // Nothing to authenticate: an answered question is on the product page anyway.
            security: "is_granted('PUBLIC_ACCESS')",
        ),
        new Get(
            uriTemplate: '/front/product_questions/{id}',
            provider: ProductQuestionProvider::class,
            security: "is_granted('PUBLIC_ACCESS')",
        ),
        new Post(
            uriTemplate: '/front/account/product_questions',
            security: "is_granted('ROLE_CUSTOMER')",
            processor: ProductQuestionPostProcessor::class,
            // The constraints below carry the write group; without naming it here the
            // validator runs the Default group, finds nothing, and the rate limiter pays for
            // a text the service then refuses.
            validationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
)]
class ProductQuestion
{
    public const GROUP_FRONT_READ = 'front:product_question:read';
    public const GROUP_FRONT_WRITE = 'front:product_question:write';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE])]
    #[GreaterThan(0, groups: [self::GROUP_FRONT_WRITE])]
    public ?int $productId = null;

    /**
     * The language the question is asked in. Optional on a post: the request's own language
     * is what a client that says nothing gets.
     */
    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[Length(max: 10, groups: [self::GROUP_FRONT_WRITE])]
    public ?string $locale = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(normalizer: 'trim', groups: [self::GROUP_FRONT_WRITE])]
    #[Length(min: ProductQuestionAsker::MINIMUM_LENGTH, max: ProductQuestionAsker::MAXIMUM_LENGTH, groups: [self::GROUP_FRONT_WRITE])]
    public ?string $content = null;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $answer = null;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $answeredAt = null;

    /**
     * Whether the question is on the product page. False for the one that has just been
     * posted, which is what tells the poster their question is waiting for the shop.
     */
    #[Groups([self::GROUP_FRONT_READ])]
    public bool $published = false;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $createdAt = null;
}
