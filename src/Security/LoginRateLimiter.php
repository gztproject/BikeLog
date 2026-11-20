<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Login throttling with a limit per username + client IP and a higher limit per client IP.
 *
 * Unlike Symfony's DefaultLoginRateLimiter this is deliberately not a
 * PeekableRequestRateLimiterInterface: a peekable limiter only checks the
 * counter before the password is verified and records the attempt afterwards,
 * so concurrent requests all pass the check before any of them is counted.
 * Here every attempt atomically takes a token before its password is checked.
 */
final class LoginRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        private RateLimiterFactoryInterface $loginGlobalLimiter,
        private RateLimiterFactoryInterface $loginLocalLimiter,
        #[Autowire('%kernel.secret%')] #[\SensitiveParameter] private string $secret,
    ) {
        if ('' === $secret) {
            throw new \InvalidArgumentException('A non-empty secret is required.');
        }
    }

    public function consume(Request $request): RateLimit
    {
        $global = $this->globalLimiter($request)->consume();
        $local = $this->localLimiter($request)->consume();

        if ($global->isAccepted() !== $local->isAccepted()) {
            return $global->isAccepted() ? $local : $global;
        }
        if (!$global->isAccepted()) {
            return $global->getRetryAfter() > $local->getRetryAfter() ? $global : $local;
        }

        return $global->getRemainingTokens() < $local->getRemainingTokens() ? $global : $local;
    }

    /**
     * Called after a successful login. Only the username + IP budget is restored:
     * restoring the per-IP budget would let anyone with a valid account clear it
     * by signing in to their own account between guesses at other usernames.
     */
    public function reset(Request $request): void
    {
        $this->localLimiter($request)->reset();
    }

    private function globalLimiter(Request $request): LimiterInterface
    {
        return $this->loginGlobalLimiter->create($this->hash((string) $request->getClientIp()));
    }

    private function localLimiter(Request $request): LimiterInterface
    {
        $username = (string) $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME, '');
        $username = preg_match('//u', $username) ? mb_strtolower($username, 'UTF-8') : strtolower($username);

        return $this->loginLocalLimiter->create($this->hash($username.'-'.$request->getClientIp()));
    }

    private function hash(string $data): string
    {
        return strtr(substr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), 0, 8), '/+', '._');
    }
}
