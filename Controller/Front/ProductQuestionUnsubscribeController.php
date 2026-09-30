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

namespace ProductQuestion\Controller\Front;

use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Service\Notification\ProductQuestionUnsubscribeLink;
use ProductQuestion\Service\Notification\UnsubscribeLinkCheck;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Controller\Front\BaseFrontController;

/**
 * The page the unsubscribe link of an answer mail opens.
 *
 * Opening the link asks; the button on the page does it. A mail client or a security scanner
 * that follows every link of a message must not turn the mails off on its own, so the change is
 * a POST carrying the same signed parameters. No session and no account needed: the signature
 * is the proof, which is also why nothing here tells a question that does not exist from a link
 * that is wrong.
 */
#[AsController]
#[Route('/product-question/{id}/unsubscribe', name: 'productquestion_unsubscribe', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final class ProductQuestionUnsubscribeController extends BaseFrontController
{
    public function __construct(
        private readonly ProductQuestionUnsubscribeLink $link,
        private readonly ProductQuestionStorageInterface $storage,
        private readonly TranslatorInterface $moduleTranslator,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $expires = $request->isMethod('POST') ? $request->request->get('expires') : $request->query->get('expires');
        $signature = $request->isMethod('POST') ? $request->request->get('signature') : $request->query->get('signature');

        $check = $this->link->check($id, $expires, $signature);
        $question = UnsubscribeLinkCheck::Valid === $check ? $this->storage->findById($id) : null;

        if (null === $question) {
            return $this->page(
                UnsubscribeLinkCheck::Expired === $check ? 'expired' : 'invalid',
                UnsubscribeLinkCheck::Expired === $check ? Response::HTTP_GONE : Response::HTTP_FORBIDDEN,
            );
        }

        if (!$request->isMethod('POST')) {
            return $this->page('confirm', Response::HTTP_OK, ['id' => $id, 'expires' => (string) $expires, 'signature' => (string) $signature]);
        }

        if (false !== $question->getNotifyAuthor()) {
            $question->setNotifyAuthor(false);
            $this->storage->save($question);
        }

        return $this->page('done', Response::HTTP_OK);
    }

    /**
     * @param array<string, int|string> $link
     */
    private function page(string $state, int $status, array $link = []): Response
    {
        // Resolved in the active front template first, then in the module's
        // templates/frontOffice/default, as the shop's own pages are.
        $response = $this->render('product-question-unsubscribe', [
            'state' => $state,
            'link' => $link,
            'labels' => [
                'title' => $this->trans('Answers to your question'),
                'confirm' => $this->trans('Stop receiving an email each time your question gets an answer?'),
                'button' => $this->trans('Stop the emails'),
                'done' => $this->trans('Done: you will not receive any more emails about this question.'),
                'expired' => $this->trans('This link has expired. Use the link of a more recent email.'),
                'invalid' => $this->trans('This link is not valid.'),
                'home' => $this->trans('Back to the shop'),
            ],
        ], $status);

        // A page reached from a signed link, and specific to it: neither indexed nor cached.
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setPrivate();

        return $response;
    }

    private function trans(string $key): string
    {
        return $this->moduleTranslator->trans($key, [], ProductQuestionModule::MESSAGE_DOMAIN);
    }
}
