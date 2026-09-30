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
use ProductQuestion\Exception\ProductQuestionsClosedException;
use ProductQuestion\Form\ProductQuestionAskForm;
use ProductQuestion\ProductQuestion;
use ProductQuestion\Service\Front\CurrentCustomerInterface;
use ProductQuestion\Service\Front\ProductQuestionAnswerLimiter;
use ProductQuestion\Service\Front\ProductQuestionAskLimiter;
use ProductQuestion\Service\Front\ProductQuestionSearchOffer;
use ProductQuestion\Service\Front\PublishedQuestionsPresenter;
use ProductQuestion\Service\Front\QuestionSearchTerm;
use ProductQuestion\Service\ProductQuestionAsker;
use ProductQuestion\Service\ProductQuestionAvailability;
use ProductQuestion\Service\ProductQuestionCustomerAnswerer;
use ProductQuestion\Service\ProductQuestionHelpfulVoter;
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
    /** Pages shown at most by "Show more", which bounds the one query of the list. */
    public const MAXIMUM_PAGE = 50;

    #[LiveProp]
    public int $productId = 0;

    #[LiveProp]
    public string $locale = '';

    /**
     * How many pages of questions are shown, the first included. Raised by "Show more", and set
     * on mount from the link of the same name for a visitor without JavaScript.
     */
    #[LiveProp]
    public int $page = 1;

    /**
     * What the visitor searches the questions for. Typed in the field, or set on mount from the
     * same field posted without JavaScript. Only applied while the product offers a search.
     */
    #[LiveProp(writable: true)]
    public string $search = '';

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

    /** Set after a helpful vote, shown under the answer it was cast for. */
    #[LiveProp]
    public ?int $voteFeedbackAnswerId = null;

    #[LiveProp]
    public ?string $voteFeedback = null;

    /** Read once per render: the template asks through canAsk() and canAnswer() both. */
    private ?bool $open = null;

    /** @var array{questions: list<array<string, mixed>>, total: int, published: int}|null */
    private ?array $published = null;

    /** The term the list was actually filtered with, once it has been read. */
    private ?string $appliedSearch = null;

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
        private readonly ProductQuestionHelpfulVoter $voter,
        private readonly ProductQuestionAvailability $availability,
        private readonly ProductQuestionSearchOffer $searchOffer,
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
            'helpful' => $this->trans('This answer helped me'),
            'helpfulCount' => $this->trans('%count% customer(s) found this helpful'),
            'closed' => $this->trans('This product no longer takes questions.'),
            'more' => $this->trans('Show more questions'),
            'searchLabel' => $this->trans('Search the questions'),
            'searchButton' => $this->trans('Search'),
            'searchClear' => $this->trans('Clear the search'),
            'searchFound' => $this->trans('%count% question(s) match "%term%".'),
            'searchNone' => $this->trans('No question matches "%term%".'),
            'askedIn' => $this->trans('Asked in %language%'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getQuestions(): array
    {
        return $this->published()['questions'];
    }

    /**
     * Whether there are published questions past the ones shown, and a page left to show them:
     * at the last page the link would only draw the same list again.
     */
    public function hasMore(): bool
    {
        return $this->perPage() > 0
            && $this->currentPage() < self::MAXIMUM_PAGE
            && $this->published()['total'] > $this->shownLimit();
    }

    /**
     * The link a visitor without JavaScript follows to the next page: the search it shows, if
     * any, is kept.
     */
    public function getMoreHref(): string
    {
        return '?'.http_build_query(array_filter([
            'questions_page' => $this->currentPage() + 1,
            'questions_search' => $this->getAppliedSearch(),
        ], static fn (int|string|null $value): bool => null !== $value)).'#product-questions';
    }

    #[LiveAction]
    public function more(): void
    {
        $this->page = $this->currentPage() + 1;
    }

    /**
     * Whether the product has enough published questions to offer a search in them.
     */
    public function isSearchOffered(): bool
    {
        return $this->searchOffer->isOfferedFor($this->published()['published']);
    }

    /** The term the list is filtered with, or null when it shows every question. */
    public function getAppliedSearch(): ?string
    {
        $this->published();

        return $this->appliedSearch;
    }

    /** How many questions the search found, all pages together. */
    public function getSearchTotal(): int
    {
        return $this->published()['total'];
    }

    /**
     * The name of the language a question was asked in, in the language of the page: "French" on
     * the English page, "anglais" on the French one.
     */
    public function languageName(string $questionLocale): string
    {
        $name = \Locale::getDisplayLanguage($questionLocale, $this->locale);

        // As the language itself writes it in a sentence: "French" in English, "anglais" in French.
        return '' === $name ? $questionLocale : $name;
    }

    /** The lang attribute of a question asked in another language than the page's. */
    public function htmlLang(string $questionLocale): string
    {
        return str_replace('_', '-', $questionLocale);
    }

    /** A new search starts from the first page of its results. */
    #[LiveAction]
    public function applySearch(): void
    {
        $this->page = 1;
    }

    #[LiveAction]
    public function clearSearch(): void
    {
        $this->search = '';
        $this->page = 1;
    }

    /**
     * Whether the product takes new questions and answers. The published ones are shown either
     * way: closing stops what customers write, not what they read.
     */
    public function isOpen(): bool
    {
        return $this->open ??= $this->availability->isOpenFor($this->productId);
    }

    public function canAsk(): bool
    {
        return $this->isOpen() && null !== $this->currentCustomer->id();
    }

    public function canAnswer(): bool
    {
        return $this->settings->allowsCustomerAnswers() && null !== $this->currentCustomer->id() && $this->isOpen();
    }

    /**
     * The count is recounted from the votes on the next render; what the visitor sees here is
     * whether theirs was taken. Nothing marks a button as already clicked in the page itself: the
     * product page may come from a shared cache, and must not carry anything of one visitor.
     */
    #[LiveAction]
    public function vote(#[LiveArg] int $answerId): void
    {
        $this->voteFeedbackAnswerId = $answerId;

        $customerId = $this->currentCustomer->id();

        if (null === $customerId) {
            $this->voteFeedback = $this->trans('Sign in to say an answer helped you.');

            return;
        }

        try {
            $counted = $this->voter->vote($answerId, $customerId);
        } catch (InvalidProductQuestionException) {
            $this->voteFeedback = $this->trans('Your vote could not be counted.');

            return;
        }

        $this->voteFeedback = $counted
            ? $this->trans('Thank you, your vote has been counted.')
            : $this->trans('Your vote was already counted.');
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
        } catch (ProductQuestionsClosedException) {
            // Closed between the render and the post.
            $this->answerFeedback = $this->trans('This product no longer takes questions.');
            $this->answeringQuestionId = null;

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
        } catch (ProductQuestionsClosedException) {
            $this->error = $this->trans('This product no longer takes questions.');

            return;
        }

        $this->feedback = $this->trans('Thank you! Your question has been sent to the shop and will appear here once published.');

        $this->resetForm();
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->createForm(ProductQuestionAskForm::getName())->getForm();
    }

    /**
     * @return array{questions: list<array<string, mixed>>, total: int, published: int}
     */
    private function published(): array
    {
        if (null !== $this->published) {
            return $this->published;
        }

        // Not a query more when the shop offers no search at all.
        $search = $this->searchOffer->isEnabled() ? QuestionSearchTerm::normalize($this->search) : null;
        $allLanguages = $this->settings->showsAllLanguages();
        $published = $this->presenter->forProduct($this->productId, $this->locale, $this->shownLimit(), $search, $allLanguages);

        // A term in the link of a product below the threshold: the field is not on the page, so
        // the list is not filtered either.
        if (null !== $search && !$this->searchOffer->isOfferedFor($published['published'])) {
            $search = null;
            $published = $this->presenter->forProduct($this->productId, $this->locale, $this->shownLimit(), null, $allLanguages);
        }

        $this->appliedSearch = $search;

        return $this->published = $published;
    }

    private function perPage(): int
    {
        return $this->settings->questionsPerPage();
    }

    /** 0 when the shop shows every question on one page. */
    private function shownLimit(): int
    {
        return $this->perPage() * $this->currentPage();
    }

    /** The page prop is a number anyone can put in the link: kept to what exists. */
    private function currentPage(): int
    {
        return max(1, min($this->page, self::MAXIMUM_PAGE));
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], ProductQuestion::MESSAGE_DOMAIN);
    }
}
