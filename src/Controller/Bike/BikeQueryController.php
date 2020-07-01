<?php

namespace App\Controller\Bike;

use App\Repository\Bike\BikeRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class BikeQueryController extends AbstractController {
	/**
	 *
	 * @Route("/dashboard/bike", methods={"GET"}, name="bike_index")
	 */
	public function index(Request $request, PaginatorInterface $paginator): Response { 
		$user = $this->getUser();
		$myBikes = $user->getBikes();		
		
		$pagination = $paginator->paginate ( $myBikes );
		return $this->render ( 'dashboard/refueling/index.html.twig', ['bikes' => $pagination]);
	}
	
	/**
	 * @Route("/dashboard/bike/list", methods={"GET"}, name="bike_list")
	 */
	public function list(): JsonResponse	
	{
		$user = $this->getUser();
		$myBikes = $user->getBikes();
		
		$orgDataArray = array();
		
		foreach($myBikes as $bike)
		{
			$dto = ['id'=>$bike->getId(), 'name'=>$bike->getName()];
			array_push($orgDataArray, $dto);
		}
		
		return new JsonResponse(
				array(
						array(
								'status'=>'ok',
								'data'=>array(
										'bikes'=>$orgDataArray
								)
						)
				)
				);
	}
}
