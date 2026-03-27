<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class PocController extends AbstractController
{
    /**
     * @Route("/dashboard/poc", methods={"GET"}, name="poc_index")
     */
    public function index(): StreamedResponse
    {
        while (@ ob_end_flush());
        
        $cmd = './test.sh';
        
        $proc = popen($cmd, 'r');
        echo '<pre>';
        while (!feof($proc))
        {
            echo fread($proc, 4096);
            @ flush();
        }
        echo '</pre>';
        
        return "OK";
        
        return $this->render ( 'dashboard/index.html.twig');
    }
    
}

