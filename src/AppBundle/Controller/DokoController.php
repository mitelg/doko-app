<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Player;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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

        $form = $this->createFormBuilder($player)
            ->add('name', TextType::class)
            ->add('save', SubmitType::class, ['label' => 'Create Player'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getEm()->persist($player);
            $this->getEm()->flush();

            return $this->redirectToRoute('app_doko_index');
        }

        return $this->render(
            'index/create_player.html.twig',
            ['form' => $form->createView()]
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

        $form = $this->createFormBuilder()
            ->add('points', NumberType::class, ['label' => 'Points', 'required' => true])
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

        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $this->prepareData($form->getData());

            foreach ($data as $item) {
                $player = $this->getPlayerById($item['playerId']);
                $newPoints = $player->getPoints() + $item['points'];
                $player->setPoints($newPoints);
            }

            $this->getEm()->flush();

            $nextAction = $form->get('save')->isClicked() ? 'app_doko_showpoints' : 'app_doko_enterpoints';

            return $this->redirectToRoute($nextAction);
        }


        return $this->render(
            'index/enter_points.html.twig',
            ['form' => $form->createView()]
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
     * @return array
     */
    private function prepareData(array $getData)
    {
        $preparedData = [];
        for ($i = 1; $i <= 4; $i++) {
            if ($getData['player' . $i . 'win']) {
                $points = $getData['points'];
            } else {
                $points = $getData['points'] * -1;
            }
            $preparedData[] = ['playerId' => $getData['player' . $i], 'points' => $points];
        }

        return $preparedData;
    }
}
