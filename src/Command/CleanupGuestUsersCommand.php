<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-guests',
    description: 'Deletes guest accounts older than 24 hours to keep the database clean',
)]
class CleanupGuestUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // 1. Calculate the cutoff time (24 hours ago)
        $cutoffDate = new \DateTimeImmutable('-24 hours');
        
        $io->title('Starting Guest Account Cleanup');
        $io->text(sprintf('Looking for accounts created before: %s', $cutoffDate->format('Y-m-d H:i:s')));

        // 2. Find Old Guest Users
        // Logic: Email starts with 'guest_%' AND createdAt is older than 24 hours
        $qb = $this->entityManager->createQueryBuilder();
        
        $oldGuests = $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.email LIKE :pattern')
            ->andWhere('u.createdAt < :cutoff') // Uses your TimestampableTrait field
            ->setParameter('pattern', 'guest_%')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        $count = count($oldGuests);

        if ($count === 0) {
            $io->success('No expired guest accounts found. Database is clean.');
            return Command::SUCCESS;
        }

        // 3. Delete them
        // Because orphanRemoval=true 
        // this will automatically delete the User's Clients and Invoices too.
        foreach ($oldGuests as $guest) {
            $this->entityManager->remove($guest);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Cleanup complete. Deleted %d expired guest accounts (and their related data).', $count));

        return Command::SUCCESS;
    }
}