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

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Repository\ProductTitleSourceInterface;
use ProductQuestion\Service\Notification\ShopContextInterface;

/**
 * Shapes the screen of one question for Twig, as ProductQuestionListPresenter does the list.
 *
 * The form and the session token stay with the controller: they belong to the request.
 */
final readonly class ProductQuestionEditPresenter
{
    public function __construct(
        private ProductQuestionStatusCatalog $statusCatalog,
        private ProductTitleSourceInterface $productTitles,
        private ShopContextInterface $shop,
    ) {
    }

    /**
     * @return array{
     *     question: ProductQuestion,
     *     questionStatus: array{value: int, label: string, css: string},
     *     customerUrl: ?string,
     *     productUrl: string,
     *     productTitle: ?string,
     * }
     */
    public function present(ProductQuestion $question, string $uiLocale): array
    {
        $customerId = $question->getCustomerId();
        $productId = (int) $question->getProductId();

        return [
            'question' => $question,
            // Not `status`: TwigParser promotes every variable handed to a back-office template to
            // a Twig global, and the web debug toolbar reads a global of that name to colour its
            // blocks — an array there is a 500 on the whole page, in dev only.
            'questionStatus' => $this->statusCatalog->get($question->getStatus()),
            // The account may be gone: the question stays, the link does not.
            'customerUrl' => null === $customerId ? null : $this->shop->customerAdminUrl($customerId),
            'productUrl' => $this->shop->productAdminUrl($productId),
            'productTitle' => $this->productTitles->titleFor($productId, $uiLocale),
        ];
    }
}
