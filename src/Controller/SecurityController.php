<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController {
	/**
	 *
	 * @Route("/login", name="app_login")
	 */
	public function login(Request $request, AuthenticationUtils $authenticationUtils): Response {
		$locale = $request->getLocale ();
		// get the login error if there is one
		$error = $authenticationUtils->getLastAuthenticationError ();
		// last username entered by the user
		$lastUsername = $authenticationUtils->getLastUsername ();

		return $this->render ( 'security/login.html.twig', [ 
				'last_username' => $lastUsername,
				'error' => $error,
				'locale' => $locale
		] );
	}

	/**
	 *
	 * @Route("/logout", name="app_logout")
	 */
	public function logout(): void {
		throw new \Exception ( 'This should never be reached!' );
	}

	/**
	 *
	 * @Route("/", methods={"GET"}, name="homepage")
	 */
	public function index(Request $request): Response {
		$locale = $request->getLocale ();
		return $this->render ( 'default/homepage.html.twig', [ 
				'locale' => $locale
		] );
	}
}