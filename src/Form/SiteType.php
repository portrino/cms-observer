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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Site URL',
                // the clients send their domains and reports from other hosts (e.g. staging) are rejected
                'help' => 'The production URL of the site, exactly as the monitored instance uses it (including or excluding "www."). Reports from other domains, e.g. a staging copy using the same token, are rejected.',
                'constraints' => [new NotBlank(), new Url()],
            ])
            ->add('type', EnumType::class, [
                'class' => CmsType::class,
                'choice_label' => static fn (CmsType $type): string => $type->label(),
                'placeholder' => false,
            ])
            ->add('slaActive', CheckboxType::class, [
                'label' => 'Active maintenance contract (SLA)',
                'required' => false,
            ])
            ->add('organization', EntityType::class, [
                'class' => Organization::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'No organization',
            ])
            ->add('repositoryUrl', UrlType::class, [
                'label' => 'Repository URL',
                'required' => false,
                'constraints' => [new Url()],
            ])
            ->add('issueTrackerUrl', UrlType::class, [
                'label' => 'Issue tracker URL',
                'required' => false,
                'constraints' => [new Url()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
