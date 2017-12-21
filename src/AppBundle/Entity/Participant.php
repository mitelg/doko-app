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

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Participant
 *
 * @ORM\Entity()
 * @ORM\Table(name="participant")
 */
class Participant
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
     * @var Round
     *
     * @ORM\ManyToOne(targetEntity="Round", inversedBy="participants")
     */
    private $round;

    /**
     * @var Player
     *
     * @ORM\ManyToOne(targetEntity="Player")
     */
    private $player;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    private $points;

    /**
     * Participant constructor.
     *
     * @param Round  $round
     * @param Player $player
     * @param int $points
     */
    public function __construct(Round $round, Player $player, $points)
    {
        $this->round = $round;
        $this->player = $player;
        $this->points = $points;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Round
     */
    public function getRound()
    {
        return $this->round;
    }

    /**
     * @return Player
     */
    public function getPlayer()
    {
        return $this->player;
    }

    /**
     * @return int
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * @param int $points
     */
    public function setPoints($points)
    {
        $this->points = $points;
    }
}
