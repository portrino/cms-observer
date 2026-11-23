<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Confirms a 2FA change with an authentication code, optionally plus the current password.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class TwoFactorConfirmationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (true === $options['require_password']) {
            $builder->add('currentPassword', PasswordType::class, [
                'label' => 'profile.field.current_password',
                'constraints' => [new NotBlank()],
            ]);
        }

        $builder->add('code', TextType::class, [
            'label' => $options['code_label'],
            'constraints' => [new NotBlank()],
            'attr' => ['autocomplete' => 'one-time-code', 'autocapitalize' => 'off', 'spellcheck' => 'false'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'require_password' => false,
            'code_label' => 'profile.field.code',
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
        $resolver->setAllowedTypes('code_label', 'string');
    }
}
