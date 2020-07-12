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
				'label' => 'label.task',
				'query_builder' => function (TaskRepository $repository) use ($options) {
					$qb = $repository->createQueryBuilder ( 't' );
					// the function returns a QueryBuilder object
					return $qb->leftJoin ( 'App\Entity\ServiceInterval\ServiceInterval', 'si', 'WITH', 't.id = si.task' )->where ( 'si.bike = :bikeId' )->orWhere ( 'si.model = :modelId' )->setParameter ( 'bikeId', $options ["bike"] )->setParameter ( 'modelId', $options ["model"] );
				}
		] )->add ( 'cost', NumberType::class, [ 
				'label' => false,
				'attr' => [ 
						'class' => 'costInput'
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
