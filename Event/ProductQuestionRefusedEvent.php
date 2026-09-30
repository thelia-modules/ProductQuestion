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

namespace ProductQuestion\Event;

/**
 * An administrator has just turned a question down. Its answer, if it had one, is untouched.
 */
final class ProductQuestionRefusedEvent extends ProductQuestionEvent
{
}
