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
 * Why a mail could not leave, as it may be written to a log.
 *
 * A transport error quotes the dialogue with the SMTP server and an RFC compliance error
 * quotes the address it refused: left as it is, the error log collects customers' addresses.
 * The reason stays, the addresses go.
 */
final class MailFailure
{
    public static function describe(\Throwable $exception): string
    {
        $message = (string) preg_replace('/[^\s<>"\'@]+@[^\s<>"\',;]+/u', '[address]', $exception->getMessage());

        return $exception::class.': '.$message;
    }
}
