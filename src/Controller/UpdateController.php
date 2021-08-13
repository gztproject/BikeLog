<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception;
use App\Entity\User\User;
use Shivas\VersioningBundle\Service\VersionManagerInterface;

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
    
    /**
     * @Route("/admin/update/check", methods={"GET"}, name="admin_update_check")
     */
    public function checkForUpdates(VersionManagerInterface $manager)
    {
        $user = $this->getUser();
        if(!$user->getIsRoleAdmin())
            throw new AccessDeniedHttpException();
        
        $currentVersion = $manager->getVersion();
        
        return $this->json(['current_version' => $currentVersion]);
    }
    
    /**
     * @Route("/admin/update/do", methods={"GET"}, name="admin_do_update")
     */
    public function doUpdate(Request $request, VersionManagerInterface $manager)
    {
        $user = $this->getUser();
        if(!$user->getIsRoleAdmin())
            throw new AccessDeniedHttpException();
            
            $currentVersion = $manager->getVersion();
            
            $url = $request->query->get('url');
            
            return $this->json(['url' => $url]);
    }
    
}