<?php

namespace App\Form\Maintenance;

use App\Entity\MaintenanceTask\CreateMaintenanceTaskCommand;
use App\Entity\Task\Task;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Bike\Bike;
use App\Repository\Task\TaskRepository;
use App\Entity\Model\Model;

class MaintenanceTaskType extends AbstractType {
	/**
	 *
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {	   
		$builder->add ( 'task', EntityType::class, [ 
				'class' => Task::class,
				'choice_label' => 'name',
				'expanded' => false,
				'multiple' => false,
				'label' => false,
				'attr' => [
						'class' => 'form-control'
				],
		    'query_builder' => function (TaskRepository $repository) use ($options) {
		    return $repository->getBikeTasksQuery($options["bike"]->getId(), $options["model"]->getId());
				}
		] )->add ( 'cost', NumberType::class, [ 
				'label' => false,
				'attr' => [ 
						'class' => 'costInput',
						'min' => 0,
						'step' => '0.01',
						'inputmode' => 'decimal',
						'placeholder' => '0.00'
				]
		] );
	}
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults ( array (
				'data_class' => CreateMaintenanceTaskCommand::class,
				'bike' => Bike::class,
				'model' => Model::class
		) );
	}
}
