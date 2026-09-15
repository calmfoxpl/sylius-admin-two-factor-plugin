<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserTrait;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AdminUser as BaseAdminUser;

/** The test application's administrator, set up the way the README tells applications to. */
#[ORM\Entity]
#[ORM\Table(name: 'sylius_admin_user')]
class AdminUser extends BaseAdminUser implements TwoFactorAdminUserInterface
{
    use TwoFactorAdminUserTrait;
}
