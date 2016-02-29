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
     * shows points of all players
     *
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

            // if data is okay, save points to database
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
     * add new Bock rounds
     *
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
            // create new game, if there is non
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
