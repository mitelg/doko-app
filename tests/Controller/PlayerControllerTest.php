<?php declare(strict_types=1);
/*
 * Copyright (c) Michael Telgmann
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mitelg\DokoApp\Test;

use Doctrine\ORM\EntityManagerInterface;
use Mitelg\DokoApp\Entity\Player;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PlayerControllerTest extends WebTestCase
{
    private const TEST_PLAYER_NAME = 'Test Player';

    public function testShowCreatePlayerAction(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/createPlayer');

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        static::assertSelectorTextContains('html h1', 'Create new player');
    }

    public function testCreatePlayerAction(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = self::$container->get(EntityManagerInterface::class);
        $entityManager->beginTransaction();

        $client->request(Request::METHOD_GET, '/createPlayer');
        $client->submitForm('Create player', [
            'form[name]' => self::TEST_PLAYER_NAME,
        ]);

        $successText = $client->followRedirect()->filter('div.alert-success')->text();

        static::assertSame('New player created', $successText);

        $builder = $entityManager->createQueryBuilder()
            ->select(['player'])
            ->from(Player::class, 'player')
            ->where('player.name LIKE :name')
            ->setParameter('name', self::TEST_PLAYER_NAME);

        /** @var Player[] $result */
        $result = $builder->getQuery()->getResult();

        static::assertCount(1, $result);

        $testPlayer = $result[0];
        self::assertSame(self::TEST_PLAYER_NAME, $testPlayer->getName());
        self::assertSame(0, $testPlayer->getPoints());
        self::assertIsInt($testPlayer->getId());

        $entityManager->rollback();
    }
}
