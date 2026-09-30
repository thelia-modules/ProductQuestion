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
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Repository\ClosedProductStorageInterface;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Service\BackOffice\BulkDecision;
use ProductQuestion\Service\BackOffice\ProductQuestionBulkModerator;
use ProductQuestion\Service\BackOffice\ProductQuestionEditPresenter;
use ProductQuestion\Service\BackOffice\ProductQuestionListFilters;
use ProductQuestion\Service\BackOffice\ProductQuestionListPresenter;
use ProductQuestion\Service\ProductQuestionAnswerer;
use ProductQuestion\Service\ProductQuestionAnswerModerator;
use ProductQuestion\Service\ProductQuestionPublisher;
use ProductQuestion\Service\ProductQuestionRefuser;
use ProductQuestion\Service\ProductQuestionSettingsInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
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
        private readonly ProductQuestionEditPresenter $editPresenter,
        private readonly ProductQuestionAnswerer $answerer,
        private readonly ProductQuestionRefuser $refuser,
        private readonly ProductQuestionAnswerStorageInterface $answers,
        private readonly ProductQuestionPublisher $publisher,
        private readonly ProductQuestionAnswerModerator $answerModerator,
        private readonly ProductQuestionSettingsInterface $settings,
        private readonly ClosedProductStorageInterface $closedProducts,
        private readonly ProductQuestionBulkModerator $bulkModerator,
    ) {
    }

    #[Route('/ProductQuestion', name: '_list', methods: ['GET'])]
    public function listAction(Request $request): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::VIEW)) {
            return $denied;
        }

        $filters = ProductQuestionListFilters::fromRequest($request);

        return $this->render('product-questions', [
            ...$this->listPresenter->present($filters, $request->getLocale()),
            'allowsCustomerAnswers' => $this->settings->allowsCustomerAnswers(),
            'questionsClosed' => $this->settings->questionsClosed(),
            'questionsPerPage' => $this->settings->questionsPerPage(),
            'searchThreshold' => $this->settings->searchThreshold(),
            // The bulk buttons follow the rights their route checks.
            'canUpdate' => $this->isModuleGranted(AccessManager::UPDATE),
            'canDelete' => $this->isModuleGranted(AccessManager::DELETE),
            'csrfToken' => $this->tokenProvider->assignToken(),
        ]);
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

    private function renderQuestion(Request $request, ProductQuestion $question, ?BaseForm $form = null): Response
    {
        // On the error path the submitted form is handed back rather than rebuilt, so what a
        // moderator typed is still in the textarea next to the message telling them why.
        $form ??= $this->createForm(ProductQuestionAnswerForm::getName(), data: [
            'answer' => $this->answers->findOfficialForQuestion((int) $question->getId())?->getContent(),
        ]);

        return $this->render('product-question-edit', [
            ...$this->editPresenter->present($question, $request->getLocale()),
            'form' => $form->getForm()->createView(),
            // The two moderation buttons are plain forms, not Thelia forms, so they carry the
            // session token by hand. The answer form gets its own from BaseForm. Not `token`:
            // that name is the profiler's, like `status`.
            'csrfToken' => $this->tokenProvider->assignToken(),
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
            $this->log(AccessManager::UPDATE, \sprintf('Official answer to product question %d published', $id), $id);
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

    #[Route('/ProductQuestion/{id}/publish', name: '_publish', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publishAction(Request $request, int $id): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::UPDATE)) {
            return $denied;
        }

        if (null !== $denied = $this->checkToken($request)) {
            return $denied;
        }

        $question = $this->storage->findById($id);

        if (null !== $question) {
            $this->publisher->publish($question);
            $this->log(AccessManager::UPDATE, \sprintf('Product question %d published without an answer', $id), $id);
        }

        return $this->backToQuestion($id);
    }

    #[Route('/ProductQuestion/answer/{answerId}/{decision}', name: '_moderate_answer', requirements: ['answerId' => '\d+', 'decision' => 'publish|refuse|delete'], methods: ['POST'])]
    public function moderateAnswerAction(Request $request, int $answerId, string $decision): Response
    {
        if (null !== $denied = $this->checkModuleAccess('delete' === $decision ? AccessManager::DELETE : AccessManager::UPDATE)) {
            return $denied;
        }

        if (null !== $denied = $this->checkToken($request)) {
            return $denied;
        }

        $answer = $this->answers->findById($answerId);

        if (!$answer instanceof ProductQuestionAnswer) {
            return $this->backToList();
        }

        match ($decision) {
            'publish' => $this->answerModerator->publish($answer),
            'refuse' => $this->answerModerator->refuse($answer),
            default => $this->answerModerator->delete($answer),
        };

        $this->log(
            'delete' === $decision ? AccessManager::DELETE : AccessManager::UPDATE,
            \sprintf('Answer %d to product question %d: %s', $answerId, (int) $answer->getQuestionId(), $decision),
            (int) $answer->getQuestionId(),
        );

        return $this->backToQuestion((int) $answer->getQuestionId());
    }

    /**
     * The questions ticked in the list, published, refused or deleted together. Back to the list
     * with the filters it was drawn with.
     */
    #[Route('/ProductQuestion/bulk', name: '_bulk', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $decision = BulkDecision::tryFrom((string) $request->request->get('decision', ''));

        if (null !== $denied = $this->checkModuleAccess(BulkDecision::Delete === $decision ? AccessManager::DELETE : AccessManager::UPDATE)) {
            return $denied;
        }

        if (null !== $denied = $this->checkToken($request)) {
            return $denied;
        }

        $filters = ProductQuestionListFilters::fromRequest($request);
        $back = new RedirectResponse(URL::getInstance()->absoluteUrl(ProductQuestionModule::ADMIN_LIST_PATH, $filters->toQueryParams()));

        if (null === $decision) {
            return $back;
        }

        $ids = array_map(intval(...), array_filter($request->request->all('ids'), is_numeric(...)));
        $done = $this->bulkModerator->apply($decision, array_values($ids));

        foreach ($done as $questionId) {
            $this->log(
                BulkDecision::Delete === $decision ? AccessManager::DELETE : AccessManager::UPDATE,
                \sprintf('Product question %d %s (bulk)', $questionId, $decision->pastTense()),
                $questionId,
            );
        }

        $session = $request->hasSession() ? $request->getSession() : null;

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', $this->getTranslator()->trans(
                '%count% question(s) processed.',
                ['%count%' => \count($done)],
                ProductQuestionModule::MESSAGE_DOMAIN_BO,
            ));
        }

        return $back;
    }

    #[Route('/ProductQuestion/settings', name: '_settings', methods: ['POST'])]
    public function settingsAction(Request $request): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::UPDATE)) {
            return $denied;
        }

        if (null !== $denied = $this->checkToken($request)) {
            return $denied;
        }

        $allowed = '1' === $request->request->get('allow_customer_answers');
        $closed = '1' === $request->request->get('questions_closed');
        $this->settings->setAllowsCustomerAnswers($allowed);
        $this->settings->setQuestionsClosed($closed);
        $this->settings->setQuestionsPerPage(self::number($request, 'questions_per_page'));
        $this->settings->setSearchThreshold(self::number($request, 'search_threshold'));
        $this->log(AccessManager::UPDATE, \sprintf(
            'Product question settings saved: customer answers %s, questions %s, %d per page, search above %d',
            $allowed ? 'on' : 'off',
            $closed ? 'closed' : 'open',
            $this->settings->questionsPerPage(),
            $this->settings->searchThreshold(),
        ));

        return $this->backToList();
    }

    /**
     * The switch of the product edit page's Modules tab. Back to that tab afterwards.
     */
    #[Route('/ProductQuestion/product/{productId}/closure', name: '_product_closure', requirements: ['productId' => '\d+'], methods: ['POST'])]
    public function productClosureAction(Request $request, int $productId): Response
    {
        if (null !== $denied = $this->checkModuleAccess(AccessManager::UPDATE)) {
            return $denied;
        }

        if (null !== $denied = $this->checkToken($request)) {
            return $denied;
        }

        $closed = '1' === $request->request->get('closed');
        $this->closedProducts->setClosed($productId, $closed);
        $this->log(AccessManager::UPDATE, \sprintf('Questions %s on product %d', $closed ? 'closed' : 'opened', $productId));

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/products/update', ['product_id' => $productId, 'current_tab' => 'modules']));
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
            $this->log(AccessManager::UPDATE, \sprintf('Product question %d refused', $id), $id);
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
            $this->log(AccessManager::DELETE, \sprintf('Product question %d deleted', $id), $id);
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

    /** A number field of the settings form; anything else is 0, the setting's "off". */
    private static function number(Request $request, string $field): int
    {
        $value = $request->request->get($field);

        return \is_string($value) && ctype_digit(trim($value)) ? (int) trim($value) : 0;
    }

    private function isModuleGranted(string $access): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [], [ProductQuestionModule::getModuleCode()], [$access]);
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

    /**
     * Every accepted moderation action leaves a line in the administration log, under the module
     * and the question it touched.
     */
    private function log(string $access, string $message, ?int $questionId = null): void
    {
        $this->adminLogAppend(ProductQuestionModule::getModuleCode(), $access, $message, $questionId);
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
        return new RedirectResponse(URL::getInstance()->absoluteUrl(ProductQuestionModule::ADMIN_LIST_PATH.'/'.$id));
    }
}
