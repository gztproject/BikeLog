<?php 

namespace App\Controller\Bike;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BikeCommandController extends AbstractController
{    
	/**
     * @Route("/dashboard/bike/new", methods={"GET"}, name="bike_new")
     */
	public function new(): Response
    {      	
    	return $this->render ( 'dashboard/bike/new.html.twig');
    }    
    
}
