<?php

namespace App\Form\ServicePlan;

use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Model\Model;
use App\Entity\Part\Part;
use App\Entity\Task\Task;
use App\Repository\Part\PartRepository;
use App\Repository\Task\TaskRepository;
use App\Entity\ServiceInterval\ServiceInterval;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServicePlanTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Manufacturer $manufacturer */
        $manufacturer = $options['manufacturer'];

        /** @var Model $model */
        $model = $options['model'];

        $builder
            ->add('existingTask', EntityType::class, [
                'label' => 'label.existingTask',
                'class' => Task::class,
                'required' => false,
                'placeholder' => 'label.createNewTask',
                'help' => 'body.servicePlan.existingTaskModelScoped',
                'choice_label' => function (Task $task): string {
                    $part = $task->getPart();

                    return $part != null
                        ? $part->getName() . ' - ' . $task->getName()
                        : $task->getName();
                },
                'query_builder' => function (TaskRepository $repository) use ($model) {
                    return $repository->getModelTasksWithUniversalQuery($model);
                },
            ])
            ->add('part', EntityType::class, [
                'label' => 'label.part',
                'class' => Part::class,
                'required' => false,
                'placeholder' => 'label.selectPart',
                'help' => 'body.servicePlan.partOptional',
                'choice_label' => 'name',
                'attr' => [
                    'data-searchable-select' => 'true',
                ],
                'query_builder' => function (PartRepository $repository) use ($manufacturer) {
                    return $repository->getManufacturerPartsQuery($manufacturer);
                },
            ])
            ->add('name', TextType::class, [
                'label' => 'label.taskName',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                ],
            ])
            ->add('interval', NumberType::class, [
                'label' => 'label.interval',
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                    'inputmode' => 'numeric',
                    'placeholder' => '5000',
                ],
            ])
            ->add('intervalType', ChoiceType::class, [
                'label' => 'label.intervalType',
                'choices' => [
                    'label.intervalDistanceAbsolute' => ServiceInterval::ABSOLUTE_DISTANCE,
                    'label.intervalDistanceRelative' => ServiceInterval::RELATIVE_DISTANCE,
                    'label.days' => ServiceInterval::DAYS,
                    'label.months' => ServiceInterval::MONTHS,
                    'label.years' => ServiceInterval::YEARS,
                ],
                'choice_translation_domain' => 'messages',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServicePlanTaskCommand::class,
            'manufacturer' => null,
            'model' => null,
        ]);

        $resolver->setAllowedTypes('manufacturer', [Manufacturer::class]);
        $resolver->setAllowedTypes('model', [Model::class]);
    }
}
