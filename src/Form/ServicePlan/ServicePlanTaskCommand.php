<?php

namespace App\Form\ServicePlan;

use App\Entity\Task\Task;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ServicePlanTaskCommand
{
    public $existingTask;

    public $part;

    public $name;

    public $description;

    public $interval;

    public $intervalType;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('interval', new Assert\NotNull(message: 'servicePlan.interval_required'));
        $metadata->addPropertyConstraint('interval', new Assert\Positive(message: 'servicePlan.interval_positive'));
        $metadata->addPropertyConstraint('intervalType', new Assert\NotNull(message: 'servicePlan.interval_type_required'));
        $metadata->addConstraint(new Assert\Callback('validateTaskSelection'));
    }

    public function validateTaskSelection(ExecutionContextInterface $context): void
    {
        if ($this->existingTask instanceof Task) {
            return;
        }

        if (trim((string) $this->name) === '') {
            $context->buildViolation('servicePlan.name_required')
                ->atPath('name')
                ->addViolation();
        }

        if (trim((string) $this->description) === '') {
            $context->buildViolation('servicePlan.description_required')
                ->atPath('description')
                ->addViolation();
        }
    }
}
