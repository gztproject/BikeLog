<?php

namespace App\Controller\ServicePlan;

use App\Entity\Bike\Bike;
use App\Entity\Model\Model;
use App\Entity\Part\CreatePartCommand;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\ServiceInterval\ServiceInterval;
use App\Entity\Task\CreateTaskCommand;
use App\Entity\Task\Task;
use App\Form\ServicePlan\ServicePlanOverrideCommand;
use App\Form\ServicePlan\ServicePlanOverrideType;
use App\Form\ServicePlan\ServicePlanPartType;
use App\Form\ServicePlan\ServicePlanTaskCommand;
use App\Form\ServicePlan\ServicePlanTaskType;
use App\Repository\Manufacturer\ManufacturerRepository;
use App\Repository\Part\PartRepository;
use App\Repository\Task\TaskRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ServicePlanController extends AbstractController
{
    #[Route('/dashboard/bike/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/service-plan', methods: ['GET', 'POST'], name: 'bike_service_plan')]
    public function bike(Request $request, Bike $bike, ManagerRegistry $doctrine, FormFactoryInterface $formFactory): Response
    {
        if ($bike->getOwner() != $this->getUser()) {
            throw $this->createAccessDeniedException("Bikes can only be shown to their owners.");
        }

        $overrideCommand = new ServicePlanOverrideCommand();
        $overrideForm = $formFactory->createNamed('bike_override', ServicePlanOverrideType::class, $overrideCommand, [
            'model' => $bike->getModel(),
            'bike' => $bike,
        ]);
        $overrideForm->handleRequest($request);

        if ($overrideForm->isSubmitted() && $overrideForm->isValid()) {
            if ($this->bikeHasCustomIntervalForTask($bike, $overrideCommand->task)) {
                $this->addFlash('warning', 'flash.servicePlan.bikeTaskExists');
            } else {
                $serviceInterval = $bike->createServiceInterval(
                    $this->createServiceIntervalCommand(
                        $overrideCommand->task,
                        (int) $overrideCommand->interval,
                        (int) $overrideCommand->intervalType
                    ),
                    $this->getUser()
                );

                $entityManager = $doctrine->getManager();
                $entityManager->persist($serviceInterval);
                $entityManager->persist($bike);
                $entityManager->flush();

                $this->addFlash('success', 'flash.servicePlan.bikeOverrideCreated');
            }

            return $this->redirectToRoute('bike_service_plan', [
                'id' => $bike->getId(),
            ]);
        }

        return $this->render('dashboard/servicePlan/bike.html.twig', [
            'bike' => $bike,
            'overrideForm' => $overrideForm->createView(),
            'servicePlanGroups' => $this->buildBikeServicePlanGroups($bike),
        ]);
    }

    #[Route('/admin/model/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/service-plan', methods: ['GET', 'POST'], name: 'admin_model_service_plan')]
    public function model(
        Request $request,
        Model $model,
        ManagerRegistry $doctrine,
        ManufacturerRepository $manufacturers,
        PartRepository $parts,
        TaskRepository $tasks,
        FormFactoryInterface $formFactory
    ): Response
    {
        $partCommand = new CreatePartCommand();
        $taskCommand = new ServicePlanTaskCommand();

        $partForm = $formFactory->createNamed('model_part', ServicePlanPartType::class, $partCommand);
        $taskForm = $formFactory->createNamed('model_task', ServicePlanTaskType::class, $taskCommand, [
            'manufacturer' => $model->getManufacturer(),
            'model' => $model,
        ]);

        $partForm->handleRequest($request);
        $taskForm->handleRequest($request);

        $entityManager = $doctrine->getManager();

        if ($partForm->isSubmitted() && $partForm->isValid()) {
            $existingPart = $parts->findOneForManufacturerByName($model->getManufacturer(), $partCommand->name);

            if ($existingPart != null) {
                $this->addFlash('warning', 'flash.servicePlan.partExists');
            } else {
                $part = $model->getManufacturer()->createPart($partCommand, $this->getUser());
                $entityManager->persist($part);
                $entityManager->persist($model->getManufacturer());
                $entityManager->flush();

                $this->addFlash('success', 'flash.servicePlan.partCreated');
            }

            return $this->redirectToRoute('admin_model_service_plan', [
                'id' => $model->getId(),
            ]);
        }

        if ($taskForm->isSubmitted() && $taskForm->isValid()) {
            $task = $this->resolveTaskForServicePlan($taskCommand, $model, $tasks);

            if ($this->modelHasIntervalForTask($model, $task)) {
                $this->addFlash('warning', 'flash.servicePlan.modelTaskExists');
            } else {
                $serviceInterval = $model->createServiceInterval(
                    $this->createServiceIntervalCommand(
                        $task,
                        (int) $taskCommand->interval,
                        (int) $taskCommand->intervalType
                    ),
                    $this->getUser()
                );

                $entityManager->persist($task);
                $entityManager->persist($serviceInterval);
                $entityManager->persist($model);
                $entityManager->flush();

                $this->addFlash('success', 'flash.servicePlan.taskCreated');
            }

            return $this->redirectToRoute('admin_model_service_plan', [
                'id' => $model->getId(),
            ]);
        }

        return $this->render('admin/servicePlan/model.html.twig', [
            'model' => $model,
            'availableManufacturers' => $this->getSortedManufacturers($manufacturers->findAll()),
            'availableModels' => $this->getSortedModels($manufacturers->findAll()),
            'partForm' => $partForm->createView(),
            'taskForm' => $taskForm->createView(),
            'servicePlanGroups' => $this->buildServicePlanGroups($model->getServiceIntervals()->toArray()),
        ]);
    }

    /**
     *
     * @param array $manufacturers
     * @return array
     */
    private function getSortedManufacturers(array $manufacturers): array
    {
        usort($manufacturers, static function ($left, $right): int {
            return strcmp($left->getName(), $right->getName());
        });

        return $manufacturers;
    }

    /**
     *
     * @param array $manufacturers
     * @return array
     */
    private function getSortedModels(array $manufacturers): array
    {
        $models = [];

        foreach ($manufacturers as $manufacturer) {
            foreach ($manufacturer->getModels() as $model) {
                $models[] = $model;
            }
        }

        usort($models, static function (Model $left, Model $right): int {
            $leftManufacturer = $left->getManufacturer()->getName();
            $rightManufacturer = $right->getManufacturer()->getName();

            if ($leftManufacturer !== $rightManufacturer) {
                return strcmp($leftManufacturer, $rightManufacturer);
            }

            $leftName = trim($left->getName() . ' ' . $left->getAlterName());
            $rightName = trim($right->getName() . ' ' . $right->getAlterName());

            if ($leftName !== $rightName) {
                return strcmp($leftName, $rightName);
            }

            if ($left->getYearFrom() !== $right->getYearFrom()) {
                return $left->getYearFrom() <=> $right->getYearFrom();
            }

            return $left->getYearTo() <=> $right->getYearTo();
        });

        return $models;
    }

    /**
     *
     * @param Bike $bike
     * @return array
     */
    private function buildBikeServicePlanGroups(Bike $bike): array
    {
        $sourceByTaskId = [];
        foreach ($bike->getCustomServiceIntervals() as $customInterval) {
            $sourceByTaskId[$customInterval->getTask()->getId()->toString()] = 'bike';
        }

        $statusByTaskId = [];
        foreach ($bike->getServiceIntervalStatuses() as $status) {
            $statusByTaskId[$status['task']->getId()->toString()] = $status;
        }

        foreach ($bike->getServiceIntervals() as $serviceInterval) {
            $taskId = $serviceInterval->getTask()->getId()->toString();
            if (! array_key_exists($taskId, $sourceByTaskId)) {
                $sourceByTaskId[$taskId] = 'model';
            }
        }

        return $this->buildServicePlanGroups($bike->getServiceIntervals()->toArray(), $sourceByTaskId, $statusByTaskId);
    }

    /**
     *
     * @param array $serviceIntervals
     * @param array $sourceByTaskId
     * @param array $statusByTaskId
     * @return array
     */
    private function buildServicePlanGroups(array $serviceIntervals, array $sourceByTaskId = [], array $statusByTaskId = []): array
    {
        $groups = [];

        foreach ($serviceIntervals as $serviceInterval) {
            $task = $serviceInterval->getTask();
            $part = $task->getPart();
            $groupKey = $part != null ? $part->getId()->toString() : 'general';

            if (! array_key_exists($groupKey, $groups)) {
                $groups[$groupKey] = [
                    'name' => $part != null ? $part->getName() : 'General',
                    'translationKey' => $part != null ? null : 'label.general',
                    'entries' => [],
                ];
            }

            $taskId = $task->getId()->toString();
            $groups[$groupKey]['entries'][] = [
                'task' => $task,
                'serviceInterval' => $serviceInterval,
                'source' => $sourceByTaskId[$taskId] ?? null,
                'status' => $statusByTaskId[$taskId] ?? null,
            ];
        }

        foreach ($groups as &$group) {
            usort($group['entries'], static function (array $left, array $right): int {
                return strcmp($left['task']->getName(), $right['task']->getName());
            });
        }
        unset($group);

        uasort($groups, static function (array $left, array $right): int {
            return strcmp($left['name'], $right['name']);
        });

        return array_values($groups);
    }

    /**
     *
     * @param ServicePlanTaskCommand $command
     * @param Model $model
     * @param TaskRepository $tasks
     * @return Task
     */
    private function resolveTaskForServicePlan(ServicePlanTaskCommand $command, Model $model, TaskRepository $tasks): Task
    {
        if ($command->existingTask instanceof Task) {
            return $command->existingTask;
        }

        $existingTask = $tasks->findOneForPartByName($command->part, $command->name);
        if ($existingTask != null) {
            return $existingTask;
        }

        $createTaskCommand = new CreateTaskCommand();
        $createTaskCommand->part = $command->part;
        $createTaskCommand->name = trim((string) $command->name);
        $createTaskCommand->description = trim((string) $command->description);
        $createTaskCommand->comment = '';

        return $model->createTask($createTaskCommand, $this->getUser());
    }

    /**
     *
     * @param Task $task
     * @param int $interval
     * @param int $intervalType
     * @return CreateServiceIntervalCommand
     */
    private function createServiceIntervalCommand(Task $task, int $interval, int $intervalType): CreateServiceIntervalCommand
    {
        $command = new CreateServiceIntervalCommand();
        $command->task = $task;
        $command->interval = $interval;
        $command->intervalType = $intervalType;

        return $command;
    }

    /**
     *
     * @param Bike $bike
     * @param Task $task
     * @return bool
     */
    private function bikeHasCustomIntervalForTask(Bike $bike, Task $task): bool
    {
        foreach ($bike->getCustomServiceIntervals() as $serviceInterval) {
            if ($serviceInterval->getTask() == $task) {
                return true;
            }
        }

        return false;
    }

    /**
     *
     * @param Model $model
     * @param Task $task
     * @return bool
     */
    private function modelHasIntervalForTask(Model $model, Task $task): bool
    {
        foreach ($model->getServiceIntervals() as $serviceInterval) {
            if ($serviceInterval->getTask() == $task) {
                return true;
            }
        }

        return false;
    }
}
