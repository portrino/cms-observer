<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => false,
                'first_options' => ['label' => 'New password (leave blank to keep current)'],
                'second_options' => ['label' => 'Repeat new password'],
                'invalid_message' => 'The password fields must match.',
                'constraints' => [new Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.')],
            ])
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Current password (to confirm changes)',
                'constraints' => [new NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
