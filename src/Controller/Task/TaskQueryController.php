<?php

namespace App\Controller\Task;

use App\Entity\Bike\Bike;
use App\Repository\Bike\BikeRepository;
use App\Repository\Refueling\RefuelingRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\Task\TaskRepository;

class TaskQueryController extends AbstractController {
	/**
	 *
	 * @Route("/dashboard/task", methods={"GET"}, name="tasks_index")
	 */
	public function index(TaskRepository $tasks, BikeRepository $bikes, Request $request, PaginatorInterface $paginator): Response {
		$bikeId = $request->query->get ( 'bike', null );
		$bikeId = $bikeId === "" ? null : $bikeId;
		$bike = null;
		if ($bikeId) {
			$bike = $bikes->findOneBy ( [ 
					'id' => $bikeId
			] );
			if ($bike->getOwner () != $this->getUser ())
				throw new SecurityError ( "Bikes can only be shown to their owners." );
		}

		$queryBuilder = $tasks->getFilteredQuery ( $bikeId, $this->getUser () );

		$pagination = $paginator->paginate ( $queryBuilder, $request->query->getInt ( 'page', 1 ), 10 );
		return $this->render ( 'dashboard/task/index.html.twig', [ 
				'tasks' => $pagination,
				'bike' => $bike
		] );
	}
}
