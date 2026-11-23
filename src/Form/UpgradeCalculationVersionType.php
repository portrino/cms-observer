<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\UpgradeItem;
use App\Entity\AgencySettings;
use App\Entity\UpgradeCalculationVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Everything a new version of a calculation consists of; one row collection per section.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class UpgradeCalculationVersionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (UpgradeSection::cases() as $section) {
            $builder->add($section->value, CollectionType::class, [
                'entry_type' => UpgradeItemType::class,
                'entry_options' => ['label' => false, 'package' => $section->hasPackages()],
                'allow_add' => true,
                'allow_delete' => true,
                // a row with anything but a status in it stays, so a missing label is reported instead of losing its hours
                'delete_empty' => static fn (?array $row): bool => null === $row || [] === array_filter(
                    array_diff_key($row, ['status' => true, 'packageType' => true]),
                    static fn (mixed $value): bool => null !== $value && '' !== trim((string) $value),
                ),
                // new rows by hand: nothing is known about the package yet
                'prototype_data' => $section->hasPackages() ? ['status' => UpgradeItemStatus::Manual->value] : null,
                'label' => $section->label(),
            ]);
        }

        $builder
            ->add('hourlyRate', NumberType::class, [
                'label' => 'Hourly rate (EUR)',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0'],
                'constraints' => [new Assert\NotNull(), new Assert\Positive()],
            ])
            ->add('managementPercent', NumberType::class, [
                'label' => 'Project management (%)',
                'html5' => true,
                'attr' => ['step' => '1', 'min' => '0', 'max' => '100'],
                'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 100)],
            ])
            ->add('headerNotes', TextareaType::class, [
                'label' => 'Calculation notes',
                'required' => false,
                'help' => 'E.g. when the upgrade is scheduled. Markdown, e.g. **bold** or lists.',
                'attr' => ['rows' => 3, 'data-controller' => 'markdown-editor'],
            ])
            ->add('remarks', TextareaType::class, [
                'required' => false,
                'help' => 'Shown below the totals, e.g. "License costs not included". Markdown, e.g. **bold** or lists.',
                'attr' => ['rows' => 2, 'data-controller' => 'markdown-editor'],
            ])
            ->add('author', TextType::class, [
                'label' => 'Your name or initials',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])
            ->add('note', TextType::class, [
                'label' => 'What changed',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. Estimated the package updates'],
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('final', CheckboxType::class, [
                'label' => 'Final version?',
                'help' => 'Marks the version sent to the customer. Later versions are still possible.',
                'required' => false,
            ]);
    }

    /**
     * Form data to start from. Rate, management share and remarks come from $previous,
     * e.g. the project's last calculation, else from the agency's defaults (none in code).
     *
     * @param list<UpgradeItem> $items
     *
     * @return array<string, mixed>
     */
    public static function dataFrom(array $items, ?UpgradeCalculationVersion $previous = null, ?AgencySettings $defaults = null): array
    {
        $data = [
            'hourlyRate' => $previous?->getHourlyRate() ?? $defaults?->getDefaultHourlyRate(),
            'managementPercent' => $previous?->getManagementPercent() ?? $defaults?->getDefaultManagementPercent(),
            'remarks' => $previous?->getRemarks(),
            'headerNotes' => $previous?->getHeaderNotes(),
            'final' => false,
        ];
        foreach (UpgradeSection::cases() as $section) {
            $data[$section->value] = [];
        }
        foreach ($items as $item) {
            $data[$item->section->value][] = $item->toArray();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data submitted form data
     *
     * @return list<UpgradeItem>
     */
    public static function itemsFrom(array $data): array
    {
        $items = [];
        foreach (UpgradeSection::cases() as $section) {
            $rows = array_values($data[$section->value] ?? []);
            // stable: rows without a position, e.g. without JavaScript, keep their order
            uksort($rows, static fn (int $a, int $b): int => [(int) ($rows[$a]['position'] ?? $a), $a] <=> [(int) ($rows[$b]['position'] ?? $b), $b]);
            foreach ($rows as $row) {
                unset($row['position']);
                // a single effort is both min and max, or the totals would show e.g. "4–0 h"
                $row['minHours'] ??= $row['maxHours'] ?? null;
                $row['maxHours'] ??= $row['minHours'];
                $items[] = UpgradeItem::fromArray(['section' => $section->value] + $row);
            }
        }

        return UpgradeItem::withSectionSums($items);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
