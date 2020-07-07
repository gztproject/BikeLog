<?php

namespace App\Controller\Refueling;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Bike\Bike;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Form\Refueling\RefuelingType;

class RefuelingCommandController extends AbstractController {
	/**
	 *
	 * @Route("/dashboard/refueling/new", methods={"GET", "POST"}, name="refueling_new")
	 */
	public function new(Request $request): Response {
		$crc = new CreateRefuelingCommand ();
		return $this->compileForm ( $crc, $request );
	}

	/**
	 *
	 * @Route("/dashboard/refueling/new/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET", "POST"}, name="refueling_new_id")
	 */
	public function new_with_id(Request $request, Bike $bike): Response {
		if ($bike->getOwner () != $this->getUser ())
			throw new SecurityError ( "Bikes can only be shown to their owners." );

		$crc = new CreateRefuelingCommand ();
		$crc->bike = $bike;

		return $this->compileForm ( $crc, $request );
	}

	/**
	 *
	 * @param CreateRefuelingCommand $crc
	 * @param Request $request
	 * @return Response
	 */
	private function compileForm(CreateRefuelingCommand $crc, Request $request): Response {
		$crc->isTankFull = true;

		$form = $this->createForm ( RefuelingType::class, $crc );

		$form->handleRequest ( $request );

		if ($form->isSubmitted () && $form->isValid ()) {

			$bike = $this->getDoctrine ()->getRepository ( Bike::class )->findOneBy ( [ 
					'id' => $crc->bike->getId ()
			] );

			$refueling = $bike->createRefueling ( $crc, $this->getUser () );

			$em = $this->getDoctrine ()->getManager ();

			$em->persist ( $refueling );
			$em->persist ( $bike );
			$em->flush ();

			return $this->redirectToRoute ( 'refueling_index' );
		}

		return $this->render ( 'dashboard/refueling/new.html.twig', [ 
				'form' => $form->createView ()
		] );
		return $this->render ( 'dashboard/refueling/new.html.twig' );
	}
}
