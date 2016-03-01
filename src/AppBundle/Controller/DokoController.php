<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Game;
use AppBundle\Entity\Participant;
use AppBundle\Entity\Player;
use AppBundle\Entity\Round;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DokoController extends Controller
{
    /**
     * @var EntityManager $em
     */
    private $em;

    /**
     * @Route("/")
     * @return Response
     */
    public function indexAction()
    {
        return $this->render('index/index.html.twig');
    }

    /**
     * creates a new player
     *
     * @Route("/createPlayer")
     * @param Request $request
     * @return Response
     */
    public function createPlayerAction(Request $request)
    {
        $player = new Player();

        $playerForm = $this->createFormBuilder($player)
            ->add('name', TextType::class)
            ->add('save', SubmitType::class, ['label' => 'Create Player'])
            ->getForm();

        $playerForm->handleRequest($request);

        if ($playerForm->isValid()) {
            $this->getEm()->persist($player);
            $this->getEm()->flush();

            return $this->redirectToRoute('app_doko_index');
        }

        return $this->render(
            'index/create_player.html.twig',
            ['playerForm' => $playerForm->createView()]
        );
    }

    /**
     * enter a new game with its points
     *
     * @Route("/enterPoints")
     * @param Request $request
     * @return Response
     */
    public function enterPointsAction(Request $request)
    {
        $pointsForm = $this->createPointsForm();

        $pointsForm->handleRequest($request);

        if ($pointsForm->isValid()) {
            $data = $this->calculateGameResult($pointsForm->getData());

            // if given data is not valid, throw several form errors and redirect to action again
            if (!is_array($data)) {
                if ($data == -1) {
                    $error = new FormError('There must be at least one winner');
                } elseif ($data == -2) {
                    $error = new FormError('Four winners are one too many');
                } else {
                    $error = new FormError('One player is selected twice');
                }

                $pointsForm->addError($error);

                return $this->render(
                    'index/enter_points.html.twig',
                    ['pointsForm' => $pointsForm->createView()]
                );
            }

            $round = new Round();
            $round->setBock($pointsForm->get('bockRound')->getData());
            $round->setCreationDate(new DateTime());

            // if data is okay, save points to database
            $participants = new ArrayCollection();
            foreach ($data as $item) {
                $points = $item['points'];
                if ($round->isBock()) {
                    $points *= 2;
                }
                $round->setPoints(abs($points));
                $player = $this->getPlayerById($item['playerId']);
                $participant = new Participant($round, $player, $points);
                $participants->add($participant);
            }
            $round->setParticipants($participants);
            $this->getEm()->persist($round);
            $this->getEm()->flush();



            $nextAction = $pointsForm->get('save')->isClicked() ? 'app_doko_showscoreboard' : 'app_doko_enterpoints';

            return $this->redirectToRoute($nextAction);
        }

        return $this->render(
            'index/enter_points.html.twig',
            ['pointsForm' => $pointsForm->createView()]
        );
    }

    /**
     * Show scoreboard
     *
     * @Route("/showScoreboard")
     * @param Request $request
     * @return Response
     */
    public function showScoreboardAction(Request $request)
    {
        $players = $this->getPlayers();

        $rounds = $this->getRounds();

        foreach ($players as $player) {
            $player->setPoints($this->getCurrentPoints($player));
        }

        foreach ($rounds as $round) {
            foreach ($round->getParticipants() as $participant) {
                $participant->setPoints($this->getCurrentPoints($participant->getPlayer(), $round));
            }
        }

        return $this->render(
            'index/show_scoreboard.html.twig',
            ['players' => $players, 'rounds' => $rounds]
        );
    }

    /**
     * calculate the game result before saving it into the database
     * checks for winners and losers
     * also checks if given data is valid
     *
     * @param array $formData
     * @return array|int
     */
    private function calculateGameResult(array $formData)
    {
        $playerIds = [];
        $winners = [];
        $losers = [];
        $points = $formData['points'];

        // double the points if Bock round
        if ($formData['bockRound']) {
            $points = $points * 2;
            $game = $this->getGame();

            if (!empty($game)) {
                $newBockRounds = $game->getBockRounds() - 1;
                $game->setBockRounds($newBockRounds);
                $this->getEm()->flush();
            }
        }

        // separate the four players into winners and losers
        for ($i = 1; $i <= 4; $i++) {
            $playerId = $formData['player' . $i];
            if (in_array($playerId, $playerIds)) {
                // if one player is selected twice, throw error
                return -3;
            }
            $playerIds[] = $playerId;

            if ($formData['player' . $i . 'win']) {
                $winners[] = ['playerId' => $playerId, 'points' => $points];
            } else {
                $losers[] = ['playerId' => $formData['player' . $i], 'points' => $points * -1];
            }
        }

        if (count($winners) == 0) {
            // there is no winner selected
            return -1;
        } elseif (count($winners) == 1) {
            // there is only one winner, so he/she gets more points
            $winners[0]['points'] = $winners[0]['points'] * 3;

            return array_merge($winners, $losers);
        } elseif (count($winners) == 2) {
            return array_merge($winners, $losers);
        } elseif (count($winners) == 3) {
            // there is only one loser, so he/she loses more points
            $losers[0]['points'] = $losers[0]['points'] * 3;

            return array_merge($winners, $losers);
        } else {
            // all four players can not be winners
            return -2;
        }
    }

    /**
     * @return Form
     */
    private function createPointsForm()
    {
        $players = $this->getPlayers();
        $playersArray = [];
        foreach ($players as $player) {
            $playersArray[$player->getName()] = $player->getId();
        }

        // check for bock rounds, if set points get doubled
        $game = $this->getGame();
        if (!empty($game) && $game->getBockRounds() > 0) {
            $bockRound = true;
        } else {
            $bockRound = false;
        }

        $pointsForm = $this->createFormBuilder()
            ->add('points', NumberType::class, ['label' => 'Points', 'required' => true])
            ->add('bockRound', CheckboxType::class, [
                'label' => 'Bock round?',
                'required' => false,
                'data' => $bockRound
            ]);

        // create four players
        for ($i = 1; $i <= 4; $i++) {
            $pointsForm->add('player' . $i, ChoiceType::class, [
                'label' => 'Player ' . $i,
                'choices' => $playersArray,
                'required' => true
            ]);
            $pointsForm->add('player' . $i . 'win', CheckboxType::class, ['label' => 'Win?', 'required' => false]);
        }

        $pointsForm->add('save', SubmitType::class, ['label' => 'Save points'])
            ->add('saveAndNew', SubmitType::class, ['label' => 'Save points and enter new']);

        $pointsForm = $pointsForm->getForm();

        return $pointsForm;
    }

    /**
     * @return EntityManager
     */
    private function getEm()
    {
        if ($this->em == null) {
            $this->em = $this->getDoctrine()->getManager();

            return $this->em;
        }

        return $this->em;
    }

    /**
     * @return Player[]
     */
    private function getPlayers()
    {
        $builder = $this->getEm()->createQueryBuilder()
            ->select(['player'])
            ->from('AppBundle:Player', 'player')
            ->addOrderBy('player.name', 'ASC');

        return $builder->getQuery()->getResult();
    }

    /**
     * @param int $id
     * @return Player
     */
    private function getPlayerById($id)
    {
        $playerRepo = $this->getEm()->getRepository('AppBundle:Player');

        $player = $playerRepo->find($id);

        return $player;
    }

    /**
     * @return Game|array
     */
    private function getGame()
    {
        $gameRepo = $this->getEm()->getRepository('AppBundle:Game');
        $game = $gameRepo->findAll();

        if (empty($game)) {
            return $game;
        } else {
            return $game[0];
        }
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return array|Round[]
     */
    private function getRounds($limit = 10, $offset = 0)
    {
        $queryBuilder = $this->getEm()->createQueryBuilder()
            ->select(['round'])
            ->from('AppBundle:Round', 'round')
            ->orderBy('round.creationDate', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $rounds = $queryBuilder->getQuery()->getResult();

        return array_reverse($rounds);
    }

    /**
     * @param Player $player
     * @param Round|null $round
     * @return int
     */
    private function getCurrentPoints(Player $player, Round $round = null)
    {
        $queryBuilder = $this->getEm()->createQueryBuilder()
            ->select('SUM(participant.points)')
            ->from('AppBundle:Participant', 'participant')
            ->join('participant.round', 'round')
            ->andWhere('participant.player = :player')
            ->setParameter('player', $player);

        if (is_object($round)) {
            $queryBuilder->andWhere('round.creationDate <= :currentRoundCreationDate')
                ->setParameter('currentRoundCreationDate', $round->getCreationDate());
        }

        $points = $queryBuilder->getQuery()->getSingleScalarResult();

        if (is_null($points)) {
            return 0;
        }

        return $points;
    }
}
