<?php
namespace App\Controller\Workshop;

use App\Entity\Workshop\Workshop;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Sandbox\SecurityError;
use App\Entity\User\User;

class WorkshopCommandController extends AbstractController
{

    /**
     *
     * @Route("/{area}/workshop/addUser", methods={"POST"}, name="addWorkshopUser")
     */
    public function addUser(User $client, Workshop $workshop, $area = "dashboard"): Response
    {
        $user = $this->getUser();
        if ($workshop->getOwner() != $user && ! $user->isAdmin())
            throw new SecurityError("Workshops can only be managed by their owners.");

        $workshop->addClient($client, $user);
        $em = $this->getDoctrine()->getManager();
        $em->persist($workshop);
        $em->flush();
        return $this->render('dashboard/workshop/show.html.twig', [
            'workshop' => $workshop,
            'area' => $area
        ]);
    }
}
