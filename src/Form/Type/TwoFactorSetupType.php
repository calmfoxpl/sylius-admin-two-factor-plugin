<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/** @extends AbstractType<array{code: string|null}> */
final class TwoFactorSetupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'calmfox_admin_two_factor.ui.code',
            'constraints' => [
                new NotBlank(message: 'calmfox_admin_two_factor.code.not_blank'),
                new Regex(pattern: '/^\d{6}$/', message: 'calmfox_admin_two_factor.code.invalid'),
            ],
            'attr' => [
                'autocomplete' => 'one-time-code',
                'inputmode' => 'numeric',
                'pattern' => '[0-9]*',
                'maxlength' => 6,
                'autofocus' => 'autofocus',
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'calmfox_admin_two_factor_setup';
    }
}
