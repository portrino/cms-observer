<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\UpgradeItemStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One row of a calculation, as the array of UpgradeItem::toArray().
 *
 * @extends AbstractType<array<string, mixed>>
 */
class UpgradeItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hours = ['required' => false, 'scale' => 2, 'html5' => true, 'attr' => ['step' => '0.25', 'min' => '0'], 'constraints' => [new Assert\PositiveOrZero()]];

        $builder
            ->add('label', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('minHours', NumberType::class, $hours)
            ->add('maxHours', NumberType::class, $hours)
            ->add('todo', TextType::class, ['required' => false])
            ->add('notes', TextareaType::class, ['required' => false, 'attr' => ['rows' => 1]])
            ->add('assignee', TextType::class, ['required' => false]);

        if (true === $options['package']) {
            $builder
                ->add('package', TextType::class, ['required' => false])
                ->add('packageType', HiddenType::class)
                ->add('installedVersion', TextType::class, ['required' => false])
                ->add('targetVersion', TextType::class, ['required' => false])
                ->add('status', ChoiceType::class, [
                    'choices' => array_combine(
                        array_map(static fn (UpgradeItemStatus $status): string => $status->label(), UpgradeItemStatus::cases()),
                        array_map(static fn (UpgradeItemStatus $status): string => $status->value, UpgradeItemStatus::cases()),
                    ),
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'package' => false,
            'constraints' => [new Assert\Callback(static function (mixed $row, ExecutionContextInterface $context): void {
                if (is_array($row) && isset($row['minHours'], $row['maxHours']) && $row['maxHours'] < $row['minHours']) {
                    $context->buildViolation('The maximum must not be below the minimum.')->atPath('[maxHours]')->addViolation();
                }
            })],
        ]);
        $resolver->setAllowedTypes('package', 'bool');
    }
}
