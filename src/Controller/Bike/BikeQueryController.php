<?php

namespace App\Controller\Bike;

use App\Entity\Bike\Bike;
use App\Repository\Bike\BikeRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Sandbox\SecurityError;

class BikeQueryController extends AbstractController {
	/**
	 *
	 * @Route("/dashboard/bike", methods={"GET"}, name="bike_index")
	 */
	public function index(Request $request, PaginatorInterface $paginator): Response { 
		$user = $this->getUser();
		$myBikes = $user->getBikes();		
		
		$pagination = $paginator->paginate ( $myBikes );
		return $this->render ( 'dashboard/bike/index.html.twig', ['bikes' => $pagination]);
	}
	
	/**
	 * Finds and displays the bike entity.
	 *
	 * @Route("/dashboard/bike/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="bike_show")
	 */
	public function show(Bike $bike): Response
	{
		if($bike->getOwner() != $this->getUser())
			throw new SecurityError("Bikes can only be shown to their owners.");
		
		return $this->render('dashboard/bike/show.html.twig', [
				'bike' => $bike,
		]);
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
