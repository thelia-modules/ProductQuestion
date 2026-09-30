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
use ApiPlatform\Metadata\Post;
use ProductQuestion\Api\State\ProductQuestionAnswerHelpfulProcessor;
use ProductQuestion\Api\State\ProductQuestionAnswerPostProcessor;
use ProductQuestion\Api\State\ProductQuestionAnswerProvider;
use ProductQuestion\Service\ProductQuestionAnswerer;
use ProductQuestion\Service\ProductQuestionCustomerAnswerer;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * One answer to a product question, for a front office that talks to the API.
 *
 * Reading one is public and limited to a published answer of a published question. Writing one
 * is a signed-in customer answering someone's question, when the shop takes such answers: it
 * goes through ProductQuestionCustomerAnswerer and waits for a moderator like the theme's form.
 * Voting an answer helpful is a signed-in customer too, once per answer.
 * Neither the author nor the moderator is ever in the payload.
 */
#[ApiResource(
    shortName: 'ProductQuestionAnswer',
    operations: [
        new Get(
            uriTemplate: '/front/product_question_answers/{id}',
            provider: ProductQuestionAnswerProvider::class,
            security: "is_granted('PUBLIC_ACCESS')",
        ),
        new Post(
            uriTemplate: '/front/account/product_question_answers',
            security: "is_granted('ROLE_CUSTOMER')",
            processor: ProductQuestionAnswerPostProcessor::class,
            validationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
        ),
        // No body: the answer is the one in the path, the voter the one in the token. Answers
        // 200 with the answer and its new count, the same whether the vote was just counted or
        // already was.
        new Post(
            uriTemplate: '/front/account/product_question_answers/{id}/helpful',
            status: 200,
            security: "is_granted('ROLE_CUSTOMER')",
            read: false,
            deserialize: false,
            validate: false,
            processor: ProductQuestionAnswerHelpfulProcessor::class,
            name: 'product_question_answer_helpful',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
)]
class ProductQuestionAnswer
{
    public const GROUP_FRONT_READ = 'front:product_question_answer:read';
    public const GROUP_FRONT_WRITE = 'front:product_question_answer:write';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE])]
    #[GreaterThan(0, groups: [self::GROUP_FRONT_WRITE])]
    public ?int $questionId = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(normalizer: 'trim', groups: [self::GROUP_FRONT_WRITE])]
    #[Length(min: ProductQuestionCustomerAnswerer::MINIMUM_LENGTH, max: ProductQuestionAnswerer::MAXIMUM_LENGTH, groups: [self::GROUP_FRONT_WRITE])]
    public ?string $content = null;

    /** The shop's own answer. */
    #[Groups([self::GROUP_FRONT_READ])]
    public bool $official = false;

    #[Groups([self::GROUP_FRONT_READ])]
    public int $helpfulCount = 0;

    /** False for the answer just posted: it waits for the shop. */
    #[Groups([self::GROUP_FRONT_READ])]
    public bool $published = false;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $publishedAt = null;
}
