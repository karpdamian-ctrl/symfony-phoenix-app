<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ListAuthTokensCommand;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ListAuthTokensCommandTest extends TestCase
{
    public function testCommandDisplaysUsersAndTokens(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['username' => 'anna', 'token' => 'token-anna'],
                ['username' => 'bob', 'token' => null],
            ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);

        $command = new ListAuthTokensCommand($entityManager);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);
        $display = $commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('username', $display);
        self::assertStringContainsString('token', $display);
        self::assertStringContainsString('anna', $display);
        self::assertStringContainsString('token-anna', $display);
        self::assertStringContainsString('bob', $display);
        self::assertStringContainsString('(no token)', $display);
    }

    public function testCommandShowsWarningWhenNoUsersExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);

        $command = new ListAuthTokensCommand($entityManager);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);
        $display = $commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No users found.', $display);
    }
}
