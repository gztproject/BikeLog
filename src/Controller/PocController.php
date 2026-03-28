<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class PocController extends AbstractController
{
    #[Route('/dashboard/poc', methods: ['GET'], name: 'poc_index')]
    public function index(): StreamedResponse
    {
        return new StreamedResponse(static function (): void {
            while (@ob_end_flush()) {
            }

            $proc = popen('./test.sh', 'r');
            if ($proc === false) {
                echo 'Unable to start process.';

                return;
            }

            echo '<pre>';
            while (!feof($proc)) {
                echo fread($proc, 4096);
                @flush();
            }
            pclose($proc);
            echo '</pre>';
        });
    }
    
}
