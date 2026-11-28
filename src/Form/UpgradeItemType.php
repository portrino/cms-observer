<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeNoteLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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
            // edited in a dialog (note-editor controller), the row shows a preview
            ->add('notes', HiddenType::class, ['required' => false])
            ->add('noteLevel', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'calculation.no_color',
                'choices' => array_combine(
                    array_map(static fn (UpgradeNoteLevel $level): string => 'calculation.note_level.'.$level->value, UpgradeNoteLevel::cases()),
                    array_map(static fn (UpgradeNoteLevel $level): string => $level->value, UpgradeNoteLevel::cases()),
                ),
            ])
            ->add('assignee', TextType::class, ['required' => false])
            // order of the rows after moving them in the browser; not stored, the row order is
            ->add('position', HiddenType::class, ['required' => false])
            // set on the positions that stand for a package section's hours
            ->add('sumOf', HiddenType::class, ['required' => false])
            // what the resolver preset, to tell on a refresh whether that changed (UpgradeItem::withPreset())
            ->add('preset', HiddenType::class, ['required' => false])
            // a row a refresh changed: someone checks it before the version is saved; neither is stored
            ->add('review', HiddenType::class, ['required' => false])
            ->add('reviewed', CheckboxType::class, ['required' => false, 'label' => 'calculation.reviewed']);

        if (true === $options['package']) {
            $builder
                // the package key, e.g. to match the row with a later report; the label shows the composer name
                ->add('package', HiddenType::class)
                ->add('packageType', HiddenType::class)
                ->add('installedVersion', TextType::class, ['required' => false])
                ->add('targetVersion', TextType::class, ['required' => false])
                ->add('status', ChoiceType::class, [
                    'label' => 'calculation.todo',
                    'choices' => array_combine(
                        array_map(static fn (UpgradeItemStatus $status): string => 'calculation.status.'.$status->value, UpgradeItemStatus::cases()),
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
                if (is_array($row) && '1' === ($row['review'] ?? null) && true !== ($row['reviewed'] ?? false)) {
                    $context->buildViolation('Check the changed row before saving.')->atPath('[reviewed]')->addViolation();
                }
            })],
        ]);
        $resolver->setAllowedTypes('package', 'bool');
    }
}
