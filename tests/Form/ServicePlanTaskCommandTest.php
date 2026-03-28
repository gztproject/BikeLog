<?php

namespace App\Tests\Form;

use App\Form\ServicePlan\ServicePlanTaskCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class ServicePlanTaskCommandTest extends TestCase
{
    public function testAllowsCreatingNewTaskWithoutPart(): void
    {
        $command = new ServicePlanTaskCommand();
        $command->name = 'Annual inspection';
        $command->description = 'General yearly workshop inspection';
        $command->interval = 365;
        $command->intervalType = 20;

        $violations = $this->createValidator()->validate($command);

        self::assertCount(0, $violations);
    }

    public function testStillRequiresNameAndDescriptionForNewTask(): void
    {
        $command = new ServicePlanTaskCommand();
        $command->interval = 365;
        $command->intervalType = 20;

        $violations = $this->createValidator()->validate($command);

        self::assertCount(2, $violations);
        self::assertSame('servicePlan.name_required', $violations->get(0)->getMessage());
        self::assertSame('servicePlan.description_required', $violations->get(1)->getMessage());
    }

    private function createValidator()
    {
        return Validation::createValidatorBuilder()
            ->addMethodMapping('loadValidatorMetadata')
            ->getValidator();
    }
}
