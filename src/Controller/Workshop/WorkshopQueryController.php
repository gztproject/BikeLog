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
use App\Repository\Workshop\WorkshopRepository;

class WorkshopQueryController extends AbstractController
{

    /**
     *
     * @Route("/{area}/workshop", methods={"GET"}, name="workshop_index")
     */
    public function index(Request $request, WorkshopRepository $workshops, PaginatorInterface $paginator, $area = "dashboard"): Response
    {
        $user = $this->getUser();

        $myWorkshops = $area == "admin" ? $workshops->findAll() : $user->getWorkshops();

        $pagination = $paginator->paginate($myWorkshops);
        return $this->render('dashboard/workshop/index.html.twig', [
            'workshops' => $pagination,
            'area' => $area
        ]);
    }

    /**
     * Finds and displays the bike entity.
     *
     * @Route("/{area}/workshop/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="workshop_show")
     */
    public function show(Workshop $workshop, PaginatorInterface $paginator, $area = "dashboard"): Response
    {
        $user = $this->getUser();

        if ($area == "admin" || $workshop->getOwner() == $user) {
            return $this->render('dashboard/workshop/showOwner.html.twig', [
                'workshop' => $workshop
            ]);
        } else {
            if (! $workshop->hasClient($user))
                throw new SecurityError("Workshops can only be shown to their members.");

                return $this->render('dashboard/workshop/show.html.twig', [
                'workshop' => $workshop
            ]);
        }
    }
}
