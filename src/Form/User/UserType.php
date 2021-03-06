<?php 
// src/Form/UserType.php
namespace App\Form\User;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\CallbackTransformer;
use App\Entity\User\CreateUserCommand;
use Doctrine\DBAL\Types\StringType;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class,[
                'label' => 'label.email'
            ])
            ->add('mobile', TextType::class,[
            		'label' => 'label.mobile',
            		'required' => false,
            ])
            ->add('username', TextType::class,[
                'label' => 'label.username'
            ])
            ->add('firstName', TextType::class,[
                'label' => 'label.firstname'
            ])
            ->add('lastName', TextType::class,[
                'label' => 'label.lastname'
            ])
            ->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
            	'invalid_message' => 'The password fields must match.',
                'first_options'  => array('label' => 'label.password'),
                'second_options' => array('label' => 'label.repeatPassword'),
            	'required' => false,
            	'empty_data' => '',
            ))  
            ->add('oldPassword', PasswordType::class,[ 
            		'label' => 'label.oldPassword',
            		'required' => false,            		
            		'empty_data' => '',
            ])  
            ->add('profilePicture', FileType::class, [
            		'label' => 'label.profilePicture',
            		'mapped' => false,
            		'required' => false,
            		'constraints' => [
            				new File([
            						'maxSize' => '4M',
            						'mimeTypes' => [
            								'image/png',
            								'image/jpeg'
            						],
            						'mimeTypesMessage' => 'Please upload a valid png or jpg image',
            				])
            		],
            ])
            ->add('isRoleAdmin', CheckboxType::class,[
                'label' => 'label.isRoleAdmin',
            	'required' => false,
            ])
            ->get('isRoleAdmin')
                ->addModelTransformer(new CallbackTransformer(
                    function($boolToCheckbox){                        
                        return $boolToCheckbox?:false;
                    },
                    function($checkboxToBool){
                        if($checkboxToBool===null) return false;
                        return $checkboxToBool?:false;
                    }
                ))			
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => CreateUserCommand::class,
        ));
    }
}
