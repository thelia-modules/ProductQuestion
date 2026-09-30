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
use ProductQuestion\Service\ProductQuestionAsker;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * The textarea a customer asks with, on the product page.
 *
 * The constraints mirror ProductQuestionAsker's rules so that a customer sees them as a
 * field error, in their language, before the service is ever called. The service checks them
 * again on the cleaned text, which is what the API goes through as well.
 */
class ProductQuestionAskForm extends BaseForm
{
    public static function getName(): string
    {
        return 'productquestion_ask';
    }

    protected function buildForm(): void
    {
        $this->formBuilder->add('content', TextareaType::class, [
            'required' => true,
            'label' => $this->translator->trans('Your question', [], ProductQuestion::MESSAGE_DOMAIN),
            'constraints' => [
                new NotBlank(normalizer: 'trim'),
                new Length(min: ProductQuestionAsker::MINIMUM_LENGTH, max: ProductQuestionAsker::MAXIMUM_LENGTH),
            ],
            'attr' => [
                'rows' => 4,
                'maxlength' => ProductQuestionAsker::MAXIMUM_LENGTH,
            ],
        ]);
    }
}
