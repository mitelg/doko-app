<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Player;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $html = $this->render(
            'index/index.html.twig'
        );

        return new Response($html);
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
        $playerRepo = $this->getEm()->getRepository('AppBundle:Player');

        $players = $playerRepo->findAll();

        $html = $this->render(
            'index/show_points.html.twig',
            ['players' => $players]
        );

        return new Response($html);
    }

    /**
     * @return EntityManager
     */
    private function getEm()
    {
        if ($this->em == null) {
            $this->em = $this->getDoctrine()->getEntityManager();
            return $this->em;
        }

        return $this->em;
    }
}
