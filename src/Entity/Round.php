<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) Michael Telgmann
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

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
     *
     * @param int  $points
     * @param bool $isBock
     */
    public function __construct($points, $isBock)
    {
        $this->creationDate = new DateTime();
        $this->points = $points;
        $this->bock = $isBock;
        $this->participants = new ArrayCollection();
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
     * @return bool
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
