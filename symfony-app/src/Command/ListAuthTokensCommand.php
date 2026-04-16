<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:auth-tokens:list',
    description: 'Display users with their auth tokens',
)]
final class ListAuthTokensCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT u.username, at.token
                FROM users u
                LEFT JOIN auth_tokens at ON at.user_id = u.id
                ORDER BY u.username
            SQL
        );

        if ($rows === []) {
            $io->warning('No users found.');

            return Command::SUCCESS;
        }

        $tableRows = array_map(
            static fn (array $row): array => [
                $row['username'],
                $row['token'] ?? '(no token)',
            ],
            $rows
        );

        $io->table(['username', 'token'], $tableRows);

        return Command::SUCCESS;
    }
}
