<?php

namespace App\Controller\Refueling;

use App\Repository\Refueling\RefuelingRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RefuelingQueryController extends AbstractController {
	/**
	 *
	 * @Route("/dashboard/refueling", methods={"GET"}, name="refueling_index")
	 */
	public function index(RefuelingRepository $refuelings, Request $request, PaginatorInterface $paginator): Response {
		$dateFrom = $request->query->get ( 'dateFrom', null );
		$dateTo = $request->query->get ( 'dateTo', null );
		$bikeId = $request->query->get ( 'bike', null );
		$bikeId = $bikeId === "" ? null : $bikeId;

		$queryBuilder = $refuelings->getFilteredQuery ( $dateFrom, $dateTo, $bikeId, $this->getUser () );

		$pagination = $paginator->paginate ( $queryBuilder, $request->query->getInt ( 'page', 1 ), 10 );
		return $this->render ( 'dashboard/refueling/index.html.twig', [ 
				'refuelings' => $pagination
		] );
	}
}
