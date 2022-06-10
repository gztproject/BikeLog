<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
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
        return $this->render('admin/update.html.twig');
    }
    
    /**
     * @Route("/admin/update/check", methods={"GET"}, name="admin_update_check")
     */
    public function checkForUpdates(VersionManagerInterface $manager)
    {          
        $currentVersion = $manager->getVersion();
        
        return $this->json(['current_version' => $currentVersion]);
    }
    
    /**
     * @Route("/admin/update/do", methods={"GET"}, name="admin_do_update")
     */
    public function doUpdate(Request $request, VersionManagerInterface $manager)
    {
        $version = $request->query->get('version');
        $matches = [];
        preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches);
        
        $version = $matches[0];
        $maj = $matches[1];
        $min = $matches[2];
        $rev = $matches[3];
        
        $out = shell_exec("./../Scripts/update.sh -v $version");
        return $this->json(['result' => $out]);
    }
    
}