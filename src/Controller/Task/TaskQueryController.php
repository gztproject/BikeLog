<?php
namespace App\Controller\Task;

use App\Repository\Bike\BikeRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\Task\TaskRepository;
use Symfony\Component\Routing\Attribute\Route;

class TaskQueryController extends AbstractController
{
    #[Route('/{area}/task', methods: ['GET'], name: 'tasks_index')]
    public function index(TaskRepository $tasks, BikeRepository $bikes, Request $request, PaginatorInterface $paginator, $area = "dashboard"): Response
    {
        $bikeId = $request->query->get('bike', null);
        $bikeId = $bikeId === "" ? null : $bikeId;
        $bike = null;
        if ($bikeId) {
            $bike = $bikes->findOneBy([
                'id' => $bikeId
            ]);
            if ($bike->getOwner() != $this->getUser())
                throw $this->createAccessDeniedException("Bikes can only be shown to their owners.");
        }

        $myTasksDto = [];
        if ($bike != null)
            $myTasksDto[] = [
                "tasks" => $tasks->getBikeTasks($bike->getId()
                    ->toString(), $bike->getModel()
                    ->getId()
                    ->toString()),
                "bike" => $bike
            ];
        else if ($area == "admin") {
            foreach ($bikes->findAll() as $curBike) {
                $myTasksDto[] = [
                    "tasks" => $tasks->getBikeTasks($curBike->getId()
                        ->toString(), $curBike->getModel()
                        ->getId()
                        ->toString()),
                    "bike" => $curBike
                ];
            }
        }

        $pagination = $paginator->paginate($myTasksDto, $request->query->getInt('page', 1), 10);
        return $this->render('dashboard/task/index.html.twig', [
            'tasksDto' => $pagination,            
            'area' => $area
        ]);
    }
}
