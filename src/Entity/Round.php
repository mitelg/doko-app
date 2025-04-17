<?php

declare(strict_types=1);

/**
 * Copyright (c) Michael Telgmann
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mitelg\DokoApp\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 *
 * @ORM\Table(name="round")
 */
class Round
{
    /**
     * @ORM\Column(type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private int $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private \DateTime $creationDate;

    /**
     * @ORM\Column(type="integer")
     */
    private int $points;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $bock;

    /**
     * @var Participant[]|Collection<array-key, Participant>
     *
     * @ORM\OneToMany(targetEntity="Participant", mappedBy="round", cascade={"persist", "remove"})
     */
    private $participants;

    public function __construct(int $points, bool $isBock)
    {
        $this->creationDate = new \DateTime();
        $this->points = $points;
        $this->bock = $isBock;
        $this->participants = new ArrayCollection([]);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCreationDate(): \DateTime
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTime $creationDate): Round
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): Round
    {
        $this->points = $points;

        return $this;
    }

    public function getBock(): bool
    {
        return $this->bock;
    }

    public function setBock(bool $bock): Round
    {
        $this->bock = $bock;

        return $this;
    }

    /**
     * @return Participant[]|Collection<array-key, Participant>
     */
    public function getParticipants()
    {
        return $this->participants;
    }

    /**
     * @param Participant[]|Collection<array-key, Participant> $participants
     */
    public function setParticipants($participants): Round
    {
        $this->participants = $participants;

        return $this;
    }
}
