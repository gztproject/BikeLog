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
				'label' => 'label.purchaseDate',
				'widget' => 'single_text',
				'format' => 'dd. MM. yyyy',
				// prevents rendering it as type="date", to avoid HTML5 date pickers
				'html5' => false,
				'attr' => [
						'placeholder' => 'DD. MM. YYYY',
						'autocomplete' => 'off'
				]
		] )->add ( 'vin', TextType::class, [ 
				'label' => 'label.vin',
				'required' => false,
				'attr' => [
						'maxlength' => 17,
						'placeholder' => 'JH2SC59A17M012345'
				]
		] )->add ( 'purchasePrice', NumberType::class, [
				'label' => 'label.purchasePrice',
				'attr' => [
						'min' => 0,
						'step' => '0.01',
						'inputmode' => 'decimal',
						'placeholder' => '0.00'
				]
		] )->add ( 'purchaseOdometer', NumberType::class, [
				'label' => 'label.purchaseOdometer',
				'attr' => [
						'min' => 0,
						'step' => 1,
						'inputmode' => 'numeric',
						'placeholder' => '0'
				]
		] )->add ( 'year', NumberType::class, [
				'label' => 'label.year',
				'attr' => [
						'min' => 1900,
						'max' => (int) date('Y') + 1,
						'step' => 1,
						'inputmode' => 'numeric',
						'placeholder' => date('Y')
				]
		] )->add ( 'model', EntityType::class, [ 
				'label' => 'label.model',
				'class' => Model::class,
				'choice_label' => 'name',
				'expanded' => false,
				'multiple' => false
		] )->add ( 'fuelTanksize', NumberType::class, [
				'label' => 'label.fuelTankSize',
				'required' => false,
				'attr' => [
						'min' => 0,
						'step' => '0.1',
						'inputmode' => 'decimal',
						'placeholder' => '0.0'
				]
		] )
		->add('picture', FileType::class, [
				'label' => 'label.picture',
				'mapped' => false,
				'required' => false,
				'attr' => [
						'accept' => 'image/png,image/jpeg'
				],
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
