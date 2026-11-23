<?php

namespace App\Tests\Entity;

use App\Entity\User\CreateUserCommand;
use App\Entity\User\UpdateUserCommand;
use App\Entity\User\User;
use App\Form\User\UserType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\CheckCredentialsListener;
use Symfony\Component\Security\Http\EventListener\UserCheckerListener;

/**
 * Account creation and password change must reject passwords shorter than 8
 * characters. These tests fail while a four-character password is still
 * hashed and then accepted by the sign-in credential check.
 *
 * ChangePasswordType is not covered: no controller or template references it.
 */
class WeakPasswordPolicyTest extends KernelTestCase
{
    private const MINIMUM_LENGTH = 8;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testUserModelRejectsShortPasswordsWhenCreatingRiderAndAdminAccounts(): void
    {
        foreach ([4, 7] as $length) {
            foreach ([false, true] as $admin) {
                $password = $this->placeholder($length, true);
                $result = $this->createThroughModel($password, $admin);

                self::assertFalse(
                    $result['acceptedBySignIn'],
                    'Creating an account must not leave a password shorter than 8 characters that sign-in accepts.'
                );
                self::assertFalse($result['canOpenMotorcyclePages']);
                if ($admin) {
                    self::assertFalse($result['canStartUpdate']);
                }
            }
        }
    }

    public function testNewUserFormRejectsShortPasswordsBeforeTheyCanSignIn(): void
    {
        foreach ([4, 7] as $length) {
            $password = $this->placeholder($length, true);
            $result = $this->createThroughForm($password, true);

            self::assertFalse($result['acceptedBySignIn']);
            self::assertFalse($result['canOpenMotorcyclePages']);
            self::assertFalse($result['canStartUpdate']);
        }
    }

    public function testPasswordChangeRejectsShortPasswordsAndKeepsThePreviousOne(): void
    {
        $original = $this->placeholder(self::MINIMUM_LENGTH, false);
        $user = $this->requireCreatedUser($original, false);

        foreach ([4, 7] as $length) {
            $replacement = $this->placeholder($length, true);

            $direct = $this->changeThroughModel($user, $original, $replacement);
            self::assertTrue($direct['rejected']);
            self::assertNotSame('Old password is incorrect.', $direct['message']);
            self::assertTrue($this->signInAccepts($user, $original));
            self::assertFalse($this->signInAccepts($user, $replacement));

            $throughForm = $this->changeThroughForm($user, $original, $replacement);
            self::assertTrue($throughForm['rejected']);
            self::assertNotSame('Old password is incorrect.', $throughForm['message']);
            self::assertTrue($this->signInAccepts($user, $original));
            self::assertFalse($this->signInAccepts($user, $replacement));
        }
    }

    public function testMinimumLengthPasswordCanBeCreatedAndChanged(): void
    {
        $original = $this->placeholder(self::MINIMUM_LENGTH, false);
        $user = $this->requireCreatedUser($original, false);
        self::assertTrue($this->signInAccepts($user, $original));
        self::assertTrue($this->canOpenMotorcyclePages($user));

        $replacement = $this->differentLetters(self::MINIMUM_LENGTH);
        $changed = $this->changeThroughModel($user, $original, $replacement);
        self::assertFalse($changed['rejected'], $changed['message']);
        self::assertTrue($this->signInAccepts($user, $replacement));
        self::assertFalse($this->signInAccepts($user, $original));

        $admin = $this->requireCreatedUser($original, true);
        self::assertTrue($this->signInAccepts($admin, $original));
        self::assertContains('ROLE_ADMIN', $admin->getRoles());
        self::assertTrue($this->canStartUpdate($admin));
    }

    private function createThroughModel(string $password, bool $admin): array
    {
        $command = $this->command($password, $admin);
        try {
            $user = $this->creator()->createUser($command, $this->hasher());
        } catch (\Throwable $e) {
            return $this->rejectedAccount();
        }

        return $this->accountOutcome($user, $password);
    }

    private function createThroughForm(string $password, bool $admin): array
    {
        $command = new CreateUserCommand();
        $form = $this->submitUserForm($command, $password, $admin, '');
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->rejectedAccount();
        }

        try {
            $user = $this->creator()->createUser($command, $this->hasher());
        } catch (\Throwable $e) {
            return $this->rejectedAccount();
        }

