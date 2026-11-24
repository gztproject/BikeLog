<?php

namespace App\Tests\Controller;

use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UpdatePageTest extends WebTestCase
{
    private const PASSWORD = 'placeholder';

    private static string $databasePath;

    private KernelBrowser $client;

    private bool $channelReady = false;

    private bool $channelExisted = false;

    private ?string $channelBackup = null;

    public static function setUpBeforeClass(): void
    {
        self::$databasePath = sys_get_temp_dir().'/bikelog-update-page-test.sqlite';
        self::useSqliteDatabase();
        self::writeAssetStubs();
    }

    protected function setUp(): void
    {
        self::useSqliteDatabase();
        if (is_file(self::$databasePath)) {
            unlink(self::$databasePath);
        }
        parent::setUp();

        $this->client = static::createClient();
        $this->rebuildSchema();

        $path = $this->channelPath();
        $this->channelExisted = is_file($path);
        $this->channelBackup = $this->channelExisted ? (string) file_get_contents($path) : null;
        $this->channelReady = true;
    }

    protected function tearDown(): void
    {
        if ($this->channelReady) {
            $path = $this->channelPath();
            if ($this->channelExisted) {
                file_put_contents($path, (string) $this->channelBackup);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testAdminCanOpenTheUpdatePage(): void
    {
        $this->client->loginUser($this->createUser('update-admin', true));

        $this->client->request('GET', '/admin/update');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#admin-update-panel');
        $this->assertSelectorExists('[data-channel="stable"]');
        $this->assertSelectorExists('[data-channel="prerelease"]');
        $this->assertSelectorExists('[data-step="requirements"]');
        $this->assertSelectorExists('[data-step="cache"]');
        $this->assertSelectorNotExists('[data-step="uploads"]');
        $this->assertSelectorExists('.js-update-badge');
    }

    public function testUserCannotOpenThePageOrChangeTheChannel(): void
    {
        $this->client->loginUser($this->createUser('update-user', false));

        $this->client->request('GET', '/admin/update');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/update/channel', [
            'channel' => 'prerelease',
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertChannelUnchanged();
    }

    public function testUnknownChannelIsRejected(): void
    {
        $this->client->loginUser($this->createUser('update-admin-channel', true));
        $token = $this->token();

        $this->client->request('POST', '/admin/update/channel', [
            'token' => $token,
            'channel' => 'beta',
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertChannelUnchanged();
    }

    public function testChannelChangeRequiresAValidTokenAndSavesTheChoice(): void
    {
        $this->client->loginUser($this->createUser('update-admin-save', true));

        $this->client->request('POST', '/admin/update/channel', [
            'channel' => 'prerelease',
        ]);
        $this->assertResponseStatusCodeSame(403);

        $token = $this->token();
        $this->client->request('POST', '/admin/update/channel', [
            'token' => $token,
            'channel' => 'prerelease',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('prerelease', trim((string) file_get_contents($this->channelPath())));
    }

    public function testUpdateCannotStartOutsideProductionOrWithoutAToken(): void
    {
        $this->client->loginUser($this->createUser('update-admin-run', true));

        $this->client->request('POST', '/admin/update');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/update', [
            'token' => $this->token(),
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('prod', (string) $this->client->getResponse()->getContent());
    }

    private function token(): string
    {
        $crawler = $this->client->request('GET', '/admin/update');
        $this->assertResponseIsSuccessful();

        return (string) $crawler->filter('#admin-update-panel')->attr('data-update-token');
    }

    private function assertChannelUnchanged(): void
    {
        if ($this->channelExisted) {
            $this->assertSame($this->channelBackup, (string) file_get_contents($this->channelPath()));

            return;
        }

        $this->assertFileDoesNotExist($this->channelPath());
    }

    private function channelPath(): string
    {
        return dirname(__DIR__, 2).'/var/update-channel';
    }

    private function createUser(string $username, bool $administrator): User
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $migrator = $manager->getRepository(User::class)->findOneBy(['username' => 'db-migrator']);
        if (!$migrator instanceof User) {
            $migrator = $this->createMigrator();
            $manager->persist($migrator);
            $manager->flush();
        }

        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = 'Update';
        $command->lastName = 'Tester';
        $command->email = $username.'@example.test';
        $command->mobile = '';
        $command->password = self::PASSWORD;
        $command->isRoleAdmin = $administrator;
        $command->profilePictureFilename = '';

        $user = new User($command, $migrator, $hasher);
        $manager->persist($user);
        $manager->flush();

        return $user;
    }

    private function createMigrator(): User
    {
        $migrator = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $id = Uuid::uuid6();
        $this->writeProperty(User::class, $migrator, 'id', $id);
        $this->writeProperty(\App\Entity\Base\Base::class, $migrator, 'createdOn', new \DateTime());
        $this->writeProperty(\App\Entity\Base\Base::class, $migrator, 'createdBy', $migrator);
        $this->writeProperty(User::class, $migrator, 'username', 'db-migrator');
        $this->writeProperty(User::class, $migrator, 'firstName', 'Database');
        $this->writeProperty(User::class, $migrator, 'lastName', 'Migrator');
        $this->writeProperty(User::class, $migrator, 'password', '');
        $this->writeProperty(User::class, $migrator, 'roles', ['ROLE_USER']);
        $this->writeProperty(User::class, $migrator, 'email', 'db-migrator@example.test');
        $this->writeProperty(User::class, $migrator, 'mobile', '');
        $this->writeProperty(User::class, $migrator, 'isActive', false);
        $this->writeProperty(User::class, $migrator, 'profilePictureFilename', '');
        $this->writeProperty(User::class, $migrator, 'bikes', new \Doctrine\Common\Collections\ArrayCollection());
        $this->writeProperty(User::class, $migrator, 'workshops', new \Doctrine\Common\Collections\ArrayCollection());
        $this->writeProperty(User::class, $migrator, 'ownedWorkshops', new \Doctrine\Common\Collections\ArrayCollection());

        return $migrator;
    }

    private function writeProperty(string $class, object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    private function rebuildSchema(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private static function useSqliteDatabase(): void
    {
        $url = 'sqlite:///'.self::$databasePath;
        putenv('DATABASE_URL='.$url);
        $_ENV['DATABASE_URL'] = $url;
        $_SERVER['DATABASE_URL'] = $url;
    }

    private static function writeAssetStubs(): void
    {
        $buildDir = dirname(__DIR__, 2).'/public/build';
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0777, true);
        }
        file_put_contents($buildDir.'/entrypoints.json', json_encode(['entrypoints' => new \stdClass()]));
        file_put_contents($buildDir.'/manifest.json', json_encode(new \stdClass()));
    }
}
