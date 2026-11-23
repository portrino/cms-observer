<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CmsType;
use App\Entity\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'common.name',
                'constraints' => [new NotBlank()],
            ])
            ->add('url', UrlType::class, [
                'label' => 'project.form.url',
                // the clients send their domains and reports from other hosts (e.g. staging) are rejected
                'help' => 'project.form.url_help',
                'constraints' => [new NotBlank(), new Url()],
            ])
            ->add('type', EnumType::class, [
                'class' => CmsType::class,
                'label' => 'common.type',
                'choice_translation_domain' => false,
                'choice_label' => static fn (CmsType $type): string => $type->label(),
                'placeholder' => false,
            ])
            ->add('slaActive', CheckboxType::class, [
                'label' => 'project.form.sla',
                'required' => false,
            ])
            ->add('organization', EntityType::class, [
                'class' => Organization::class,
                'label' => 'organization.label',
                'choice_translation_domain' => false,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'organization.none',
            ])
            // organization_select_controller.js offers it as "+ New organization…" in the select above;
            // the controller creates it, or takes an existing one with that name
            ->add('newOrganization', TextType::class, [
                'label' => 'organization.new',
                'required' => false,
                'constraints' => [
                    new Length(max: 255),
                    new Callback(static function (?string $name, ExecutionContextInterface $context): void {
                        $form = $context->getRoot();
                        if ('' !== trim((string) $name) && $form instanceof FormInterface && null !== $form->get('organization')->getData()) {
                            $context->addViolation('Choose an existing organization or enter a new one, not both.');
                        }
                    }),
                ],
            ])
            ->add('repositoryUrl', UrlType::class, [
                'label' => 'project.form.repository',
                'required' => false,
                'constraints' => [new Url()],
            ])
            ->add('issueTrackerUrl', UrlType::class, [
                'label' => 'project.form.issue_tracker',
                'required' => false,
                'constraints' => [new Url()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
