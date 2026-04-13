<?php

namespace Symfonicat\Command;

use BaconQrCode\Renderer\PlainTextRenderer;
use BaconQrCode\Writer;
use Symfonicat\Entity\Admin;
use Symfonicat\Repository\AdminRepository;
use Symfonicat\Service\AdminMfaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'symfonicat:admin:create',
    description: 'Create or update a Symfonicat admin user.',
    aliases: ['symfonicat:admin:add'],
)]
final class AdminCreateCommand extends Command
{
    public function __construct(
        private readonly AdminRepository $adminRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdminMfaService $adminMfaService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email address.')
            ->addArgument('password', InputArgument::OPTIONAL, 'Admin password.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));

        if ($email === '') {
            $io->error('Email must not be empty.');

            return Command::FAILURE;
        }

        $password = (string) $input->getArgument('password');
        if ($password === '') {
            $helper = $this->getHelper('question');
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = (string) $helper->ask($input, $output, $question);
        }

        if ($password === '') {
            $io->error('Password must not be empty.');

            return Command::FAILURE;
        }

        $admin = $this->adminRepository->findOneByEmail($email) ?? (new Admin())->setEmail($email);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
        $admin->setRoles(['ROLE_ADMIN']);
        $this->adminMfaService->ensureSecret($admin);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $provisioningUri = $this->adminMfaService->getProvisioningUri($admin);

        $io->success(sprintf('Admin "%s" is ready.', $admin->getEmail()));
        $io->section('MFA');
        $io->writeln('Scan this QR code with your TOTP app:');
        if ($provisioningUri !== null && $provisioningUri !== '') {
            $io->newLine();
            $output->writeln((new Writer(new PlainTextRenderer()))->writeString($provisioningUri));
        }

        return Command::SUCCESS;
    }
}
