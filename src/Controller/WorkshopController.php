<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class WorkshopController
{
	/**
	 * @Route("/")
	 */
    public function index()
    {
        $number = random_int(0, 100);

        return new Response(
            '<html><body>Index '.$number.'</body></html>'
        );
    }
}