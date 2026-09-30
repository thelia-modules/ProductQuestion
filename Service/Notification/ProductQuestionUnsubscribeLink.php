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

namespace ProductQuestion\Service\Notification;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The link every answer mail carries to stop the mails about one question.
 *
 * Signed and dated, with nothing stored: an HMAC of the question and the expiry date under the
 * application secret. The question id is in the link, but a link for another question, or with
 * a later date, cannot be made without the secret. Checked in constant time.
 *
 * The signature covers the question and the date only, not the host or the scheme, so a link
 * sent from a back office on one domain works on the shop's own.
 */
final readonly class ProductQuestionUnsubscribeLink
{
    /** How long a link stays good: long enough for a mail read late, short enough to age out. */
    public const LIFETIME_SECONDS = 90 * 86400;

    public const PATH = '/product-question/%d/unsubscribe';

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private ShopContextInterface $shop,
    ) {
    }

    public function urlFor(int $questionId, ?int $now = null): string
    {
        return $this->shop->publicUrl(\sprintf(self::PATH, $questionId), $this->parameters($questionId, $now));
    }

    /**
     * @return array{expires: int, signature: string}
     */
    public function parameters(int $questionId, ?int $now = null): array
    {
        $expires = ($now ?? time()) + self::LIFETIME_SECONDS;

        return ['expires' => $expires, 'signature' => $this->sign($questionId, $expires)];
    }

    public function check(int $questionId, mixed $expires, mixed $signature, ?int $now = null): UnsubscribeLinkCheck
    {
        if ($questionId <= 0 || !\is_string($signature) || !\is_scalar($expires) || 1 !== preg_match('/^\d{1,12}$/', (string) $expires)) {
            return UnsubscribeLinkCheck::Invalid;
        }

        if (!hash_equals($this->sign($questionId, (int) $expires), $signature)) {
            return UnsubscribeLinkCheck::Invalid;
        }

        // Only a link the shop signed gets to say it expired: a forged one learns nothing.
        return (int) $expires < ($now ?? time()) ? UnsubscribeLinkCheck::Expired : UnsubscribeLinkCheck::Valid;
    }

    private function sign(int $questionId, int $expires): string
    {
        return hash_hmac('sha256', 'product_question.unsubscribe|'.$questionId.'|'.$expires, $this->secret);
    }
}