        return $this->accountOutcome($user, $password);
    }

    /**
     * @return array{rejected: bool, message: string}
     */
    private function changeThroughModel(User $user, string $original, string $replacement): array
    {
        $command = new UpdateUserCommand();
        $command->password = $replacement;
        $command->oldPassword = $original;

        return $this->applyPasswordChange($user, $command);
    }

    /**
     * @return array{rejected: bool, message: string}
     */
    private function changeThroughForm(User $user, string $original, string $replacement): array
    {
        $command = new UpdateUserCommand();
        $user->mapTo($command);
        $form = $this->submitUserForm($command, $replacement, $user->isAdmin(), $original);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return ['rejected' => true, 'message' => ''];
        }

        return $this->applyPasswordChange($user, $command);
    }

    /**
     * @return array{rejected: bool, message: string}
     */
    private function applyPasswordChange(User $user, UpdateUserCommand $command): array
    {
        try {
            $user->update($command, $user, $this->hasher());
        } catch (\Throwable $e) {
            return ['rejected' => true, 'message' => $e->getMessage()];
        }

        return ['rejected' => false, 'message' => ''];
    }

    private function requireCreatedUser(string $password, bool $admin): User
    {
        $user = $this->creator()->createUser($this->command($password, $admin), $this->hasher());
        self::assertNotNull($user->getPassword());

        return $user;
    }

    private function command(string $password, bool $admin): CreateUserCommand
    {
        $command = new CreateUserCommand();
        $command->username = 'rider-placeholder';
        $command->firstName = 'Ride';
        $command->lastName = 'Er';
        $command->email = 'rider@example.test';
        $command->mobile = '';
        $command->password = $password;
        $command->isRoleAdmin = $admin;

        return $command;
    }

    private function accountOutcome(User $user, string $password): array
    {
        $accepted = $this->signInAccepts($user, $password);

        return [
            'acceptedBySignIn' => $accepted,
            'canOpenMotorcyclePages' => $accepted && $this->canOpenMotorcyclePages($user),
            'canStartUpdate' => $accepted && $this->canStartUpdate($user),
        ];
    }

    /**
     * @return array{acceptedBySignIn: bool, canOpenMotorcyclePages: bool, canStartUpdate: bool}
     */
    private function rejectedAccount(): array
    {
        return [
            'acceptedBySignIn' => false,
            'canOpenMotorcyclePages' => false,
            'canStartUpdate' => false,
        ];
    }

    private function signInAccepts(User $user, string $password): bool
    {
        $request = Request::create('/login', 'POST', [
            '_username' => $user->getUserIdentifier(),
            '_password' => $password,
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = self::getContainer()->get('request_stack');
        $stack->push($request);

        $authenticator = new FormLoginAuthenticator(
            self::getContainer()->get('security.http_utils'),
            new FixedUserProvider($user),
            new class implements AuthenticationSuccessHandlerInterface {
                public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
                {
                    return new Response();
                }
            },
            new class implements AuthenticationFailureHandlerInterface {
                public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
                {
                    return new Response('', Response::HTTP_UNAUTHORIZED);
                }
            },
            [
                'check_path' => 'app_login',
                'login_path' => 'app_login',
                'username_parameter' => '_username',
                'password_parameter' => '_password',
                'enable_csrf' => false,
                'post_only' => true,
            ]
        );

        try {
            $passport = $authenticator->authenticate($request);
            $event = new CheckPassportEvent($authenticator, $passport);
            (new UserCheckerListener(self::getContainer()->get('security.user_checker')))->preCheckCredentials($event);
            (new CheckCredentialsListener(self::getContainer()->get('security.password_hasher_factory')))->checkPassport($event);

            $badge = $passport->getBadge(PasswordCredentials::class);

            return $badge instanceof PasswordCredentials && $badge->isResolved();
        } catch (AuthenticationException) {
            return false;
        } finally {
            $stack->pop();
        }
    }

    private function canOpenMotorcyclePages(User $user): bool
    {
        return $this->accessGranted($user, Request::create('/dashboard/bike', 'GET'));
    }

    private function canStartUpdate(User $user): bool
    {
        return $this->accessGranted($user, Request::create('/admin/update/do', 'POST'));
    }

    private function accessGranted(User $user, Request $request): bool
    {
        [$attributes] = self::getContainer()->get('security.access_map')->getPatterns($request);
        if (!$attributes) {
            return false;
        }

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        return self::getContainer()->get('security.access.decision_manager')->decide($token, $attributes, $request);
    }

    private function submitUserForm(CreateUserCommand $command, string $password, bool $admin, string $oldPassword): \Symfony\Component\Form\FormInterface
    {
        $request = Request::create('/admin/user/new', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = self::getContainer()->get('request_stack');
        $stack->push($request);

        try {
            $form = self::getContainer()->get('form.factory')->create(UserType::class, $command);
            $token = $form->createView()->children['_token']->vars['value'];
            $form->submit([
                'email' => 'rider@example.test',
                'username' => 'rider-placeholder',
                'firstName' => 'Ride',
                'lastName' => 'Er',
                'mobile' => '',
                'password' => ['first' => $password, 'second' => $password],
                'oldPassword' => $oldPassword,
                'isRoleAdmin' => $admin ? '1' : null,
                '_token' => $token,
            ]);

            return $form;
        } finally {
            $stack->pop();
        }
    }

    private function creator(): User
    {
        return (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return self::getContainer()->get('security.user_password_hasher');
    }

    private function placeholder(int $length, bool $withNumberAndSymbol): string
    {
        $value = str_repeat('p', $length);
        if ($withNumberAndSymbol) {
            $value[1] = '7';
            $value[2] = '!';
        }

        return $value;
    }

    private function differentLetters(int $length): string
    {
        return str_repeat('q', $length);
    }
}

/**
 * Returns the user object account creation already produced.
 * EntityUserProvider::loadUserByIdentifier returns that persisted entity
 * without rewriting its password hash.
 */
final class FixedUserProvider implements UserProviderInterface
{
    public function __construct(private User $user)
    {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException();
        }

        return $this->user;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ($identifier !== $this->user->getUserIdentifier()) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $this->user;
    }
}
