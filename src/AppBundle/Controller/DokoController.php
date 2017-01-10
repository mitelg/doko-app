<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Participant;
use AppBundle\Entity\Player;
use AppBundle\Entity\Round;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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
        $pointsForm = $this->createPointsForm($this->get('session')->get('playerIds', []));

        $pointsForm->handleRequest($request);

        if ($pointsForm->isSubmitted()) {
            $pointsOfGame = $pointsForm->getData()['points'];
            if ($pointsOfGame > 0) {
                $data = $this->calculateGameResult($pointsForm->getData());
            } else {
                $data = -4;
            }

            // if given data is not valid, throw several form errors and redirect to action again
            if (!is_array($data)) {
                if ($data === -1) {
                    $pointsForm->addError(new FormError('There must be at least one winner'));
                } elseif ($data === -2) {
                    $pointsForm->addError(new FormError('Four winners are one too many'));
                } elseif ($data === -3) {
                    $pointsForm->addError(new FormError('One player is selected twice'));
                } elseif ($data === -4) {
                    $pointsForm->addError(new FormError('The value of points must be at least 1'));
                }

                return $this->render(
                    'index/enter_points.html.twig',
                    ['pointsForm' => $pointsForm->createView()]
                );
            }

            $round = new Round();
            $round->setBock($pointsForm->get('bockRound')->getData());
            $round->setCreationDate(new DateTime());
            $round->setPoints($pointsOfGame);

            $playerIds = [];
            // if data is okay, save points to database
            $participants = new ArrayCollection();
            foreach ($data as $item) {
                $player = $this->getPlayerById($item['playerId']);
                $playerIds[] = $item['playerId'];
                $newPoints = $player->getPoints() + $item['points'];
                $player->setPoints($newPoints);
                $participant = new Participant($round, $player, $item['points']);
                $participants->add($participant);
            }
            $round->setParticipants($participants);
            $this->getEm()->persist($round);
            $this->getEm()->flush();

            $this->get('session')->set('playerIds', $playerIds);

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

        $rounds = $this->getRounds($request);

        return $this->render(
            'index/show_scoreboard.html.twig',
            ['players' => $players, 'rounds' => $rounds]
        );
    }

    /**
     * Show player stats
     *
     * @Route("/playerstats/{playerId}")
     * @param Request $request
     * @param int $playerId
     * @return Response
     */
    public function getPlayerStats(Request $request, $playerId)
    {
        $player = $this->getPlayerById($playerId);

        $rounds = $this->getRoundsByPlayer($player, $request);

        $partners = $this->getPartnersOfPlayer($player);

        return $this->render(
            'index/player_stats.html.twig',
            ['player' => $player, 'rounds' => $rounds, 'partners' => $partners]
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
            $points *= 2;
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

        if (count($winners) === 0) {
            // there is no winner selected
            return -1;
        } elseif (count($winners) === 1) {
            // there is only one winner, so he/she gets more points
            $winners[0]['points'] *= 3;

            return array_merge($winners, $losers);
        } elseif (count($winners) === 2) {
            return array_merge($winners, $losers);
        } elseif (count($winners) === 3) {
            // there is only one loser, so he/she loses more points
            $losers[0]['points'] *= 3;

            return array_merge($winners, $losers);
        } else {
            // all four players can not be winners
            return -2;
        }
    }

    /**
     * @param array $playerIds
     * @return FormInterface
     */
    private function createPointsForm(array $playerIds)
    {
        if (empty($playerIds)) {
            $playerIds = [1,2,3,4];
        }

        $players = $this->getPlayers();
        $playersArray = [];
        foreach ($players as $player) {
            $playersArray[$player->getName()] = $player->getId();
        }

        $pointsForm = $this->createFormBuilder()
            ->add('points', NumberType::class)
            ->add('bockRound', CheckboxType::class, ['required' => false]);

        // create four players
        for ($i = 1; $i <= 4; $i++) {
            $pointsForm->add('player' . $i, ChoiceType::class, ['choices' => $playersArray, 'data' => $playerIds[$i-1]]);
            $pointsForm->add('player' . $i . 'win', CheckboxType::class, ['required' => false]);
        }

        $pointsForm->add('saveAndNew', SubmitType::class, ['label' => 'Save points and enter new'])
            ->add('save', SubmitType::class, ['label' => 'Save points']);

        $pointsForm = $pointsForm->getForm();

        return $pointsForm;
    }

    /**
     * @return EntityManager
     */
    private function getEm()
    {
        if ($this->em === null) {
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
            ->addOrderBy('player.points', 'DESC');

        return $builder->getQuery()->getResult();
    }

    /**
     * @param int $id
     * @return Player
     */
    private function getPlayerById($id)
    {
        $playerRepo = $this->getEm()->getRepository('AppBundle:Player');

        return $playerRepo->find($id);
    }

    /**
     * @param Request $request
     * @return PaginationInterface
     */
    private function getRounds(Request $request)
    {
        $queryBuilder = $this->getEm()->createQueryBuilder()
            ->select(['round'])
            ->from('AppBundle:Round', 'round')
            ->addOrderBy('round.creationDate', 'DESC');

        $query = $queryBuilder->getQuery();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $pagination;
    }

    /**
     * @param Player $player
     * @param Request $request
     * @return PaginationInterface
     */
    private function getRoundsByPlayer(Player $player, Request $request)
    {
        $queryBuilder = $this->getEm()->createQueryBuilder()
            ->select(['round'])
            ->from('AppBundle:Round', 'round')
            ->join('round.participants', 'participant')
            ->andWhere('participant.player = :player')
            ->setParameter('player', $player)
            ->addOrderBy('round.id', 'DESC');

        $query = $queryBuilder->getQuery();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $pagination;
    }

    /**
     * @param Player $player
     * @return array
     */
    private function getPartnersOfPlayer(Player $player)
    {
        $sql = 'SELECT partnerPlayer.name AS name, SUM(participant.points) AS points
                FROM participant
                JOIN participant AS partner ON partner.round_id = participant.round_id
                    AND partner.player_id != :playerId
                    AND participant.points = partner.points
                JOIN player AS partnerPlayer ON partnerPlayer.id = partner.player_id
                WHERE participant.player_id = :playerId
                GROUP BY partner.player_id
                ORDER BY points DESC;';

        return $this->getEm()->getConnection()->executeQuery($sql, ['playerId' => $player->getId()])->fetchAll();
    }
}
