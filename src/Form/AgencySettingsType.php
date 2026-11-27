<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class AgencySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('defaultHourlyRate', NumberType::class, [
                'label' => 'settings.field.rate',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0'],
                'constraints' => [new Assert\Positive()],
            ])
            ->add('defaultManagementPercent', NumberType::class, [
                'label' => 'settings.field.management',
                'required' => false,
                'html5' => true,
                'attr' => ['step' => '1', 'min' => '0', 'max' => '100'],
                'constraints' => [new Assert\Range(min: 0, max: 100)],
            ])
            ->add('packagistVendors', TextType::class, [
                'label' => 'settings.field.vendors',
                'help' => 'settings.help.vendors',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. portrino'],
                // Composer's vendor names, comma-separated
                'constraints' => [new Assert\Regex('/^\s*[a-z0-9]([_.-]?[a-z0-9]+)*(\s*,\s*[a-z0-9]([_.-]?[a-z0-9]+)*)*\s*$/i', message: 'Enter Composer vendor names like "portrino", separated by commas.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
