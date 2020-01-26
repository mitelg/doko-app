<?php

declare(strict_types=1);

/**
 * Copyright (c) Michael Telgmann
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mitelg\DokoApp\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Mitelg\DokoApp\Entity\Participant;
use Mitelg\DokoApp\Entity\Player;
use Mitelg\DokoApp\Entity\Round;
use Mitelg\DokoApp\Exception\FourWinnersException;
use Mitelg\DokoApp\Exception\NoPlayersException;
use Mitelg\DokoApp\Exception\NoWinnerSelectedException;
use Mitelg\DokoApp\Exception\PlayerSelectedTwiceException;
use Mitelg\DokoApp\Exception\TooFewPlayersException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DokoController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var Connection
     */
    private $connection;

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
        EntityManagerInterface $entityManager,
        Connection $connection,
        TranslatorInterface $translator,
        SessionInterface $session,
        PaginatorInterface $paginator
    ) {
        $this->entityManager = $entityManager;
        $this->connection = $connection;
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
     * @Route("/enterPoints")
     */
    public function enterPointsAction(Request $request): Response
    {
        /** @var array<array-key, int> $playerIds */
        $playerIds = (array) $this->session->get('playerIds', []);

        try {
            $pointsForm = $this->createPointsForm($playerIds);
        } catch (NoPlayersException | TooFewPlayersException $playersException) {
            $this->addFlash('danger', $playersException->getMessage());

            return $this->redirectToRoute('mitelg_dokoapp_doko_index');
        }

        $pointsForm->handleRequest($request);

        if ($pointsForm->isSubmitted() && $pointsForm->isValid()) {
            /** @var array<string, bool|int> $pointsFormData */
            $pointsFormData = (array) $pointsForm->getData();
            $pointsOfGame = 0;
            if (isset($pointsFormData['points'])) {
                $pointsOfGame = (int) $pointsFormData['points'];
            }
            if ($pointsOfGame < 1) {
                $pointsForm->addError(new FormError('The value of points must be at least 1'));

                return $this->render(
                    'index/enter_points.html.twig',
                    ['pointsForm' => $pointsForm->createView()]
                );
            }

            $isBockRound = false;
            if (isset($pointsFormData['bockRound'])) {
                $isBockRound = (bool) $pointsFormData['bockRound'];
            }

            $gameResult = [];

            try {
                $gameResult = $this->calculateGameResult($pointsFormData, $pointsOfGame, $isBockRound);
            } catch (FourWinnersException | NoWinnerSelectedException | PlayerSelectedTwiceException $e) {
                $pointsForm->addError(new FormError($e->getMessage()));
            }

            // if given data is not valid redirect to action again and show form errors
            if (!$pointsForm->isValid()) {
                return $this->render(
                    'index/enter_points.html.twig',
                    ['pointsForm' => $pointsForm->createView()]
                );
            }

            $round = new Round($pointsOfGame, $isBockRound);

            $playerIds = [];
            // if data is okay, save points to database
            /** @var Collection<array-key, Participant> $participants */
            $participants = new ArrayCollection();
            foreach ($gameResult as $item) {
                $player = $this->getPlayerById($item['playerId']);
                $playerIds[] = $item['playerId'];
                $newPoints = $player->getPoints() + $item['points'];
                $player->setPoints($newPoints);
                $participant = new Participant($round, $player, $item['points']);
                $participants->add($participant);
            }
            $round->setParticipants($participants);
            $this->entityManager->persist($round);
            $this->entityManager->flush();

            $this->session->set('playerIds', $playerIds);

            /** @var SubmitButton $saveButton */
            $saveButton = $pointsForm->get('save');
            $nextAction = $saveButton->isClicked() ? 'mitelg_dokoapp_doko_showscoreboard' : 'mitelg_dokoapp_doko_enterpoints';

            return $this->redirectToRoute($nextAction);
        }

        return $this->render(
            'index/enter_points.html.twig',
            ['pointsForm' => $pointsForm->createView()]
        );
    }

    /**
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
                'longestWinStreak' => $streaks['winStreak'],
                'longestLosingStreak' => $streaks['lossStreak'],
                'winLossRatio' => $winLossRatio,
            ]
        );
    }

    /**
     * @return array{winStreak:int, lossStreak:int}
     */
    private function calculateStreaks(int $playerId): array
    {
        $sql = 'SELECT participant.points
                 FROM participant
                 JOIN round ON round.id = participant.round_id
                 WHERE participant.player_id = :playerId';

        $query = $this->connection->prepare($sql);
        $query->bindValue('playerId', $playerId);
        $query->execute();
        /** @var array<array-key, string> $points */
        $points = $query->fetchAll(FetchMode::COLUMN);

        $maxWinStreak = 0;
        $winStreak = 0;
        $maxLossStreak = 0;
        $lossStreak = 0;

        foreach ($points as $point) {
            $point = (int) $point;

            if ($point >= 0) {
                ++$winStreak;
                if ($winStreak > $maxWinStreak) {
                    $maxWinStreak = $winStreak;
                }
                $lossStreak = 0;

                continue;
            }

            ++$lossStreak;
            if ($lossStreak > $maxLossStreak) {
                $maxLossStreak = $lossStreak;
            }
            $winStreak = 0;
        }

        return ['winStreak' => $maxWinStreak, 'lossStreak' => $maxLossStreak];
    }

    /**
     * calculate the game result before saving it into the database
     * checks for winners and losers
     * also checks if given data is valid
     *
     * @param array<string, int|bool> $formData
     *
     * @throws FourWinnersException
     * @throws NoWinnerSelectedException
     * @throws PlayerSelectedTwiceException
     *
     * @return array<array-key, array{playerId:int, points:int}>
     */
    private function calculateGameResult(array $formData, int $points, bool $isBockRound): array
    {
        $playerIds = [];
        $winners = [];
        $losers = [];

        // double the points if Bock round
        if ($isBockRound) {
            $points *= 2;
        }

        // separate the four players into winners and losers
        for ($i = 1; $i <= 4; ++$i) {
            $playerId = (int) $formData['player' . $i];
            if (\in_array($playerId, $playerIds, true)) {
                throw new PlayerSelectedTwiceException('One player is selected twice');
            }
            $playerIds[] = $playerId;

            if ($formData['player' . $i . 'win']) {
                $winners[] = ['playerId' => $playerId, 'points' => $points];
            } else {
                $losers[] = ['playerId' => $playerId, 'points' => $points * -1];
            }
        }

        if (\count($winners) === 0) {
            throw new NoWinnerSelectedException('There must be at least one winner');
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
            if (isset($losers[0]['points'])) {
                $losers[0]['points'] *= 3;
            }

            return array_merge($winners, $losers);
        }

        throw new FourWinnersException('Four winners are one too many');
    }

    /**
     * @param array<array-key, int> $playerIds
     *
     * @return FormInterface<FormTypeInterface>
     */
    private function createPointsForm(array $playerIds): FormInterface
    {
        $players = $this->getPlayers();
        if ($players === []) {
            throw new NoPlayersException('No players created yet. Please create at least four players');
        }
        if (\count($players) < 4) {
            throw new TooFewPlayersException('Too few players for a game. Please create at least four players');
        }

        if ($playerIds === []) {
            foreach (\array_slice($players, 0, 4) as $initialPlayer) {
                $playerIds[] = $initialPlayer->getId();
            }
        }

        $playersArray = [];
        foreach ($players as $player) {
            $playersArray[$player->getName()] = $player->getId();
        }

        /** @var FormBuilder<FormTypeInterface> $pointsForm */
        $pointsForm = $this->createFormBuilder()
            ->add('points', IntegerType::class)
            ->add('bockRound', CheckboxType::class, ['required' => false]);

        // create four players
        for ($i = 1; $i <= 4; ++$i) {
            $pointsForm->add('player' . $i, ChoiceType::class, [
                'choices' => $playersArray,
                'data' => $playerIds[$i - 1],
            ]);
            $pointsForm->add('player' . $i . 'win', CheckboxType::class, ['required' => false]);
        }

        $translator = $this->translator;

        $pointsForm->add('saveAndNew', SubmitType::class, [
            'label' => $translator->trans('save_and_new', [], 'buttons'),
        ]);
        $pointsForm->add('save', SubmitType::class, ['label' => $translator->trans('save', [], 'buttons')]);

        return $pointsForm->getForm();
    }

    /**
     * @return Player[]
     */
    private function getPlayers(): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select(['player'])
            ->from(Player::class, 'player')
            ->addOrderBy('player.points', 'DESC');

        /** @var Player[] $result */
        $result = $builder->getQuery()->getResult();

        return $result;
    }

    private function getPlayerById(int $id): Player
    {
        /** @var Player $player */
        $player = $this->entityManager->getRepository(Player::class)->find($id);

        return $player;
    }

    /**
     * @return PaginationInterface<Round>
     */
    private function getRounds(Request $request): PaginationInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select(['round'])
            ->from(Round::class, 'round')
            ->addOrderBy('round.creationDate', 'DESC');

        $query = $queryBuilder->getQuery();

        return $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1)
        );
    }

    /**
     * @return PaginationInterface<Round>
     */
    private function getRoundsByPlayer(Player $player, Request $request): PaginationInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select(['round'])
            ->from(Round::class, 'round')
            ->join('round.participants', 'participant')
            ->andWhere('participant.player = :player')
            ->setParameter('player', $player)
            ->addOrderBy('round.id', 'DESC');

        $query = $queryBuilder->getQuery();

        return $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1)
        );
    }

    /**
     * @return array<array-key, array<string, string>>
     */
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

        return $this->connection->executeQuery($sql, ['playerId' => $player->getId()])->fetchAll();
    }

    /**
     * @return array{wins:int, loss:int}
     */
    private function getWinLossRatio(int $playerId): array
    {
        $sql = 'SELECT SUM(points > 0) AS wins, SUM(points < 0) AS loss
                FROM `participant`
                WHERE player_id = :playerId
                GROUP BY player_id';

        $sql = $this->connection->prepare($sql);
        $sql->bindValue('playerId', $playerId);
        $sql->execute();
        /** @var array{wins:string, loss:string}|false $result */
        $result = $sql->fetch();

        if ($result === false) {
            return ['wins' => 0, 'loss' => 0];
        }

        return array_map(static function (string $value) {
            return (int) $value;
        }, $result);
    }
}
