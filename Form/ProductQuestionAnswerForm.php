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

namespace ProductQuestion\Form;

use ProductQuestion\ProductQuestion;
use ProductQuestion\Service\ProductQuestionAnswerer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * The textarea a moderator answers with.
 *
 * A Thelia form rather than a bare textarea, for the CSRF token it carries on its own. The
 * constraints here are what a moderator sees as a field error; the same rules are enforced
 * again in ProductQuestionAnswerer, which is what the API of the next phase goes through.
 */
class ProductQuestionAnswerForm extends BaseForm
{
    public static function getName(): string
    {
        return 'productquestion_answer';
    }

    protected function buildForm(): void
    {
        $this->formBuilder->add('answer', TextareaType::class, [
            'required' => true,
            'label' => $this->translator->trans('Answer', [], ProductQuestion::MESSAGE_DOMAIN_BO),
            'constraints' => [
                new NotBlank(),
                new Length(max: ProductQuestionAnswerer::MAXIMUM_LENGTH),
            ],
            'attr' => [
                'rows' => 6,
                'maxlength' => ProductQuestionAnswerer::MAXIMUM_LENGTH,
            ],
        ]);
    }
}
