<?php

namespace App\Controller\Refueling;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Bike\Bike;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Form\Refueling\RefuelingType;
use Doctrine\Persistence\ManagerRegistry;

class RefuelingCommandController extends AbstractController {
	/**
	 *
	 * @Route("/dashboard/refueling/new", methods={"GET", "POST"}, name="refueling_new")
	 */
    public function new(Request $request, ManagerRegistry $doctrine): Response {
		$crc = new CreateRefuelingCommand ();
		return $this->compileForm ( $crc, $request, $doctrine );
	}

	/**
	 *
	 * @Route("/dashboard/refueling/new/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET", "POST"}, name="refueling_new_id")
	 */
	public function new_with_id(Request $request, Bike $bike, ManagerRegistry $doctrine): Response {
		if ($bike->getOwner () != $this->getUser ())
			throw new SecurityError ( "Bikes can only be shown to their owners." );

		$crc = new CreateRefuelingCommand ();
		$crc->bike = $bike;

		return $this->compileForm ( $crc, $request, $doctrine );
	}

	/**
	 *
	 * @param CreateRefuelingCommand $crc
	 * @param Request $request
	 * @return Response
	 */
	private function compileForm(CreateRefuelingCommand $crc, Request $request, ManagerRegistry $doctrine): Response {
		$crc->isTankFull = true;
		$crc->isNotBreakingContinuum = true;

		$form = $this->createForm ( RefuelingType::class, $crc, ['user' => $this->getUser()] );

		$form->handleRequest ( $request );

		if ($form->isSubmitted () && $form->isValid ()) {

			$bike = $doctrine->getRepository ( Bike::class )->findOneBy ( [ 
					'id' => $crc->bike->getId ()
			] );

			$refueling = $bike->createRefueling ( $crc, $this->getUser () );

			$em = $doctrine->getManager ();

			// If it's older we should find where it belongs and check the odometer
			if ($bike->getLastRefueling() != null && $refueling->getDate () < $bike->getLastRefueling ()->getDate ()) {
				// Find the previous and next refueling
				$previous = null;
				$next = $bike->getLastRefueling ();
				foreach ( $bike->getRefuelings () as $r ) {
					if ($r->getDate () > $refueling->getDate ()) {
						if ($next->getDate () > $r->getDate () && $r != $refueling)
							$next = $r;
						continue;
					} else {
						if (($previous == null || ($previous->getDate () < $r->getDate ())) && $r != $refueling)
							$previous = $r;
						continue;
					}
				}

				// first we have to dereference old (and wrong) previous refuelings
				$refueling->setPreviousRefueling ( null, $this->getUser () );
				$next->setPreviousRefueling ( null, $this->getUser () );
				$em->persist ( $refueling );
				$em->persist ( $next );
				$em->flush ();

				// Just assuming that there was nothing in between this and the next existing refueling
				$next->setPreviousRefueling ( $refueling, $this->getUser () );
				if ($crc->isNotBreakingContinuum)
					$refueling->setPreviousRefueling ( $previous, $this->getUser () );
				$em->persist ( $refueling );
				$em->flush ();
				$em->persist ( $next );
			} // In normal case just persist the new refueling and updated bike's reference.
			else {
				$em->persist ( $refueling );
				$em->persist ( $bike );
			}
			$em->flush ();

			return $this->redirectToRoute ( 'refueling_index', ["bike"=>$bike->getId()] );
		}

		return $this->render ( 'dashboard/refueling/new.html.twig', [ 
				'form' => $form->createView ()
		] );
		return $this->render ( 'dashboard/refueling/new.html.twig' );
	}
}
