<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Controller;

use Calmfox\SyliusAdminTwoFactorPlugin\Form\Type\TwoFactorSetupType;
use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Calmfox\SyliusAdminTwoFactorPlugin\Totp\PendingTotpUser;
use Calmfox\SyliusAdminTwoFactorPlugin\Totp\QrCodeRenderer;
use Doctrine\Persistence\ObjectManager;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Pairs the logged-in administrator with an authenticator app. The secret waits in the
 * session and lands on the account only after the administrator types a valid code, so a
 * half-finished setup never locks anyone out.
 */
final readonly class SetupAction
{
    private const SESSION_KEY = 'calmfox_admin_two_factor.pending_secret';

    public function __construct(
        private Security $security,
        private TotpAuthenticatorInterface $totpAuthenticator,
        private QrCodeRenderer $qrCodeRenderer,
        private ObjectManager $adminUserManager,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private Environment $twig,
        private TwoFactorPolicy $policy,
        private bool $passkeysEnabled = true,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $adminUser = $this->security->getUser();
        if (!$adminUser instanceof TwoFactorAdminUserInterface) {
            throw new AccessDeniedException('Two-factor setup is only available to administrators implementing TwoFactorAdminUserInterface.');
        }

        if ($adminUser->hasTwoFactorAuthentication() || !$this->policy->isActive()) {
            return new RedirectResponse($this->urlGenerator->generate('sylius_admin_dashboard'));
        }

        $session = $request->getSession();
        $secret = $session->get(self::SESSION_KEY);
        if (!is_string($secret) || '' === $secret) {
            $secret = $this->totpAuthenticator->generateSecret();
            $session->set(self::SESSION_KEY, $secret);
        }

        $pendingUser = new PendingTotpUser($adminUser->getTotpAuthenticationUsername(), $secret);

        $form = $this->formFactory->create(TwoFactorSetupType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->get('code')->getData();
            $code = is_string($data) ? $data : '';

            if ($this->totpAuthenticator->checkCode($pendingUser, $code)) {
                $adminUser->setTotpSecret($secret);
                $adminUser->setTwoFactorSetupRequired(false);
                $this->adminUserManager->flush();
                $session->remove(self::SESSION_KEY);

                if ($session instanceof FlashBagAwareSessionInterface) {
                    $session->getFlashBag()->add('success', 'calmfox_admin_two_factor.setup.enabled');
                }

                return new RedirectResponse($this->urlGenerator->generate('sylius_admin_dashboard'));
            }

            $form->get('code')->addError(new FormError(
                $this->translator->trans('calmfox_admin_two_factor.code.wrong', [], 'validators'),
            ));
        }

        return new Response(
            $this->twig->render('@CalmfoxSyliusAdminTwoFactorPlugin/security/setup.html.twig', [
                'form' => $form->createView(),
                'qrCode' => $this->qrCodeRenderer->dataUri($this->totpAuthenticator->getQRContent($pendingUser)),
                'secret' => trim(chunk_split($secret, 4, ' ')),
                'adminUser' => $adminUser,
                'passkeysEnabled' => $this->passkeysEnabled,
                'mandatory' => $this->policy->mustSetUp($adminUser),
            ]),
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }
}
