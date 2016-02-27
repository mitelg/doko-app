<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DokoController extends Controller
{
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
     * @return Response
     */
    public function createPlayerAction()
    {
        $html = $this->render(
            'index/create_player.html.twig'
        );

        return new Response($html);
    }

    /**
     * @Route("/showPoints")
     * @return Response
     */
    public function showPointsAction()
    {
        $html = $this->render(
            'index/show_points.html.twig'
        );

        return new Response($html);
    }
}
