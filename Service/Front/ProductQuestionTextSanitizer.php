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
 * Cleans the two texts this module stores: the question a visitor typed and the answer an
 * administrator wrote.
 *
 * Both end up on a public product page. Twig escapes them on the way out, so this is the
 * second line rather than the first: what it removes is markup that would come back as
 * markup the day someone renders the text through |raw, and invisible characters that make
 * the stored text read differently from what is shown.
 *
 * Tags are matched on a real tag name rather than with strip_tags(), which reads everything
 * between a "<" and the next ">" as a tag and would turn "is it < 6 kg and > 3 kg?" into
 * "is it  3 kg?" — exactly the kind of question this module exists to carry.
 *
 * Newlines are kept: an answer of several paragraphs is normal, and the product page shows
 * them as the administrator typed them.
 */
final readonly class ProductQuestionTextSanitizer
{
    private const TAG = '#</?[a-zA-Z][a-zA-Z0-9:-]*(\s[^<>]*)?/?>#';

    /**
     * C0 controls except tab and newline, DEL, then the zero-width and direction-override
     * characters.
     */
    private const INVISIBLE = '#[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\x{200B}|\x{200C}|\x{200D}|\x{200E}|\x{200F}|\x{202A}|\x{202B}|\x{202C}|\x{202D}|\x{202E}|\x{2066}|\x{2067}|\x{2068}|\x{2069}|\x{FEFF}#u';

    public function sanitize(?string $text): string
    {
        $clean = (string) $text;

        // Encoded markup is markup. One decode and one strip is not enough: a doubly encoded
        // tag comes back as a tag on the next decode, so this runs until the text settles.
        for ($pass = 0; $pass < 4; ++$pass) {
            $before = $clean;

            $clean = (string) preg_replace(
                self::TAG,
                '',
                html_entity_decode($clean, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')
            );

            if ($before === $clean) {
                break;
            }
        }

        $clean = (string) preg_replace(self::INVISIBLE, '', $clean);

        // Windows and old Mac line endings, then runs of blank lines: one empty line between
        // paragraphs is a paragraph break, five is a layout accident.
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);
        $clean = (string) preg_replace('#[ \t]+#', ' ', $clean);
        $clean = (string) preg_replace('#[ \t]*\n[ \t]*#', "\n", $clean);
        $clean = (string) preg_replace('#\n{3,}#', "\n\n", $clean);

        return trim($clean);
    }
}
