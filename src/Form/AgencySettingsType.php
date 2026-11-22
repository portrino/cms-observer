<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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
                'label' => 'Default hourly rate (EUR)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0'],
                'constraints' => [new Assert\Positive()],
            ])
            ->add('defaultManagementPercent', NumberType::class, [
                'label' => 'Default project management (%)',
                'required' => false,
                'html5' => true,
                'attr' => ['step' => '1', 'min' => '0', 'max' => '100'],
                'constraints' => [new Assert\Range(min: 0, max: 100)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
