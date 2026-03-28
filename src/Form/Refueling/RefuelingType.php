<?php

namespace App\Form\Refueling;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use App\Form\Type\DateTimePickerType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Bike\Bike;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Repository\Bike\BikeRepository;
use App\Entity\User\User;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use DateTimeInterface;

class RefuelingType extends AbstractType {
	/**
	 *
	 * private $datetime;
	 * private $odometer;
	 * private $fuelQuantity;
	 * private $price;
	 * private $bike;
	 * private $isTankFull;
	 * private $isPreviousMissing;
	 *
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add ( 'datetime', DateTimePickerType::class, [ 
				'label' => 'label.datetime',
				'widget' => 'single_text',
				'format' => 'dd. MM. yyyy',
				'html5' => false,
				'attr' => [
						'placeholder' => 'DD. MM. YYYY',
						'autocomplete' => 'off'
				]
		] )->add ( 'odometer', NumberType::class, [ 
				'label' => 'label.odometer',
				'attr' => [
						'min' => 0,
						'step' => 1,
						'inputmode' => 'numeric',
						'placeholder' => '0',
						'data-refueling-role' => 'odometer'
				]
		] )->add ( 'fuelQuantity', NumberType::class, [ 
				'label' => 'label.fuelQuantity',
				'attr' => [
						'min' => 0,
						'step' => '0.01',
						'inputmode' => 'decimal',
						'placeholder' => '0.00'
				]
		] )->add ( 'price', NumberType::class, [ 
				'label' => 'label.price',
				'attr' => [
						'min' => 0,
						'step' => '0.01',
						'inputmode' => 'decimal',
						'placeholder' => '0.00'
				]
		] )->add ( 'bike', EntityType::class, [ 
				'label' => 'label.bike',
				'class' => Bike::class,
				'choice_label' => 'name',
				'expanded' => false,
				'multiple' => false,
				'attr' => [
						'data-refueling-role' => 'bike'
				],
				'choice_attr' => function (Bike $bike) {
					$lastRefueling = $bike->getLastRefueling ();
					return [
							'data-bike-name' => $bike->getName (),
							'data-current-odometer' => $bike->getOdometer (),
							'data-purchase-odometer' => $bike->getPurchaseOdometer (),
							'data-last-refueling-odometer' => $lastRefueling != null ? $lastRefueling->getOdometer () : '',
							'data-last-refueling-date' => $lastRefueling != null ? $lastRefueling->getDate ()->format ( DateTimeInterface::ATOM ) : ''
					];
				},
				'query_builder' => function (BikeRepository $repository) use ($options) {
					$qb = $repository->createQueryBuilder ( 'b' );
					// the function returns a QueryBuilder object
						return $qb->where ( $qb->expr ()->eq ( 'b.owner', '?1' ) )->setParameter ( '1', $options ["user"] );
				}
		] )->add ( 'comment', TextareaType::class, [
				'label' => 'label.comment',
				'required' => false,
				'attr' => [
						'rows' => 3
				]
		] )->add ( 'isTankFull', CheckboxType::class, [ 
				'label' => 'label.isTankFull',
				'required' => false
		] )->add ( 'isNotBreakingContinuum', CheckboxType::class, [ 
				'label' => 'label.isNotBreakingContinuum',
				'required' => false
		] )->add ( 'longitude', HiddenType::class, [
				'required' => false,
				'attr' => [
						'data-geo-field' => 'longitude'
				]
		] )->add ( 'latitude', HiddenType::class, [
				'required' => false,
				'attr' => [
						'data-geo-field' => 'latitude'
				]
		] )->add ( 'save', SubmitType::class );
	}
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults ( array (
				'data_class' => CreateRefuelingCommand::class,
				'user' => User::class
		) );
	}
}
