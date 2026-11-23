<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The language of the interface, apart from the profile form: no password needed to change it.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class LanguageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('locale', ChoiceType::class, [
            'label' => 'Language',
            'required' => false,
            'placeholder' => false,
            // each language in its own words, so it can be found in a language one can't read
            'choices' => ['Automatic (browser language)' => '', 'English' => 'en', 'Deutsch' => 'de'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
