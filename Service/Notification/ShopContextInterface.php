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

/**
 * The two things about the shop a notification needs and that only a booted Thelia knows:
 * its language, and the absolute URL of a back-office screen.
 */
interface ShopContextInterface
{
    public function defaultLocale(): string;

    public function adminUrlOfQuestion(int $questionId): string;
}
