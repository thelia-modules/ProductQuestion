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
 * What an unsubscribe link turned out to be.
 */
enum UnsubscribeLinkCheck
{
    case Valid;

    /** Signed by the shop, but past its date. */
    case Expired;

    /** Not signed by the shop: altered, truncated, or made up. */
    case Invalid;
}
