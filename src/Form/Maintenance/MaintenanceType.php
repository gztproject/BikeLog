<?php

namespace App\Form\Maintenance;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use App\Form\Type\DateTimePickerType;
use App\Repository\Bike\BikeRepository;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\User\User;
use App\Entity\Workshop\Workshop;
use App\Entity\Bike\Bike;
use App\Entity\Maintenance\CreateMaintenanceCommand;
use App\Entity\Model\Model;
use App\Repository\Workshop\WorkshopRepository;

class MaintenanceType extends AbstractType {
	/**
	 *
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 *        	public $workshop;
	 *        	public $bike;
	 *        	public $date;
	 *        	public $odometer;
	 *        	public $spentTime;
	 *        	public $unspecifiedCost;
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add ( 'workshop', EntityType::class, [ 
				'class' => Workshop::class,
				'choice_label' => 'name',
				'expanded' => false,
				'multiple' => false,
				'label' => 'label.workshop',
				'query_builder' => function (WorkshopRepository $repository) use ($options) {
					$qb = $repository->createQueryBuilder ( 'w' );
					return $qb->leftJoin ( 'w.clients', 'wc' )->where ( $qb->expr ()->eq ( 'wc.id', '?1' ) )->setParameter ( '1', $options ["user"] );
				}
		] )->add ( 'bike', EntityType::class, [ 
				'class' => Bike::class,
				'choice_label' => 'name',
				'expanded' => false,
				'multiple' => false,
				'label' => 'label.bike',
				'query_builder' => function (BikeRepository $repository) use ($options) {
					$qb = $repository->createQueryBuilder ( 'b' );
					// the function returns a QueryBuilder object
					return $qb->where ( $qb->expr ()->eq ( 'b.owner', '?1' ) )->setParameter ( '1', $options ["user"] );
				}
		] )->add ( 'date', DateTimePickerType::class, [ 
				'label' => 'label.date',
				'widget' => 'single_text',
				'format' => 'dd. MM. yyyy',
				// prevents rendering it as type="date", to avoid HTML5 date pickers
				'html5' => false
		] )->add ( 'odometer', NumberType::class, [ 
				'label' => 'label.odometer',
				'attr' => [ 
						'class' => 'odometerInput'
				]
		] )->add ( 'spentTime', NumberType::class, [ 
				'label' => 'label.spentTime',
				'attr' => [ 
						'class' => 'spentTimeInput'
				]
		] )->add ( 'unspecifiedCosts', NumberType::class, [ 
				'label' => 'label.unspecifiedCosts',
				'attr' => [ 
						'class' => 'unspecifiedCostsInput'
				]
		] )->add ( 'maintenanceTaskCommands', CollectionType::class, [ 
				'entry_type' => MaintenanceTaskType::class,
				'entry_options' => [ 
						'bike' => $options ['bike'],
						'model' => $options ['model']
				],				
				'allow_add' => true,
				'allow_delete' => true,
				'label' => 'label.maintenanceTask',
				'by_reference' => false
		] );
	}
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults ( array (
				'data_class' => CreateMaintenanceCommand::class,
				'user' => User::class,
				'bike' => Bike::class,
				'model' => Model::class
		) );
	}
}
