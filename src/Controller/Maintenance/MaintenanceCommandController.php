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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\Persistence\ManagerRegistry;

class MaintenanceCommandController extends AbstractController {

    /**
     *
     * @Route("/dashboard/maintenance/new", methods={"GET", "POST"}, name="maintenance_new")
     */
    public function new(Request $request): Response {
        throw new BadRequestHttpException("Maintenance has to be on a specific bike.");
    }
    
	/**
	 *
	 * @Route("/dashboard/maintenance/new/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET", "POST"}, name="maintenance_new_id")
	 */
    public function new_with_id(Request $request, Bike $bike, ManagerRegistry $doctrine): Response {
		if ($bike->getOwner () != $this->getUser ())
			throw new SecurityError ( "Bikes can only be shown to their owners." );

		$cmc = new CreateMaintenanceCommand ();
		$cmc->bike = $bike;

		return $this->compileForm ( $cmc, $request, $doctrine );
	}

	/**
	 *
	 * @param CreateMaintenanceCommand $cmc
	 * @param Request $request
	 * @return Response
	 */
	private function compileForm(CreateMaintenanceCommand $cmc, Request $request, ManagerRegistry $doctrine): Response {	    
		$form = $this->createForm ( MaintenanceType::class, $cmc, [ 
				'user' => $this->getUser(),
				'bike' => $cmc->bike,
				'model' => $cmc->bike->getModel()
		] )->add ( 'saveAndCreateNew', SubmitType::class );

		$form->handleRequest ( $request );

		if ($form->isSubmitted () && $form->isValid ()) {

			$bike = $doctrine->getRepository ( Bike::class )->findOneBy ( [ 
					'id' => $cmc->bike->getId ()
			] );

			$maintenance = $bike->createMaintenance ( $cmc, $this->getUser () );

			$em = $doctrine->getManager ();

			foreach ( $cmc->maintenanceTaskCommands as $cmtc ) {
				$mt = $maintenance->createMaintenanceTask ( $cmtc, $this->getUser () );
				$em->persist ( $mt );
			}

			$em->persist ( $maintenance );
			$em->persist ( $bike );
			$em->flush ();

			return $this->redirectToRoute ( 'maintenance_show', array (
					'id' => $maintenance->getId ()
			) );
		}

		return $this->render ( 'dashboard/maintenance/new.html.twig', [ 
				'form' => $form->createView (),
				'showBikeSelecor' => $cmc->bike == null
		] );
	}
}