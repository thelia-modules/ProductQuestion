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

namespace ProductQuestion\Twig;

use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Form\ProductQuestionAskForm;
use ProductQuestion\ProductQuestion;
use ProductQuestion\Service\Front\CurrentCustomerInterface;
use ProductQuestion\Service\Front\ProductQuestionAnswerLimiter;
use ProductQuestion\Service\Front\ProductQuestionAskLimiter;
use ProductQuestion\Service\Front\PublishedQuestionsPresenter;
use ProductQuestion\Service\ProductQuestionAsker;
use ProductQuestion\Service\ProductQuestionCustomerAnswerer;
use ProductQuestion\Service\ProductQuestionSettingsInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\Form\TheliaFormFactory;

/**
 * The questions block of a product page: the published questions with their answers, the form
 * to ask one and, when the shop takes them, the form to answer someone else's.
 *
 * The form is drawn for a signed-in customer only; a visitor is invited to sign in instead.
 * Asking goes through ProductQuestionAsker, which is the same door the API uses, so the
 * question a customer types here starts pending exactly as one posted from elsewhere.
 *
 * The language travels in the props rather than being read again on each action: the
 * request a live action arrives on is not the product page, and the list must not switch
 * language between the first render and the one that follows a post.
 */
#[AsLiveComponent(name: 'ProductQuestion', template: '@ProductQuestionModule/components/ProductQuestion.html.twig')]
class ProductQuestionBlock
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public int $productId = 0;

    #[LiveProp]
    public string $locale = '';

    /** Set after a successful post; a form error would be lost, the props are rehydrated. */
    #[LiveProp]
    public ?string $feedback = null;

    #[LiveProp]
    public ?string $error = null;

    /** The question the customer opened the answer form under, if any. */
    #[LiveProp(writable: true)]
    public ?int $answeringQuestionId = null;

    #[LiveProp(writable: true)]
    public string $answerContent = '';

    /** Set after an answer is sent, shown under the question it was written for. */
    #[LiveProp]
    public ?int $answerFeedbackQuestionId = null;

    #[LiveProp]
    public ?string $answerFeedback = null;

    #[LiveProp]
    public bool $answerFeedbackIsError = false;

    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        private readonly ProductQuestionAsker $asker,
        private readonly ProductQuestionAskLimiter $askLimiter,
        private readonly PublishedQuestionsPresenter $presenter,
        private readonly CurrentCustomerInterface $currentCustomer,
        // Thelia's own translator, the one carrying the module catalogues: Twig's |trans on
        // the front office knows the theme catalogue only.
        private readonly TranslatorInterface $translator,
        private readonly ProductQuestionCustomerAnswerer $customerAnswerer,
        private readonly ProductQuestionAnswerLimiter $answerLimiter,
        private readonly ProductQuestionSettingsInterface $settings,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return [
            'title' => $this->trans('Customer questions'),
            'answer' => $this->trans('Answer from the shop'),
            'customerAnswer' => $this->trans('Answer from a customer'),
            'noAnswer' => $this->trans('No answer yet.'),
            'empty' => $this->trans('No question has been published about this product yet.'),
            'ask' => $this->trans('Ask a question'),
            'send' => $this->trans('Send my question'),
            'signIn' => $this->trans('Sign in to ask a question about this product.'),
            'signInLink' => $this->trans('Sign in'),
            'answerThis' => $this->trans('Answer this question'),
            'yourAnswer' => $this->trans('Your answer'),
            'sendAnswer' => $this->trans('Send my answer'),
            'cancel' => $this->trans('Cancel'),
        ];
    }

    /**
     * @return list<array{id: int, content: string, answers: list<array{id: int, content: string, official: bool, helpfulCount: int, publishedAt: ?\DateTimeInterface}>}>
     */
    public function getQuestions(): array
    {
        return $this->presenter->forProduct($this->productId, $this->locale);
    }

    public function canAsk(): bool
    {
        return null !== $this->currentCustomer->id();
    }

    public function canAnswer(): bool
    {
        return $this->settings->allowsCustomerAnswers() && null !== $this->currentCustomer->id();
    }

    #[LiveAction]
    public function openAnswer(#[LiveArg] int $questionId): void
    {
        $this->answeringQuestionId = $questionId;
        $this->answerContent = '';
        $this->answerFeedbackQuestionId = null;
        $this->answerFeedback = null;
    }

    #[LiveAction]
    public function cancelAnswer(): void
    {
        $this->answeringQuestionId = null;
        $this->answerContent = '';
    }

    #[LiveAction]
    public function sendAnswer(): void
    {
        $questionId = (int) $this->answeringQuestionId;
        $this->answerFeedbackQuestionId = $questionId;
        $this->answerFeedbackIsError = true;

        $customerId = $this->currentCustomer->id();

        if (null === $customerId || !$this->settings->allowsCustomerAnswers()) {
            $this->answerFeedback = $this->trans('Sign in to answer this question.');

            return;
        }

        // A text the service refuses spends nothing; the budget is spent on an answer about to be
        // written, as for the questions.
        if (mb_strlen(trim($this->answerContent)) < ProductQuestionCustomerAnswerer::MINIMUM_LENGTH) {
            $this->answerFeedback = $this->trans('Your answer could not be sent. Please check it and try again.');

            return;
        }

        if (!$this->answerLimiter->allows($customerId)) {
            $this->answerFeedback = $this->trans('Too many answers have been sent. Please try again later.');

            return;
        }

        try {
            $this->customerAnswerer->answer($questionId, $customerId, $this->answerContent);
        } catch (InvalidProductQuestionException) {
            $this->answerFeedback = $this->trans('Your answer could not be sent. Please check it and try again.');

            return;
        }

        $this->answerFeedbackIsError = false;
        $this->answerFeedback = $this->trans('Thank you! Your answer has been sent to the shop and will appear here once published.');
        $this->answeringQuestionId = null;
        $this->answerContent = '';
    }

    #[LiveAction]
    public function ask(): void
    {
        $this->error = null;
        $this->feedback = null;

        $customerId = $this->currentCustomer->id();

        // The form is not drawn for a visitor; a request that reaches here anyway is refused
        // the same way, silently.
        if (null === $customerId) {
            $this->error = $this->trans('Sign in to ask a question about this product.');

            return;
        }

        // Throws when the form is invalid, which re-renders the component with its errors. The
        // form first, as the API validates before its processor: a typo costs nothing, and only
        // a question that is about to be written spends the budget.
        $this->submitForm();

        if (!$this->askLimiter->allows($customerId, $this->productId)) {
            $this->error = $this->trans('Too many questions have been sent. Please try again later.');

            return;
        }

        $content = $this->getForm()->get('content')->getData();

        try {
            $this->asker->ask($this->productId, $customerId, $this->locale, \is_string($content) ? $content : null);
        } catch (InvalidProductQuestionException) {
            // The form already carries the length rules; what remains is a text the sanitizer
            // emptied, which the customer cannot tell apart from an invalid one.
            $this->error = $this->trans('Your question could not be sent. Please check it and try again.');

            return;
        }

        $this->feedback = $this->trans('Thank you! Your question has been sent to the shop and will appear here once published.');

        $this->resetForm();
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->createForm(ProductQuestionAskForm::getName())->getForm();
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], ProductQuestion::MESSAGE_DOMAIN);
    }
}
