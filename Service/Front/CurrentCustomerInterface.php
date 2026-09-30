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

namespace ProductQuestion\Service\Front;

/**
 * Who is browsing, reduced to what this module needs: an account id, or nothing.
 *
 * The front-office component and the API processor both ask; the answer comes from two
 * different places (the shop's session, a JWT), so each door has its own implementation and
 * the services behind them do not care which.
 */
interface CurrentCustomerInterface
{
    public function id(): ?int;
}
