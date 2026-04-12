<?php

namespace Symfonicat\Command;

use Symfonicat\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:admin:delete',
    description: 'Delete a Symfonicat admin user by email.',
    aliases: ['symfonicat:admin:remove'],
)]
final class AdminDeleteCommand extends Command
{
    public function __construct(
        private readonly AdminRepository $adminRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Admin email address.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));
        $admin = $this->adminRepository->findOneByEmail($email);

        if ($admin === null) {
            $io->error(sprintf('Admin "%s" was not found.', $email));

            return Command::FAILURE;
        }

        $this->entityManager->remove($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Admin "%s" deleted.', $email));

        return Command::SUCCESS;
    }
}
