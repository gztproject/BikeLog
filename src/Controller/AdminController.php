<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\Workshop\WorkshopRepository;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{    
    #[Route('/admin', methods: ['GET'], name: 'admin_index')]
    public function index(WorkshopRepository $workshops)
    {   
        $workshops = $workshops->findBy([], []);
        return $this->render('admin/index.html.twig', ['workshops' => $workshops, 'area' => 'admin']);
    }    
    
}
