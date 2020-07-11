<?php 
namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Maintenance\Maintenance;
use App\Repository\Maintenance\MaintenanceRepository;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Knp\Component\Pager\PaginatorInterface;

class MaintenanceQueryController extends AbstractController
{    
    /**
     * @Route("/dashboard/maintenance", methods={"GET"}, name="maintenance_index")
     */
	public function index(MaintenanceRepository $maintenances, Request $request, PaginatorInterface $paginator): Response
    {   		
    	$queryBuilder = $maintenances->getQuery();
    	
    	$pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);
    	
    	return $this->render('dashboard/maintenance/index.html.twig', [
    			'pagination' => $pagination,    			
    	]);
    } 
    
        
    /**
     * @Route("/dashboard/maintenance/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/show", methods={"GET"}, name="maintenance_show")
     */
    public function show(Maintenance $maintenance): Response
    {
    	return $this->render('dashboard/maintenance/show.html.twig', [
    			'maintenance' => $maintenance
    	]);    	    	
    }
}