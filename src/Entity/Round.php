<?php declare(strict_types=1);
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

    public function __construct(int $points, bool $isBock)
    {
        $this->creationDate = new DateTime();
        $this->points = $points;
        $this->bock = $isBock;
        $this->participants = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): Round
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
     * @return Participant[]|ArrayCollection
     */
    public function getParticipants()
    {
        return $this->participants;
    }

    /**
     * @param Participant[]|ArrayCollection $participants
     */
    public function setParticipants(ArrayCollection $participants): Round
    {
        $this->participants = $participants;

        return $this;
    }
}
