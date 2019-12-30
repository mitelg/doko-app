<?php declare(strict_types=1);
/*
 * Copyright (c) Michael Telgmann
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mitelg\DokoApp\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
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

    public function __construct(Round $round, Player $player, int $points)
    {
        $this->round = $round;
        $this->player = $player;
        $this->points = $points;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRound(): Round
    {
        return $this->round;
    }

    public function setRound(Round $round): Participant
    {
        $this->round = $round;

        return $this;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): Participant
    {
        $this->player = $player;

        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): Participant
    {
        $this->points = $points;

        return $this;
    }
}
