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

namespace ProductQuestion\Controller\Back;

use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Form\ProductQuestionAnswerForm;
use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Repository\ProductTitleSourceInterface;
use ProductQuestion\Service\BackOffice\ProductQuestionListFilters;
use ProductQuestion\Service\BackOffice\ProductQuestionListPresenter;
use ProductQuestion\Service\BackOffice\ProductQuestionStatusCatalog;
use ProductQuestion\Service\ProductQuestionAnswerer;
use ProductQuestion\Service\ProductQuestionRefuser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Admin;
use Thelia\Tools\URL;

/**
 * The moderation screens.
 *
 * Thin on purpose: it reads the request, asks a service, and renders. Every Propel call is in
 * the repository and every rule is in the domain services, so the same rules apply to the API
 * of the next phase without being written twice.
 */
#[Route('/admin/module', name: 'productquestion_module')]
class ProductQuestionController extends BaseAdminController
{
    public function __construct(
        private readonly ProductQuestionStorageInterface $storage,
        private readonly ProductQuestionListPresenter $listPresenter,
        private readonly ProductQuestionStatusCatalog $statusCatalog,
        private readonly ProductQuestionAnswerer $answerer,
        private readonly ProductQuestionRefuser $refuser,
        private readonly ProductTitleSourceInterface $productTitles,
    ) {
    }

    #[Route('/ProductQuestion', name: '_list', methods: ['GET'])]
    public function listAction(Request $request): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::VIEW)) {
            return $denied;
        }

        $filters = ProductQuestionListFilters::fromRequest($request);

        return $this->render(
            'product-questions',
            $this->listPresenter->present($filters, $request->getLocale())
        );
    }

    #[Route('/ProductQuestion/{id}', name: '_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function editAction(Request $request, int $id): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::VIEW)) {
            return $denied;
        }

        $question = $this->storage->findById($id);

        if (null === $question) {
            return $this->backToList();
        }

        return $this->renderQuestion($request, $question);
    }

    /**
     * @param \ProductQuestion\Model\ProductQuestion $question
     */
    private function renderQuestion(Request $request, $question, ?BaseForm $form = null): Response
    {
        $customerId = $question->getCustomerId();
        $productId = (int) $question->getProductId();

        // On the error path the submitted form is handed back rather than rebuilt, so what a
        // moderator typed is still in the textarea next to the message telling them why.
        $form ??= $this->createForm(ProductQuestionAnswerForm::getName(), data: ['answer' => $question->getAnswer()]);

        return $this->render('product-question-edit', [
            'question' => $question,
            'status' => $this->statusCatalog->get($question->getStatus()),
            'form' => $form->getForm()->createView(),
            // Built here rather than in the template: a module must not hard-code the admin
            // routes of the core in its markup.
            'customerUrl' => null === $customerId
                ? null
                : URL::getInstance()->absoluteUrl('/admin/customer/update', ['customer_id' => $customerId]),
            'productUrl' => URL::getInstance()->absoluteUrl('/admin/products/update', ['product_id' => $productId]),
            'productTitle' => $this->productTitles->titleFor($productId, $request->getLocale()),
            // The two moderation buttons are plain forms, not Thelia forms, so they carry the
            // session token by hand. The answer form gets its own from BaseForm.
            'token' => $this->tokenProvider->assignToken(),
        ]);
    }

    #[Route('/ProductQuestion/{id}/answer', name: '_answer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function answerAction(Request $request, int $id): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::UPDATE)) {
            return $denied;
        }

        $question = $this->storage->findById($id);

        if (null === $question) {
            return $this->backToList();
        }

        $form = $this->createForm(ProductQuestionAnswerForm::getName());

        try {
            // Validates the CSRF token as well as the field: a moderator's answer is a public
            // statement by the shop, and nothing else may post one in their name.
            $data = $this->validateForm($form)->getData();

            $this->answerer->answer($question, $data['answer'] ?? null, $this->currentAdminId());
        } catch (FormValidationException|InvalidProductQuestionException $exception) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans('Answering a question', [], ProductQuestionModule::MESSAGE_DOMAIN_BO),
                $exception->getMessage(),
                $form,
                $exception
            );

            return $this->renderQuestion($request, $question, $form);
        }

        return $this->backToQuestion($id);
    }

    #[Route('/ProductQuestion/{id}/refuse', name: '_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuseAction(Request $request, int $id): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::UPDATE)) {
            return $denied;
        }

        if (null !== $denied = $this->checkToken($request)) {
            return $denied;
        }

        $question = $this->storage->findById($id);

        if (null !== $question) {
            $this->refuser->refuse($question);
        }

        return $this->backToQuestion($id);
    }

    #[Route('/ProductQuestion/{id}/delete', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteAction(Request $request, int $id): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::DELETE)) {
            return $denied;
        }

        if (null !== $denied = $this->checkToken($request)) {
            return $denied;
        }

        $question = $this->storage->findById($id);

        if (null !== $question) {
            $this->storage->delete($question);
        }

        return $this->backToList();
    }

    /**
     * Access is granted on the module, not on a core resource: a shop gives its moderators
     * this module and nothing else of the back office.
     */
    private function checkModuleAccess(string $access): ?Response
    {
        return $this->checkAuth([], [ProductQuestionModule::getModuleCode()], $access);
    }

    private function checkToken(Request $request): ?Response
    {
        // The body first: a form posts its token there. The query string is what a tokenised
        // link carries, and TokenProvider reads only that one on its own.
        $token = (string) ($request->request->get('_token') ?? $request->query->get('_token', ''));

        try {
            if ($this->tokenProvider->checkToken($token)) {
                return null;
            }
        } catch (TokenAuthenticationException) {
            // No token in the session at all: nothing this request carries can match it.
        }

        return $this->backToList();
    }

    private function currentAdminId(): int
    {
        $admin = $this->securityContext->getAdminUser();

        return $admin instanceof Admin ? (int) $admin->getId() : 0;
    }

    private function backToList(): RedirectResponse
    {
        return new RedirectResponse(URL::getInstance()->absoluteUrl(ProductQuestionModule::ADMIN_LIST_PATH));
    }

    private function backToQuestion(int $id): RedirectResponse
    {
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/ProductQuestion/'.$id));
    }
}
