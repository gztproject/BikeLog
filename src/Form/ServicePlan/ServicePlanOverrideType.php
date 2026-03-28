<?php

namespace App\Form\ServicePlan;

use App\Entity\Bike\Bike;
use App\Entity\Model\Model;
use App\Entity\ServiceInterval\ServiceInterval;
use App\Entity\Task\Task;
use App\Repository\Task\TaskRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServicePlanOverrideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Model $model */
        $model = $options['model'];

        /** @var Bike|null $bike */
        $bike = $options['bike'];

        $builder
            ->add('task', EntityType::class, [
                'label' => 'label.task',
                'class' => Task::class,
                'placeholder' => 'label.selectTask',
                'choice_label' => function (Task $task): string {
                    $part = $task->getPart();

                    return $part != null
                        ? $part->getName() . ' - ' . $task->getName()
                        : $task->getName();
                },
                'query_builder' => function (TaskRepository $repository) use ($model, $bike) {
                    return $repository->getServicePlanTasksQuery($model, $bike);
                },
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
            'data_class' => ServicePlanOverrideCommand::class,
            'model' => null,
            'bike' => null,
        ]);

        $resolver->setAllowedTypes('model', [Model::class]);
        $resolver->setAllowedTypes('bike', ['null', Bike::class]);
    }
}
