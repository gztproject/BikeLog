<?php 

namespace App\Controller\Refueling;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RefuelingCommandController extends AbstractController
{    
	/**
     * @Route("/dashboard/refueling/new", methods={"GET"}, name="refueling_new")
     */
	public function new(): Response
    {      	
    	return $this->render ( 'dashboard/refueling/new.html.twig');
    }    
    
}
