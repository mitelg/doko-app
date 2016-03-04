<?php
namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Class Round
 *
 * @ORM\Entity()
 * @ORM\Table(name="round")
 */
class Round
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $points;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    private $bock;

    /**
     * @var ArrayCollection|Participant[]
     *
     * @ORM\OneToMany(targetEntity="Participant", mappedBy="round", cascade={"persist", "remove"})
     */
    private $participants;

    /**
     * Round constructor.
     */
    public function __construct()
    {
        $this->creationDate = null;
        $this->points = 0;
        $this->participants = new ArrayCollection();
        $this->bock = false;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return DateTime
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * @return int
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * @return boolean
     */
    public function isBock()
    {
        return $this->bock;
    }

    /**
     * @return ArrayCollection|Participant[]
     */
    public function getParticipants()
    {
        return $this->participants;
    }

    /**
     * @param ArrayCollection $participants
     */
    public function setParticipants($participants)
    {
        $this->participants = $participants;
    }

    /**
     * @param DateTime $creationDate
     */
    public function setCreationDate(DateTime $creationDate)
    {
        $this->creationDate = $creationDate;
    }

    /**
     * @param int $points
     */
    public function setPoints($points)
    {
        $this->points = $points;
    }

    /**
     * @param bool $bock
     */
    public function setBock($bock)
    {
        $this->bock = $bock;
    }
}