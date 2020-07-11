<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Bike\Bike;
use App\Entity\Maintenance\CreateMaintenanceCommand;
use App\Form\Maintenance\MaintenanceType;

class MaintenanceCommandController extends AbstractController {

	/**
	 *
	 * @Route("/dashboard/maintenance/new/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET", "POST"}, name="maintenance_new_id")
	 */
	public function new_with_id(Request $request, Bike $bike): Response {
		if ($bike->getOwner () != $this->getUser ())
			throw new SecurityError ( "Bikes can only be shown to their owners." );

		$cmc = new CreateMaintenanceCommand ();
		$cmc->bike = $bike;

		return $this->compileForm ( $cmc, $request );
	}

	/**
	 *
	 * @param CreateMaintenanceCommand $cmc
	 * @param Request $request
	 * @return Response
	 */
	private function compileForm(CreateMaintenanceCommand $cmc, Request $request): Response {
		$form = $this->createForm ( MaintenanceType::class, $cmc, ['user' => $this->getUser()] )->add ( 'saveAndCreateNew', SubmitType::class );

		$form->handleRequest ( $request );

		if ($form->isSubmitted () && $form->isValid ()) {

			$bike = $this->getDoctrine ()->getRepository ( Bike::class )->findOneBy ( [ 
					'id' => $cmc->bike->getId ()
			] );

			$maintenance = $bike->createMaintenance ( $cmc, $this->getUser () );

			$em = $this->getDoctrine ()->getManager ();

			foreach ( $cmc->maintenanceTaskCommands as $cmtc ) {
				$mt = $maintenance->createMaintenanceTask ( $cmtc, $this->getUser () );
				$em->persist ( $mt );
			}

			$em->persist ( $maintenance );
			$em->flush ();

			return $this->redirectToRoute ( 'maintenance_show', array (
					'id' => $maintenance->getId ()
			) );
		}

		return $this->render ( 'dashboard/maintenance/new.html.twig', [ 
				'form' => $form->createView ()
		] );
	}
}