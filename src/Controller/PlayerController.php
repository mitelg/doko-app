<?php

declare(strict_types=1);

/**
 * Copyright (c) Michael Telgmann
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mitelg\DokoApp\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mitelg\DokoApp\Entity\Player;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PlayerController extends AbstractController
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator
    ) {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
    }

    /**
     * creates a new player
     *
     * @Route("/createPlayer")
     */
    public function createPlayerAction(Request $request): Response
    {
        $player = new Player();
        $playerForm = $this->createPlayerForm($request, $player);

        if ($playerForm->isSubmitted() && $playerForm->isValid()) {
            $this->entityManager->persist($player);
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('created', [], 'create_player'));

            return $this->redirectToRoute('mitelg_dokoapp_doko_index');
        }

        return $this->render(
            'player/create_player.html.twig',
            ['playerForm' => $playerForm->createView()]
        );
    }

    /**
     * @return FormInterface<FormTypeInterface>
     */
    private function createPlayerForm(Request $request, Player $player): FormInterface
    {
        $buttonTranslation = $this->translator->trans('create', [], 'create_player');

        $playerForm = $this->createFormBuilder($player)
            ->add('name', TextType::class)
            ->add('save', SubmitType::class, ['label' => $buttonTranslation])
            ->getForm();

        return $playerForm->handleRequest($request);
    }
}
