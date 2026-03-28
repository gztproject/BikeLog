<?php

namespace App\Form\ServicePlan;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ServicePlanOverrideCommand
{
    public $task;

    public $interval;

    public $intervalType;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('task', new Assert\NotNull(message: 'servicePlan.task_required'));
        $metadata->addPropertyConstraint('interval', new Assert\NotNull(message: 'servicePlan.interval_required'));
        $metadata->addPropertyConstraint('interval', new Assert\Positive(message: 'servicePlan.interval_positive'));
        $metadata->addPropertyConstraint('intervalType', new Assert\NotNull(message: 'servicePlan.interval_type_required'));
    }
}
