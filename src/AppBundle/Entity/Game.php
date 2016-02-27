<?php
namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Player
 *
 * @ORM\Entity()
 * @ORM\Table(name="game")
 */
class Game
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var int
     *
     * @ORM\Column(name="bock_rounds", type="integer")
     */
    protected $bockRounds = 0;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set bockRounds
     *
     * @param integer $bockRounds
     *
     * @return Game
     */
    public function setBockRounds($bockRounds)
    {
        $this->bockRounds = $bockRounds;

        return $this;
    }

    /**
     * Get bockRounds
     *
     * @return integer
     */
    public function getBockRounds()
    {
        return $this->bockRounds;
    }
}
