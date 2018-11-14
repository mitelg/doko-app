<?php declare(strict_types=1);
/**
 * The MIT License (MIT)
 *
 * Copyright (c) Michael Telgmann
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Controller;

use App\Entity\Participant;
use App\Entity\Player;
use App\Entity\Round;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Translation\TranslatorInterface;

class DokoController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var PaginatorInterface
     */
    private $paginator;

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        SessionInterface $session,
        PaginatorInterface $paginator
    ) {
        $this->em = $em;
        $this->translator = $translator;
        $this->session = $session;
        $this->paginator = $paginator;
    }

    /**
     * @Route("/")
     */
    public function indexAction(): Response
    {
        return $this->render('index/index.html.twig');
    }

    /**
     * creates a new player
     *
     * @Route("/createPlayer")
     */
    public function createPlayerAction(Request $request): Response
    {
        $player = new Player();
        $buttonTranslation = $this->translator->trans('create', [], 'create_player');

        /* @var FormInterface $playerForm */
        $playerForm = $this->createFormBuilder($player)
            ->add('name', TextType::class)
            ->add('save', SubmitType::class, ['label' => $buttonTranslation])
            ->getForm();

        $playerForm->handleRequest($request);

        if ($playerForm->isSubmitted()) {
            $this->em->persist($player);
            $this->em->flush();

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
     */
    public function enterPointsAction(Request $request): Response
    {
        $pointsForm = $this->createPointsForm($this->session->get('playerIds', []));

        $pointsForm->handleRequest($request);

        if ($pointsForm->isSubmitted()) {
            $pointsOfGame = $pointsForm->getData()['points'];
            if ($pointsOfGame > 0) {
                $data = $this->calculateGameResult($pointsForm->getData());
            } else {
                $data = -4;
            }

            // if given data is not valid, throw several form errors and redirect to action again
            if (!\is_array($data)) {
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

            $round = new Round($pointsOfGame, $pointsForm->get('bockRound')->getData());

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
            $this->em->persist($round);
            $this->em->flush();

            $this->session->set('playerIds', $playerIds);

            /** @var SubmitButton $saveButton */
            $saveButton = $pointsForm->get('save');
            $nextAction = $saveButton->isClicked() ? 'app_doko_showscoreboard' : 'app_doko_enterpoints';

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
     */
    public function showScoreboardAction(Request $request): Response
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
     */
    public function getPlayerStats(Request $request, int $playerId): Response
    {
        $player = $this->getPlayerById($playerId);

        $rounds = $this->getRoundsByPlayer($player, $request);

        $partners = $this->getPartnersOfPlayer($player);

        $streaks = $this->calculateStreaks($playerId);

        $winLossRatio = $this->getWinLossRatio($playerId);

        return $this->render(
            'index/player_stats.html.twig',
            [
                'player' => $player,
                'rounds' => $rounds,
                'partners' => $partners,
                'longestWinStreak' => $streaks['win_streak'],
                'longestLosingStreak' => $streaks['loss_streak'],
                'winLossRatio' => $winLossRatio,
            ]
        );
    }

    private function calculateStreaks(int $playerId): array
    {
        $sql1 = 'SELECT round.creation_date AS GameDate, GR.points AS Result
                 FROM participant GR
                 JOIN round ON round.id = GR.round_id
                 WHERE GR.player_id = :playerId';

        $sql = $this->em->getConnection()
            ->prepare($sql1);
        $sql->bindValue('playerId', $playerId);
        $sql->execute();
        $data = $sql->fetchAll();

        $maxWinStreak = 0;
        $_win_streak = 0;
        $maxLossStreak = 0;
        $_loss_streak = 0;

        foreach ($data as $value) {
            if ($value['Result'] >= 0) {
                ++$_win_streak;
                if ($_win_streak > $maxWinStreak) {
                    $maxWinStreak = $_win_streak;
                }
                $_loss_streak = 0;
            } elseif ($value['Result'] < 0) {
                ++$_loss_streak;
                if ($_loss_streak > $maxLossStreak) {
                    $maxLossStreak = $_loss_streak;
                }
                $_win_streak = 0;
            }
        }

        return ['win_streak' => $maxWinStreak, 'loss_streak' => $maxLossStreak];
    }

    /**
     * calculate the game result before saving it into the database
     * checks for winners and losers
     * also checks if given data is valid
     *
     * @param array $formData
     *
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
        for ($i = 1; $i <= 4; ++$i) {
            $playerId = (int) $formData['player' . $i];
            if (\in_array($playerId, $playerIds, true)) {
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

        if (\count($winners) === 0) {
            // there is no winner selected
            return -1;
        }

        if (\count($winners) === 1) {
            // there is only one winner, so he/she gets more points
            $winners[0]['points'] *= 3;

            return array_merge($winners, $losers);
        }

        if (\count($winners) === 2) {
            return array_merge($winners, $losers);
        }

        if (\count($winners) === 3) {
            // there is only one loser, so he/she loses more points
            $losers[0]['points'] *= 3;

            return array_merge($winners, $losers);
        }

        // all four players can not be winners
        return -2;
    }

    private function createPointsForm(array $playerIds): FormInterface
    {
        $players = $this->getPlayers();

        if (empty($playerIds)) {
            foreach (\array_slice($players, 0, 4) as $initialPlayer) {
                $playerIds[] = $initialPlayer->getId();
            }
        }

        $playersArray = [];
        foreach ($players as $player) {
            $playersArray[$player->getName()] = $player->getId();
        }

        /** @var FormBuilder $pointsForm */
        $pointsForm = $this->createFormBuilder()
            ->add('points', IntegerType::class)
            ->add('bockRound', CheckboxType::class, ['required' => false]);

        // create four players
        for ($i = 1; $i <= 4; ++$i) {
            $pointsForm->add('player' . $i, ChoiceType::class, ['choices' => $playersArray, 'data' => $playerIds[$i - 1]]);
            $pointsForm->add('player' . $i . 'win', CheckboxType::class, ['required' => false]);
        }

        $translator = $this->translator;

        $pointsForm->add('saveAndNew', SubmitType::class, ['label' => $translator->trans('save_and_new', [], 'buttons')])
            ->add('save', SubmitType::class, ['label' => $translator->trans('save', [], 'buttons')]);

        return $pointsForm->getForm();
    }

    /**
     * @return Player[]
     */
    private function getPlayers(): array
    {
        $builder = $this->em->createQueryBuilder()
            ->select(['player'])
            ->from('App:Player', 'player')
            ->addOrderBy('player.points', 'DESC');

        return $builder->getQuery()->getResult();
    }

    private function getPlayerById(int $id): Player
    {
        /** @var Player $player */
        $player = $this->em->getRepository('App:Player')->find($id);

        return $player;
    }

    private function getRounds(Request $request): PaginationInterface
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->select(['round'])
            ->from('App:Round', 'round')
            ->addOrderBy('round.creationDate', 'DESC');

        $query = $queryBuilder->getQuery();

        $pagination = $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1)
        );

        return $pagination;
    }

    private function getRoundsByPlayer(Player $player, Request $request): PaginationInterface
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->select(['round'])
            ->from('App:Round', 'round')
            ->join('round.participants', 'participant')
            ->andWhere('participant.player = :player')
            ->setParameter('player', $player)
            ->addOrderBy('round.id', 'DESC');

        $query = $queryBuilder->getQuery();

        $pagination = $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1)
        );

        return $pagination;
    }

    private function getPartnersOfPlayer(Player $player): array
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

        return $this->em->getConnection()->executeQuery($sql, ['playerId' => $player->getId()])->fetchAll();
    }

    /**
     * @return array|bool
     */
    private function getWinLossRatio(int $playerId)
    {
        $sql = 'SELECT SUM(points > 0) AS wins, SUM(points < 0) AS loss
                FROM `participant` 
                WHERE player_id = :playerId
                GROUP BY player_id';

        $sql = $this->em->getConnection()->prepare($sql);
        $sql->bindValue('playerId', $playerId);
        $sql->execute();

        return $sql->fetch();
    }
}
