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

namespace ProductQuestion\Service\BackOffice;

/**
 * What a moderator can do to several questions at once from the list.
 */
enum BulkDecision: string
{
    case Publish = 'publish';
    case Refuse = 'refuse';
    case Delete = 'delete';

    /** What the administration log says of each question the decision was applied to. */
    public function pastTense(): string
    {
        return match ($this) {
            self::Publish => 'published',
            self::Refuse => 'refused',
            self::Delete => 'deleted',
        };
    }
}
