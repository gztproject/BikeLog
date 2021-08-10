<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception;
use App\Entity\User\User;

class UpdateController extends AbstractController
{    
    /**         
     * @Route("/admin/update", methods={"GET"}, name="admin_update")
     */
    public function index()
    {   
        $user = $this->getUser();
        if(!$user->getIsRoleAdmin())
            throw new AccessDeniedHttpException(); 
        return $this->render('admin/update.html.twig');
    }    
    
}