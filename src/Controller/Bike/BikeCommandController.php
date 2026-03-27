<?php

namespace App\Controller\Bike;

use App\Entity\Bike\CreateBikeCommand;
use App\Form\Bike\BikeType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;

class BikeCommandController extends AbstractController {
	/**
	 *
	 * @Route("/dashboard/bike/new", methods={"GET", "POST"}, name="bike_new")
	 */
    public function new(Request $request, ManagerRegistry $doctrine): Response {
		$cbc = new CreateBikeCommand ();
		$cbc->owner = $this->getUser ();
		$form = $this->createForm ( BikeType::class, $cbc );

		$form->handleRequest ( $request );

		if ($form->isSubmitted () && $form->isValid ()) {
			
			//Handle the file stuff
			$pictureFile = $form['picture']->getData();
			if($pictureFile)
			{
				$originalFilename = pathinfo($pictureFile->getClientOriginalName(), PATHINFO_FILENAME);
				// this is needed to safely include the file name as part of the URL
				$safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
				$newFilename = $safeFilename.'-'.uniqid().'.'.$pictureFile->guessExtension();
				
				// Move the file to the directory where brochures are stored
				try {
					$pictureFile->move(
							$this->getParameter('bike_pictures_directory'),
							$newFilename
							);
				} catch (FileException $e) {
					// ... handle exception if something happens during file upload
				}
				$cbc->pictureFilename = $newFilename;
			}

			$bike = $this->getUser ()->createBike ( $cbc );

			$em = $doctrine->getManager ();

			$em->persist ( $bike );
			$em->flush ();

			return $this->redirectToRoute ( 'bike_show', array (
					'id' => $bike->getId ()
			) );
		}

		return $this->render ( 'dashboard/bike/new.html.twig', [ 
				'form' => $form->createView ()
		] );
	}
}
