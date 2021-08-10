<?php
namespace App\Entity\Bike;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Maintenance\CreateMaintenanceCommand;
use App\Entity\Maintenance\Maintenance;
use App\Entity\Model\Model;
use App\Entity\Task\Task;
use App\Entity\User\User;
use App\Entity\Workshop\Workshop;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use App\Entity\Part\CreatePartCommand;
use App\Entity\Part\Part;
use App\Entity\Part\iHasParts;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Entity\Refueling\Refueling;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\ServiceInterval\ServiceInterval;
use App\Entity\ServiceInterval\iHasServiceIntervals;
use DateTime;
use App\Entity\Task\CreateTaskCommand;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Bike\BikeRepository")
 */
class Bike extends AggregateBase implements iHasServiceIntervals
{

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User\User", inversedBy="bikes")
     */
    private $owner;

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Model\Model")
     */
    private $model;

    /**
     *
     * @ORM\Column(type="string", length=255)
     */
    private $nickname;

    /**
     *
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private $purchasePrice;

    /**
     *
     * @ORM\Column(type="integer")
     */
    private $purchaseOdometer;

    /**
     *
     * @ORM\Column(type="date")
     */
    private $purchaseDate;

    /**
     *
     * @ORM\Column(type="integer")
     */
    private $year;

    /**
     *
     * @ORM\OneToMany(targetEntity="App\Entity\ServiceInterval\ServiceInterval", mappedBy="bike")
     */
    private $customServiceIntervals;

    /**
     *
     * @ORM\Column(type="string", length=50)
     */
    private $vin;

    /**
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Maintenance\Maintenance", mappedBy="bike")
     */
    private $maintenances;

    /**
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Refueling\Refueling", mappedBy="bike", cascade={"persist"})
     */
    private $refuelings;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Entity\Refueling\Refueling")
     */
    private $lastRefueling;

    /**
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $fuelTankSize;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Entity\Maintenance\Maintenance")
     */
    private $lastMaintenance;

    /**
     *
     * @ORM\Column(type="string")
     */
    private $pictureFilename;
   
   
    /**
     *
     * @param CreateBikeCommand $c
     * @param User $user
     * @throws \Exception
     */
    public function __construct(CreateBikeCommand $c, User $user)
    {
        if ($user == null)
            throw new \Exception("Can't create entity without a user.");
        if ($c == null)
            throw new \Exception("Can't create entity without a command.");

        parent::__construct($user);
        $this->nickname = $c->nickname ?? "";
        $this->model = $c->model;
        $this->owner = $user;
        $this->purchasePrice = $c->purchasePrice;
        $this->purchaseDate = $c->purchaseDate;
        $this->year = $c->year;
        $this->customServiceIntervals = new ArrayCollection();
        $this->vin = $c->vin ?? "";
        $this->purchaseOdometer = $c->purchaseOdometer;
        $this->fuelTankSize = $c->fuelTanksize;

        $this->maintenances = new ArrayCollection();
        $this->refuelings = new ArrayCollection();

        $this->pictureFilename = $c->pictureFilename ?? "";
    }

    /**
     *
     * @param UpdateBikeCommand $c
     * @param User $user
     * @throws \Exception
     * @return Bike
     */
    public function update(UpdateBikeCommand $c, User $user): Bike
    {
        throw new \Exception("Not implemented yet.");
        parent::updateBase($user);
        return $this;
    }

    /**
     * Creates a CUSTOM(!) ServiceInterval or overrides it.
     *
     * @param CreateServiceIntervalCommand $c
     * @param User $user
     * @return ServiceInterval
     */
    public function createServiceInterval(CreateServiceIntervalCommand $c, User $user): ServiceInterval
    {
        $csi = new ServiceInterval($c, $this, $user);
        $this->customServiceIntervals->add($csi);
        return $csi;
    }

