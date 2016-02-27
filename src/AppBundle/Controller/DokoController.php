<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Game;
use AppBundle\Entity\Player;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
     * @Route("/showPoints")
     * @return Response
     */
    public function showPointsAction()
    {
        $players = $this->getPlayers();

        return $this->render(
            'index/show_points.html.twig',
            ['players' => $players]
        );
    }

    /**
     * @Route("/enterPoints")
     * @param Request $request
     * @return Response
     */
    public function enterPointsAction(Request $request)
    {
        $players = $this->getPlayers();
        $playersArray = [];
        foreach ($players as $player) {
            $playersArray[$player->getName()] = $player->getId();
        }

        $game = $this->getGame();
        if (empty($game)) {
            $bockRound = false;
        } else {
            if ($game->getBockRounds() > 0) {
                $bockRound = true;
            } else {
                $bockRound = false;
            }
        }

        $pointsForm = $this->createFormBuilder()
            ->add('points', NumberType::class, ['label' => 'Points', 'required' => true])
            ->add('bockRound', CheckboxType::class, ['label' => 'Bock round?', 'required' => false, 'data' => $bockRound])
            ->add('player1', ChoiceType::class, ['label' => 'Player 1', 'choices' => $playersArray, 'required' => true])
            ->add('player1win', CheckboxType::class, ['label' => 'Win?', 'required' => false])
            ->add('player2', ChoiceType::class, ['label' => 'Player 2', 'choices' => $playersArray, 'required' => true])
            ->add('player2win', CheckboxType::class, ['label' => 'Win?', 'required' => false])
            ->add('player3', ChoiceType::class, ['label' => 'Player 3', 'choices' => $playersArray, 'required' => true])
            ->add('player3win', CheckboxType::class, ['label' => 'Win?', 'required' => false])
            ->add('player4', ChoiceType::class, ['label' => 'Player 4', 'choices' => $playersArray, 'required' => true])
            ->add('player4win', CheckboxType::class, ['label' => 'Win?', 'required' => false])
            ->add('save', SubmitType::class, ['label' => 'Save points'])
            ->add('saveAndNew', SubmitType::class, ['label' => 'Save points and enter new'])
            ->getForm();

        $pointsForm->handleRequest($request);

        if ($pointsForm->isValid()) {
            $data = $this->prepareData($pointsForm->getData());

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

            foreach ($data as $item) {
                $player = $this->getPlayerById($item['playerId']);
                $newPoints = $player->getPoints() + $item['points'];
                $player->setPoints($newPoints);
            }

            $this->getEm()->flush();

            $nextAction = $pointsForm->get('save')->isClicked() ? 'app_doko_showpoints' : 'app_doko_enterpoints';

            return $this->redirectToRoute($nextAction);
        }

        return $this->render(
            'index/enter_points.html.twig',
            ['pointsForm' => $pointsForm->createView()]
        );
    }

    /**
     * @Route("/addBockRounds")
     * @param Request $request
     * @return Response
     */
    public function addBockRoundsAction(Request $request)
    {
        $game = $this->getGame();

        $bockForm = $this->createFormBuilder()
            ->add('amount', NumberType::class, ['label' => 'Amount of Bock rounds to add'])
            ->add('save', SubmitType::class, ['label' => 'Add new Bock rounds'])
            ->getForm();

        $bockForm->handleRequest($request);

        if ($bockForm->isValid()) {
            $data = $bockForm->getData();
            $bockRoundsAmount = $data['amount'];
            if (empty($game)) {
                $game = new Game();
                $game->setBockRounds($bockRoundsAmount);
                $this->getEm()->persist($game);
            }

            $newBockRoundsAmount = $game->getBockRounds() + $bockRoundsAmount;
            $game->setBockRounds($newBockRoundsAmount);

            $this->getEm()->flush();

            return $this->redirectToRoute('app_doko_addbockrounds');
        }

        if (empty($game)) {
            $currentBockRounds = 0;
        } else {
            $currentBockRounds = $game->getBockRounds();
        }

        return $this->render(
            'index/add_bock_rounds.html.twig',
            ['bockForm' => $bockForm->createView(), 'currentBockRounds' => $currentBockRounds]
        );
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
        $playerRepo = $this->getEm()->getRepository('AppBundle:Player');

        $players = $playerRepo->findAll();

        return $players;
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
     * @param array $getData
     * @return array|int
     */
    private function prepareData(array $getData)
    {
        $playerIds = [];
        $winners = [];
        $losers = [];
        $points = $points = $getData['points'];

        if ($getData['bockRound']) {
            $points = $points * 2;
            $game = $this->getGame();

            if (!empty($game)) {
                $newBockRounds = $game->getBockRounds() - 1;
                $game->setBockRounds($newBockRounds);
                $this->getEm()->flush();
            }
        }

        for ($i = 1; $i <= 4; $i++) {
            $playerId = $getData['player' . $i];
            if (in_array($playerId, $playerIds)) {
                return -3;
            }
            $playerIds[] = $playerId;

            if ($getData['player' . $i . 'win']) {
                $winners[] = ['playerId' => $playerId, 'points' => $points];
            } else {
                $losers[] = ['playerId' => $getData['player' . $i], 'points' => $points * -1];
            }
        }

        if (count($winners) == 0) {
            return -1;
        } elseif (count($winners) == 1) {
            $winners[0]['points'] = $winners[0]['points'] * 3;

            return array_merge($winners, $losers);
        } elseif (count($winners) == 2) {
            return array_merge($winners, $losers);
        } elseif (count($winners) == 3) {
            $losers[0]['points'] = $losers[0]['points'] * 3;

            return array_merge($winners, $losers);
        } else {
            return -2;
        }
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
}
