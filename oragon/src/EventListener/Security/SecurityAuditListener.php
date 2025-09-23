<?php

namespace App\EventListener\Security;

use App\Service\Security\SecurityAuditService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;

class SecurityAuditListener
{
    public function __construct(
        private SecurityAuditService $auditService
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        
        $this->auditService->logSecurityEvent('login_success', $user, [
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'authentication_method' => $this->getAuthenticationMethod($event),
        ]);

        // Update last login time
        if (method_exists($user, 'setLastLoginAt')) {
            $user->setLastLoginAt(new \DateTimeImmutable());
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $exception = $event->getException();
        $request = $event->getRequest();
        
        $email = $request->request->get('email') ?? $request->request->get('_username') ?? 'unknown';
        
        $this->auditService->logSecurityEvent('login_failed', null, [
            'email' => $email,
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'failure_reason' => $exception->getMessage(),
            'exception_class' => get_class($exception),
        ]);
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token ? $token->getUser() : null;
        
        if ($user) {
            $this->auditService->logSecurityEvent('logout', $user, [
                'ip_address' => $event->getRequest()->getClientIp(),
                'user_agent' => $event->getRequest()->headers->get('User-Agent'),
            ]);
        }
    }

    #[AsEventListener(event: AuthenticationSuccessEvent::class)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $token = $event->getAuthenticationToken();
        $user = $token->getUser();

        // Handle 2FA authentication
        if ($token instanceof TwoFactorTokenInterface) {
            $this->auditService->logSecurityEvent('2fa_success', $user, [
                'provider' => $token->getTwoFactorProviderKey(),
            ]);
        }
    }

    #[AsEventListener(event: AuthenticationFailureEvent::class)]
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $token = $event->getAuthenticationToken();
        $user = $token ? $token->getUser() : null;
        $exception = $event->getAuthenticationException();

        // Handle 2FA authentication failures
        if ($token instanceof TwoFactorTokenInterface) {
            $this->auditService->logSecurityEvent('2fa_failed', $user, [
                'provider' => $token->getTwoFactorProviderKey(),
                'failure_reason' => $exception->getMessage(),
            ]);
        } else {
            $this->auditService->logSecurityEvent('authentication_failed', $user, [
                'failure_reason' => $exception->getMessage(),
                'exception_class' => get_class($exception),
            ]);
        }
    }

    /**
     * Determine the authentication method used
     */
    private function getAuthenticationMethod($event): string
    {
        $authenticator = $event->getAuthenticator();
        
        if ($authenticator) {
            $class = get_class($authenticator);
            return substr($class, strrpos($class, '\\') + 1);
        }
        
        return 'unknown';
    }
}