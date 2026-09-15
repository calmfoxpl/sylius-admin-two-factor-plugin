<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Command;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Doctrine\Persistence\ObjectManager;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'calmfox:admin:2fa:reset',
    description: 'Resets an administrator\'s two-factor authentication (e.g. after losing their phone): paired methods are removed and they pair again at next login.',
)]
final class ResetTwoFactorCommand extends Command
{
    /** @param UserRepositoryInterface<TwoFactorAdminUserInterface> $adminUserRepository */
    public function __construct(
        private readonly UserRepositoryInterface $adminUserRepository,
        private readonly ObjectManager $adminUserManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail address of the administrator')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Only remove the paired methods, without requiring a new pairing (the policy still applies)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $email = is_string($email) ? $email : '';

        $adminUser = $this->adminUserRepository->findOneByEmail($email);
        if (!$adminUser instanceof TwoFactorAdminUserInterface) {
            $io->error(sprintf('No administrator with two-factor support found for %s.', $email));

            return Command::FAILURE;
        }

        $disable = (bool) $input->getOption('disable');
        $adminUser->setTotpSecret(null);
        $adminUser->setPasskeyCredentials([]);
        $adminUser->setTwoFactorSetupRequired(!$disable);
        $this->adminUserManager->flush();

        $io->success($disable
            ? sprintf('Two-factor authentication disabled for %s.', $email)
            : sprintf('Two-factor authentication reset for %s. They will pair a new device at next login.', $email));

        return Command::SUCCESS;
    }
}
