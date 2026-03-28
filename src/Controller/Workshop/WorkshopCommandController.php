<?php
namespace App\Controller\Workshop;

use App\Entity\Workshop\Workshop;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User\User;
use Doctrine\Persistence\ManagerRegistry;

class WorkshopCommandController extends AbstractController
{
    #[Route('/{area}/workshop/addUser', methods: ['POST'], name: 'addWorkshopUser')]
    public function addUser(User $client, Workshop $workshop, ManagerRegistry $doctrine, $area = "dashboard"): Response
    {
        $user = $this->getUser();
        if ($workshop->getOwner() != $user && ! $user->isAdmin())
            throw $this->createAccessDeniedException("Workshops can only be managed by their owners.");

        $workshop->addClient($client, $user);
        $em = $doctrine->getManager();
        $em->persist($workshop);
        $em->flush();
        return $this->render('dashboard/workshop/show.html.twig', [
            'workshop' => $workshop,
            'area' => $area
        ]);
    }
}
