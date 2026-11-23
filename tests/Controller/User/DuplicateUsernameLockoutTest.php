<?php

namespace App\Tests\Controller\User;

use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Spec for the duplicate-username lockout. These tests fail while a signed-in
 * rider can store another account's username and sign-in still accepts one of
 * the two passwords.
 */
class DuplicateUsernameLockoutTest extends WebTestCase
{
    private const VICTIM_USERNAME = 'victim-rider';
    private const ATTACKER_USERNAME = 'attacker-rider';
    private const ADMIN_USERNAME = 'admin-rider';
    private const VICTIM_PASSWORD = 'placeholder-victim-password';
    private const ATTACKER_PASSWORD = 'placeholder-attacker-password';
    private const ADMIN_PASSWORD = 'placeholder-admin-password';
    private const CREATED_PASSWORD = 'placeholder-created-user-password';

    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir().'/bikelog-duplicate-username-'.getmypid().'.sqlite';
        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
        $this->useDatabase($this->databasePath);
        self::ensureKernelShutdown();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
        parent::tearDown();
    }

    public function testOwnerProfileSaveRejectsAnotherAccountsUsernameWithoutAPassword(): void
    {
        $users = $this->seedAccounts();
        $client = static::createClient();
        $this->signIn($client, self::ATTACKER_USERNAME, self::ATTACKER_PASSWORD);

        $this->submitOwnProfile($client, $users['attacker'], [
            'username' => self::VICTIM_USERNAME,
            'password' => '',
            'oldPassword' => '',
        ]);

        $this->assertUsernameUnchanged($users['attacker'], self::ATTACKER_USERNAME);
        $this->assertSingleAccount(self::VICTIM_USERNAME, $users['victim']);

        $client->restart();
        $this->signIn($client, self::VICTIM_USERNAME, self::VICTIM_PASSWORD);
    }

    public function testOwnerProfileSaveRejectsAnotherAccountsUsernameEvenWithCurrentPassword(): void
    {
        $users = $this->seedAccounts();
        $client = static::createClient();
        $this->signIn($client, self::ATTACKER_USERNAME, self::ATTACKER_PASSWORD);

        $this->submitOwnProfile($client, $users['attacker'], [
            'username' => self::VICTIM_USERNAME,
            'password' => '',
            'oldPassword' => self::ATTACKER_PASSWORD,
        ]);

        $this->assertUsernameUnchanged($users['attacker'], self::ATTACKER_USERNAME);
        $this->assertSingleAccount(self::VICTIM_USERNAME, $users['victim']);

        $client->restart();
        $this->signIn($client, self::VICTIM_USERNAME, self::VICTIM_PASSWORD);
    }

    public function testUsernameChangeRequiresTheCurrentPassword(): void
    {
        $users = $this->seedAccounts();
        $client = static::createClient();
        $this->signIn($client, self::ATTACKER_USERNAME, self::ATTACKER_PASSWORD);

        $this->submitOwnProfile($client, $users['attacker'], [
            'username' => 'renamed-rider',
            'password' => '',
            'oldPassword' => '',
        ]);

        $this->assertUsernameUnchanged($users['attacker'], self::ATTACKER_USERNAME);
    }

    public function testOwnerCanStillSaveUnrelatedProfileFieldsWithoutTheCurrentPassword(): void
    {
        $users = $this->seedAccounts();
        $client = static::createClient();
        $this->signIn($client, self::ATTACKER_USERNAME, self::ATTACKER_PASSWORD);

        $this->submitOwnProfile($client, $users['attacker'], [
            'username' => self::ATTACKER_USERNAME,
            'mobile' => '5550001111',
            'password' => '',
            'oldPassword' => '',
        ]);

        $reloaded = $this->reload($users['attacker']);
        self::assertSame('5550001111', $reloaded->getMobile());
        self::assertSame(self::ATTACKER_USERNAME, $reloaded->getUsername());
    }

    public function testAdminCreateRejectsAUsernameThatAlreadyExists(): void
    {
        $users = $this->seedAccounts();
        $client = static::createClient();
        $this->signIn($client, self::ADMIN_USERNAME, self::ADMIN_PASSWORD);

        $crawler = $client->request('GET', '/admin/user/new');
        self::assertResponseIsSuccessful();
        $form = $this->profileForm($crawler);
        $this->setFieldEndingWith($form, '[username]', self::VICTIM_USERNAME);
        $this->setFieldEndingWith($form, '[email]', 'created@example.test');
        $this->setFieldEndingWith($form, '[firstName]', 'Created');
        $this->setFieldEndingWith($form, '[lastName]', 'Rider');
        $this->setFieldEndingWith($form, '[password][first]', self::CREATED_PASSWORD);
        $this->setFieldEndingWith($form, '[password][second]', self::CREATED_PASSWORD);
        $this->submitFormAllowingRejection($client, $form);

        $this->assertSingleAccount(self::VICTIM_USERNAME, $users['victim']);
        $em = $this->entityManager();
        self::assertNull($em->getRepository(User::class)->findOneBy(['email' => 'created@example.test']));

        $client->restart();
        $this->signIn($client, self::VICTIM_USERNAME, self::VICTIM_PASSWORD);
    }

    public function testDatabaseRejectsASecondAccountWithTheSameUsername(): void
    {
        $this->seedAccounts();
        $em = $this->entityManager();
        $migrator = $em->find(User::class, '00000000-0000-0000-0000-000000000000');
        self::assertInstanceOf(User::class, $migrator);

        $duplicate = $migrator->createUser(
            $this->command('temporary-rider', 'duplicate@example.test', self::CREATED_PASSWORD, false),
            $this->hasher()
        );
        $username = new \ReflectionProperty(User::class, 'username');
        $username->setValue($duplicate, self::VICTIM_USERNAME);
        $em->persist($duplicate);

        $this->expectException(UniqueConstraintViolationException::class);
        $em->flush();
    }

    public function testSignInFailsClosedWhenTwoAccountsAlreadyShareAUsername(): void
    {
        $users = $this->seedAccounts(true);
        $client = static::createClient();
        $this->forceSharedUsername($users['attacker'], self::VICTIM_USERNAME);

        $loadedId = 'none';
        try {
            $loaded = static::getContainer()->get('security.user.provider.concrete.sql_db_provider')->loadUserByIdentifier(self::VICTIM_USERNAME);
            $loadedId = (string) $loaded->getId();
        } catch (\Throwable $e) {
            $loadedId = $e::class;
        }

        $victimRejected = $this->loginRejected($client, self::VICTIM_USERNAME, self::VICTIM_PASSWORD);
        $client->restart();
        $attackerRejected = $this->loginRejected($client, self::VICTIM_USERNAME, self::ATTACKER_PASSWORD);

        self::assertTrue(
            $victimRejected && $attackerRejected,
            sprintf(
                'Sign-in must refuse a username that matches more than one account. victimRejected=%s attackerRejected=%s loadedAccount=%s attackerAccount=%s victimAccount=%s',
                $victimRejected ? 'yes' : 'no',
                $attackerRejected ? 'yes' : 'no',
                $loadedId,
                $users['attacker'],
                $users['victim']
            )
        );
    }

    /**
     * @return array{victim: string, attacker: string, admin: string}
     */
    private function seedAccounts(bool $attackerBeforeVictim = false): array
    {
        $client = static::createClient();
        $em = $this->entityManager();
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $connection = $em->getConnection();
        $connection->insert('app_users', [
            'id' => '00000000-0000-0000-0000-000000000000',
            'created_by_id' => '00000000-0000-0000-0000-000000000000',
            'username' => 'placeholder-migrator',
            'first_name' => 'Database',
            'last_name' => 'Migrator',
            'password' => '',
            'roles' => ['ROLE_USER'],
            'email' => 'migrator@example.test',
            'mobile' => null,
            'is_active' => false,
            'created_on' => new \DateTimeImmutable('2026-01-01 00:00:00'),
            'updated_on' => null,
            'updated_by_id' => null,
            'profile_picture_filename' => '',
        ], [
            'roles' => 'array',
            'is_active' => 'boolean',
            'created_on' => 'datetime',
            'updated_on' => 'datetime',
        ]);

        $migrator = $em->find(User::class, '00000000-0000-0000-0000-000000000000');
        self::assertInstanceOf(User::class, $migrator);

        $make = function (string $username, string $email, string $password, bool $admin) use ($migrator, $em): string {
            $user = $migrator->createUser($this->command($username, $email, $password, $admin), $this->hasher());
            $em->persist($user);
            $em->flush();

            return (string) $user->getId();
        };

        $ids = [];
        if ($attackerBeforeVictim) {
            $ids['attacker'] = $make(self::ATTACKER_USERNAME, 'attacker@example.test', self::ATTACKER_PASSWORD, false);
            $ids['victim'] = $make(self::VICTIM_USERNAME, 'victim@example.test', self::VICTIM_PASSWORD, false);
        } else {
            $ids['victim'] = $make(self::VICTIM_USERNAME, 'victim@example.test', self::VICTIM_PASSWORD, false);
            $ids['attacker'] = $make(self::ATTACKER_USERNAME, 'attacker@example.test', self::ATTACKER_PASSWORD, false);
        }
        $ids['admin'] = $make(self::ADMIN_USERNAME, 'admin@example.test', self::ADMIN_PASSWORD, true);

        $client->restart();
        self::ensureKernelShutdown();

        return $ids;
    }

    private function command(string $username, string $email, string $password, bool $admin): CreateUserCommand
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->email = $email;
        $command->firstName = 'Test';
        $command->lastName = 'Rider';
        $command->mobile = '5550000000';
        $command->password = $password;
        $command->isRoleAdmin = $admin;
        $command->profilePictureFilename = '';

        return $command;
    }

    /**
     * @param array{username?: string, email?: string, mobile?: string, password?: string, oldPassword?: string} $overrides
     */
    private function submitOwnProfile(KernelBrowser $client, string $userId, array $overrides): void
    {
        $crawler = $client->request('GET', '/user/'.$userId.'/edit');
        self::assertResponseIsSuccessful('Owner profile edit was not reachable. Status '.$client->getResponse()->getStatusCode().' body '.substr((string) $client->getResponse()->getContent(), 0, 1500));
        $form = $this->profileForm($crawler);
        foreach ($overrides as $field => $value) {
            if ('password' === $field) {
                $this->setFieldEndingWith($form, '[password][first]', $value);
                $this->setFieldEndingWith($form, '[password][second]', $value);
                continue;
            }
            $this->setFieldEndingWith($form, '['.$field.']', $value);
        }
        $this->submitFormAllowingRejection($client, $form);
    }

    private function submitFormAllowingRejection(KernelBrowser $client, Form $form): void
    {
        try {
            $client->submit($form);
        } catch (\Throwable $e) {
            self::ensureKernelShutdown();
            $this->useDatabase($this->databasePath);
            static::createClient();
        }
    }

    private function signIn(KernelBrowser $client, string $username, string $password): void
    {
        $client->request('POST', '/login', [
            '_username' => $username,
            '_password' => $password,
            '_csrf_token' => $this->loginCsrfToken($client),
        ]);
        self::assertTrue($client->getResponse()->isRedirect(), 'Sign-in did not redirect. Status '.$client->getResponse()->getStatusCode().' body '.substr((string) $client->getResponse()->getContent(), 0, 800));
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/dashboard', $location, 'Sign-in was rejected. Location: '.$location);
    }

    private function loginCsrfToken(KernelBrowser $client): string
    {
        return (string) $client->request('GET', '/login')->filter('input[name="_csrf_token"]')->attr('value');
    }

    private function loginRejected(KernelBrowser $client, string $username, string $password): bool
    {
        try {
            $client->request('POST', '/login', [
                '_username' => $username,
                '_password' => $password,
                '_csrf_token' => $this->loginCsrfToken($client),
            ]);
        } catch (\Throwable $e) {
            return true;
        }

        if (!$client->getResponse()->isRedirect()) {
            return true;
        }

        return !str_contains((string) $client->getResponse()->headers->get('Location'), '/dashboard');
    }

    private function assertUsernameUnchanged(string $userId, string $username): void
    {
        self::assertSame($username, $this->reload($userId)->getUsername());
    }

    private function assertSingleAccount(string $username, string $expectedId): void
    {
        $matches = $this->entityManager()->getRepository(User::class)->findBy(['username' => $username]);
        self::assertCount(1, $matches);
        self::assertSame($expectedId, (string) $matches[0]->getId());
    }

    private function reload(string $userId): User
    {
        $em = $this->entityManager();
        $em->clear();
        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function forceSharedUsername(string $attackerId, string $username): void
    {
        $em = $this->entityManager();
        $connection = $em->getConnection();
        $schemaManager = $connection->createSchemaManager();
        if ($schemaManager->tablesExist(['app_users'])) {
            foreach ($schemaManager->listTableIndexes('app_users') as $index) {
                $columns = array_map('strtolower', $index->getColumns());
                if ($index->isUnique() && in_array('username', $columns, true)) {
                    $schemaManager->dropIndex($index->getName(), 'app_users');
                }
            }
        }

        $updated = $connection->executeStatement(
            'UPDATE app_users SET username = ? WHERE id = ?',
            [$username, $attackerId]
        );
        self::assertSame(1, $updated);
        $em->clear();
    }

    private function profileForm(\Symfony\Component\DomCrawler\Crawler $crawler): Form
    {
        $count = $crawler->filter('form')->count();
        for ($i = 0; $i < $count; ++$i) {
            $form = $crawler->filter('form')->eq($i)->form();
            foreach (array_keys($form->getValues()) as $name) {
                if (str_ends_with($name, '[username]')) {
                    return $form;
                }
            }
        }

        self::fail('Profile form was not rendered. URI '.$crawler->getUri().' body '.substr($crawler->html(), 0, 1500));
    }

    private function setFieldEndingWith(Form $form, string $suffix, string $value): void
    {
        foreach (array_keys($form->getValues()) as $name) {
            if (str_ends_with($name, $suffix)) {
                $form[$name] = $value;

                return;
            }
        }

        self::fail('Missing form field ending with '.$suffix.'. Fields: '.implode(', ', array_keys($form->getValues())));
    }

    private function entityManager(): EntityManagerInterface
    {
        if (!static::$booted) {
            static::createClient();
        }

        $doctrine = static::getContainer()->get('doctrine');
        $manager = $doctrine->getManager();
        if (!$manager->isOpen()) {
            $manager = $doctrine->resetManager();
        }

        return $manager;
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private function useDatabase(string $path): void
    {
        $url = 'sqlite:///'.$path;
        putenv('DATABASE_URL='.$url);
        $_ENV['DATABASE_URL'] = $url;
        $_SERVER['DATABASE_URL'] = $url;
    }
}
