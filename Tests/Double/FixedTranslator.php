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

namespace ProductQuestion\Tests\Double;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translator that answers the key prefixed with the domain it was asked for.
 *
 * It exists to make the domain visible in an assertion: an injected Thelia translator
 * defaults to the core domain, where none of this module's keys are, and returns the raw key
 * when it finds nothing — so leaving the domain out looks exactly like translating.
 */
final class FixedTranslator implements TranslatorInterface
{
    /** @var list<array{id: string, domain: string|null}> */
    public array $calls = [];

    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $this->calls[] = ['id' => $id, 'domain' => $domain];

        return ($domain ?? 'no-domain').':'.$id;
    }

    public function getLocale(): string
    {
        return 'en_US';
    }
}
