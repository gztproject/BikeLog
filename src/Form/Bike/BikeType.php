<?php

namespace App\Form\Bike;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Bike\CreateBikeCommand;
use App\Entity\Model\Model;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Validator\Constraints\File;

class BikeType extends AbstractType {
	/**
	 * public $nickname;
	 * public $purchasePrice;
	 * public $year;
	 * public $vin;
	 *
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add ( 'nickname', TextType::class, [ 
				'label' => 'label.nickname'
		] )->add ( 'purchaseDate', DateTimePickerType::class, [
		    'label' => 'label.PurchaseDate',
		    'widget' => 'single_text',
		    'format' => 'dd. MM. yyyy',
		    // prevents rendering it as type="date", to avoid HTML5 date pickers
		    'html5' => false
		] )->add ( 'vin', TextType::class, [ 
				'label' => 'label.vin'
		] )->add ( 'purchasePrice', TextType::class, [ 
				'label' => 'label.purchasePrice'
		] )->add ( 'purchaseOdometer', NumberType::class, [
				'label' => 'label.purchaseOdometer'
		] )->add ( 'year', NumberType::class, [
				'label' => 'label.year'
		] )->add ( 'model', EntityType::class, [ 
				'label' => 'label.model',
				'class' => Model::class,
				'choice_label' => 'name',
				'expanded' => false,
				'multiple' => false
		] )
		->add('picture', FileType::class, [
				'label' => 'label.picture',
				'mapped' => false,
				'required' => false,
				'constraints' => [
						new File([
								'maxSize' => '4M',
								'mimeTypes' => [
										'image/png',
										'image/jpeg'
								],
								'mimeTypesMessage' => 'message.invalid_image',
						])
				],
		])
		->add('save', SubmitType::class);
	}
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults ( array (
				'data_class' => CreateBikeCommand::class
		) );
	}
}