    /**
     *
     * @param CreateRefuelingCommand $c
     * @param User $user
     * @return Refueling
     */
    public function createRefueling(CreateRefuelingCommand $c, User $user): Refueling
    {
        $refueling = new Refueling($c, $this, $c->isNotBreakingContinuum ? $this->lastRefueling : null, $user);

        // Is this chronologically a newer refueling?
        if ($this->lastRefueling == null || $refueling->getDate() >= $this->lastRefueling->getDate()) {
            if ($this->lastRefueling != null && $refueling->getOdometer() < $this->lastRefueling->getOdometer())
                throw new \Exception("New odometer state (" . $refueling->getOdometer() . ") is smaller 
											than the last known odometer state (" . $this->lastRefueling->getOdometer() . ").");
            $this->lastRefueling = $refueling;
        }
        $this->refuelings->add($refueling);
        return $refueling;
    }

    /**
     *
     * @param CreateMaintenanceCommand $c
     * @param User $user
     * @return Maintenance
     */
    public function createMaintenance(CreateMaintenanceCommand $c, User $user): Maintenance
    {
        $maintenance = new Maintenance($c, $this, $user);
        $this->maintenances->add($maintenance);
        $this->sortMaintenances(null);
        $this->lastMaintenance = $this->maintenances->last();
        return $maintenance;
    }

    
    public function CreateCustomServiceInterval(CreateServiceIntervalCommand $c, User $user): Part
    { 
        $csi = new ServiceInterval($c, $this, $user);
        $this->customServiceIntervals->add($csi);
        return $csi;
    }   

    /*
     * ===============================
     * Getters
     * ===============================
     */

    /**
     *
     * @return string|NULL
     */
    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    /**
     *
     * @return string|NULL
     */
    public function getName(): ?string
    {
        if (trim($this->getNickname()) == "")
            return $this->getModel()->getName() . " " . $this->getModel()->getAlterName();

        return $this->getNickname();
    }

    /**
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     *
     * @return User
     */
    public function getOwner(): User
    {
        return $this->owner;
    }

    /**
     *
     * @return float
     */
    public function getPurchasePrice(): float
    {
        return $this->purchasePrice;
    }

    /**
     *
     * @return DateTime
     */
    public function getPurchaseDate(): DateTime
    {
        return $this->purchaseDate;
    }

    /**
     *
     * @return int
     */
    public function getYear(): int
    {
        return $this->year;
    }

    /**
     *
     * @return Collection
     */
    public function getCustomServiceIntervals(): Collection
    {
        return $this->customServiceIntervals;
    }

    /**
     *
     * @return Collection
     */
    public function getServiceIntervals(): Collection
    {
        $intervals = new ArrayCollection();

        // If we have no overrides
        if ($this->customServiceIntervals->count() == 0)
            return $this->getModel()->getServiceIntervals();

        // Find defaults and overriden defaults
        foreach ($this->getModel()->getServiceIntervals() as $i) {
            $intervals->add($this->customServiceIntervals->matching(new Criteria(new Comparison('task', "=", $i->getTask())))[0] ?? $i);

            // $intervals->add ( $this->customServiceIntervals->filter ( function ($custom) use ($i) {
            // return $custom->getTask () == $i->getTask () ? $custom : $i;
            // } ) [0] );
        }

        // Add custom intervals
        foreach ($this->customServiceIntervals as $i) {
            if ($intervals->matching(new Criteria(new Comparison('task', "=", $i->getTask())))
                ->isEmpty())
                $intervals->add($i);
        }
        return $intervals;
    }

    /**
     *
     * @return string
     */
    public function getVin(): string
    {
        return $this->vin;
    }

    /**
     *
     * @return Collection
     */
    public function getMaintenances(): Collection
    {
        $this->sortMaintenances(null);
        return $this->maintenances;
    }

    /**
     *
     * @return Collection
     */
    public function getFilteredMaintenances(?Workshop $workshop): Collection
    {
        $this->sortMaintenances($workshop);
        return $this->maintenances;
    }

    /**
     *
     * @return Refueling|NULL
     */
    public function getLastRefueling(): ?Refueling
    {
        return $this->lastRefueling;
    }

    /**
     *
     * @return Maintenance|NULL
     */
    public function getLastMaintenance(): ?Maintenance
    {
        return $this->lastMaintenance;
    }

    /**
     *
     * @return int
     */
    public function getOdometer(): int
    {
        return max($this->getPurchaseOdometer(), $this->getLastRefueling() != null ? $this->getLastRefueling()->getOdometer() : 0, $this->getLastMaintenance() != null ? $this->getLastMaintenance()->getOdometer() : 0);
    }

    /**
     *
     * @return int
     */
    public function getPurchaseOdometer(): int
    {
        return $this->purchaseOdometer;
    }

    /**
     *
     * @return int
     */
    public function getTotalDistance(): int
    {
        return $this->getOdometer() - $this->getPurchaseOdometer();
    }

    /**
     *
     * @return Collection
     */
    public function getRefuelings(): Collection
    {
        return $this->refuelings;
    }

    /**
     *
     * @return string
     */
    public function getPictureFilename(): string
    {
        if (trim($this->pictureFilename) == "")
            return $this->getModel()->getPictureFilename();
        return "uploads/bikes/" . $this->pictureFilename;
    }

    /**
     * Returns odometer state when a task was last executed.
     *
     * @param Task $task
     * @return int odometer
     */
    public function getLastTaskOdometer(Task $task): int
    {
        $last = 0;
        foreach ($this->maintenances as $m) {
            if ($m->hasTask($task) && $m->getOdometer() > $last)
                $last = $m->getOdometer();
        }
        return $last;
    }
    
    /**
     * Returns odometer state when a task was last executed.
     *
     * @param Task $task
     * @return int odometer
     */
    public function getLastTaskDate(Task $task): int
    {
        $last = 0;
        foreach ($this->maintenances as $m) {
            if ($m->hasTask($task) && $m->getDateInt() > $last)
                $last = $m->getDateInt();
        }
        return $last;
    }

    /**
     *
     * @return float
     */
    public function getFuelTankSize(): float
    {
        if ($this->fuelTankSize)
            return $this->fuelTankSize;
        return $this->getModel()->getfuelTankSize();
    }

    /**
     *
     * @return float
     */
    public function getNumberOfRefuelings(): float
    {
        return $this->calculateRefuelingStats()["numberOfRefuelings"];
    }

    /**
     *
     * @return float
     */
    public function getTotalFuelQuantity(): float
    {
        return $this->calculateRefuelingStats()["totalFuelQuantity"];
    }

    /**
     *
     * @return float
     */
    public function getTotalFuelPrice(): float
    {
        return $this->calculateRefuelingStats()["totalFuelPrice"];
    }

    /**
     *
     * @return float
     */
    public function getAverageConsumption(): float
    {
        return $this->calculateRefuelingStats()["averageConsumption"];
    }

    /**
     *
     * @return float
     */
    public function getAverageRange(): float
    {
        return $this->calculateRefuelingStats()["averageRange"];
    }

    /**
     *
     * @return array
     */
    private function calculateRefuelingStats(): array
    {
        $n = 0;
        $nValid = 0;
        $consAccu = 0;
        $fuelAccu = 0;
        $priceAccu = 0;
        foreach ($this->refuelings as $r) {
            if ($r->isValid()) {
                $nValid ++;
                $consAccu += $r->getConsumption();
            }
            $n ++;
            $fuelAccu += $r->getFuelQuantity();
            $priceAccu += $r->getPrice();
        }
        $avgCons = $nValid > 0 ? ($consAccu / $nValid) : 0;
        $range = ($this->fuelTankSize / $avgCons) * 100;
        return [
            "numberOfRefuelings" => $n,
            "totalFuelQuantity" => $fuelAccu,
            "totalFuelPrice" => $priceAccu,
            "averageConsumption" => $avgCons,
            "averageRange" => $range
        ];
        // $this->numberOfRefuelings = $n;
        // $this->totalFuelQuantity = $fuelAccu;
        // $this->totalFuelPrice = $priceAccu;
        // $this->averageConsumption = $consAccu / $n;
    }

    /**
     */
    private function sortMaintenances(?Workshop $workshop)
    {
        $iterator = $this->maintenances->getIterator();
        $iterator->uasort(function ($a, $b) {
            return ($a->getDate() < $b->getDate()) ? - 1 : 1;
        });
        $array = iterator_to_array($iterator);
        if ($workshop != null) {
            $array = array_filter($array, function ($a) use ($workshop) {
                return $a->getWorkshop() == $workshop;
            });
        }
        $this->maintenances = new ArrayCollection($array);
    }

    // /**
    // * Returns all of this bike's custom parts.
    // *
    // * @return Collection
    // */
    // public function getCustomParts(): Collection {
    // return $this->parts;
    // }

    // /**
    // * Returns all the generic parts from the model AND all the custom ones.
    // *
    // * {@inheritdoc}
    // * @see \App\Entity\Part\iHasParts::getParts()
    // */
    // public function getParts(): Collection {
    // $parts = new ArrayCollection ();
    // foreach ( $this->getModel ()->getParts () as $p )
    // $parts->add ( $p );
    // foreach ( $this->getCustomParts () as $p )
    // $parts->add ( $p );
    // return $parts;
    // }
}
