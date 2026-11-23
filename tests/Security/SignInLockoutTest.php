<?php

namespace App\Tests\Security;

use App\Entity\Bike\Bike;
use App\Entity\Bike\CreateBikeCommand;
use App\Entity\Manufacturer\CreateManufacturerCommand;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Model\CreateModelCommand;
use App\Entity\Model\Model;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\UpdateUserCommand;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Sign-in must stop accepting guesses, and accounts must reject short passwords.
 *
 * These tests fail while the main firewall has no login throttling and while
 * User::checkPasswordRequirements allows a four-character password. They pass
 * once the main firewall enables Symfony login throttling at its defaults
 * (five attempts per username and client address, twenty-five attempts per
 * client address, both inside one minute) and account create/update reject
 * any password shorter than eight characters.
 */
final class SignInLockoutTest extends WebTestCase
{
    private const USERNAME_ATTEMPT_LIMIT = 5;

    private const ADDRESS_ATTEMPT_LIMIT = 25;

    private const MINIMUM_PASSWORD_LENGTH = 8;

    private const PLACEHOLDER_PASSWORD = 'placeholder';

    private const EIGHT_CHARACTER_PLACEHOLDER = 'xxxxxxxx';

    private const SEVEN_CHARACTER_PLACEHOLDER = 'xxxxxxx';

    private const FOUR_CHARACTER_PLACEHOLDER = 'xxxx';

    private const WRONG_GUESS = 'wrong-guess';

    private const VIN = 'PLACEHOLDERVIN0001';

    private const LATITUDE = '12.345678';

    private const LONGITUDE = '98.765432';

    private static string $databasePath;

    public static function setUpBeforeClass(): void
    {
        self::$databasePath = dirname(__DIR__, 2).'/var/signin-lockout-test.sqlite';
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
    }

    public function testSignInStopsAfterFiveFailuresForTheSameUsernameAndAddress(): void
    {
        $client = $this->bootClient();
        $rider = $this->createRider('lockout-rider', self::PLACEHOLDER_PASSWORD, true);
        $bikeId = $this->giveRiderAMotorcycle($rider);

        $this->submitSignIn($client, 'lockout-rider', self::PLACEHOLDER_PASSWORD);
        self::assertResponseRedirects('/dashboard');

        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(self::VIN, $client->getResponse()->getContent());

        $client->request('GET', '/dashboard/refueling?bike='.$bikeId);
        self::assertResponseIsSuccessful();
        $refuelingPage = $client->getResponse()->getContent();
        self::assertStringContainsString(self::LATITUDE, $refuelingPage);
        self::assertStringContainsString(self::LONGITUDE, $refuelingPage);

        $client->request('GET', '/admin/update');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('admin-update-panel', $client->getResponse()->getContent());

        $client->request('GET', '/logout');

        for ($attempt = 1; $attempt <= self::USERNAME_ATTEMPT_LIMIT; ++$attempt) {
            $this->submitSignIn($client, 'lockout-rider', self::WRONG_GUESS);
            $this->assertAuthenticationFailure($client, BadCredentialsException::class, 'Attempt '.$attempt.' was not a normal credential failure');
        }

        $this->submitSignIn($client, 'lockout-rider', self::PLACEHOLDER_PASSWORD);
        $this->assertAuthenticationFailure(
            $client,
            TooManyLoginAttemptsAuthenticationException::class,
            'The correct password was still accepted after the attempt limit'
        );

        $client->request('GET', '/dashboard');
        self::assertResponseRedirects('/login');
        self::assertStringNotContainsString(self::VIN, (string) $client->getResponse()->getContent());

        $client->request('GET', '/dashboard/refueling?bike='.$bikeId);
        self::assertResponseRedirects('/login');
        self::assertStringNotContainsString(self::LATITUDE, (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/update');
        self::assertResponseRedirects('/login');
        self::assertStringNotContainsString('admin-update-panel', (string) $client->getResponse()->getContent());
    }

    public function testFailuresForOneUsernameDoNotLockADifferentUsername(): void
    {
        $client = $this->bootClient();
        $this->createRider('first-rider', self::PLACEHOLDER_PASSWORD, false);
        $this->createRider('second-rider', self::PLACEHOLDER_PASSWORD, false);

        for ($attempt = 1; $attempt <= self::USERNAME_ATTEMPT_LIMIT; ++$attempt) {
            $this->submitSignIn($client, 'first-rider', self::WRONG_GUESS);
        }

        $this->submitSignIn($client, 'second-rider', self::WRONG_GUESS);
        $this->assertAuthenticationFailure(
            $client,
            BadCredentialsException::class,
            'A different username was locked by the first username from the same address'
        );
    }

    public function testOneAddressCannotKeepGuessingAcrossUsernames(): void
    {
        $client = $this->bootClient();
        $usernames = [];
        for ($index = 1; $index <= 6; ++$index) {
            $usernames[] = 'address-rider-'.$index;
            $this->createRider('address-rider-'.$index, self::PLACEHOLDER_PASSWORD, false);
        }

        $submitted = 0;
        foreach (array_slice($usernames, 0, 5) as $username) {
            for ($attempt = 1; $attempt <= self::USERNAME_ATTEMPT_LIMIT; ++$attempt) {
                $this->submitSignIn($client, $username, self::WRONG_GUESS);
                ++$submitted;
            }
        }
        self::assertSame(self::ADDRESS_ATTEMPT_LIMIT, $submitted);

        $this->submitSignIn($client, $usernames[5], self::PLACEHOLDER_PASSWORD);
        $this->assertAuthenticationFailure(
            $client,
            TooManyLoginAttemptsAuthenticationException::class,
            'The address-wide attempt budget still accepted another username'
        );

        $client->request('GET', '/dashboard');
        self::assertResponseRedirects('/login');
    }

    public function testPasswordsShorterThanEightCharactersAreRejectedOnCreateAndUpdate(): void
    {
        $client = $this->bootClient();
        $migrator = $this->createMigrator();
        $hasher = $this->hasher();

        foreach ([self::FOUR_CHARACTER_PLACEHOLDER, self::SEVEN_CHARACTER_PLACEHOLDER] as $password) {
            $accepted = false;
            try {
                $this->newUser($migrator, 'short-'.$password, $password, false);
                $accepted = true;
            } catch (\Exception $exception) {
                $accepted = false;
            }
            self::assertFalse($accepted, 'Creating an account accepted a password shorter than '.self::MINIMUM_PASSWORD_LENGTH.' characters.');
        }

        $rider = $this->newUser($migrator, 'long-enough-rider', self::EIGHT_CHARACTER_PLACEHOLDER, true);
        $this->manager()->persist($rider);
        $this->manager()->flush();
        $originalHash = $rider->getPassword();

        foreach ([self::FOUR_CHARACTER_PLACEHOLDER, self::SEVEN_CHARACTER_PLACEHOLDER] as $password) {
            $update = new UpdateUserCommand();
            $update->password = $password;
            $update->oldPassword = self::EIGHT_CHARACTER_PLACEHOLDER;
            $accepted = false;
            try {
                $rider->update($update, $migrator, $hasher);
                $accepted = true;
            } catch (\Exception $exception) {
                $accepted = false;
            }
            self::assertFalse($accepted, 'Updating an account accepted a password shorter than '.self::MINIMUM_PASSWORD_LENGTH.' characters.');
            self::assertSame($originalHash, $rider->getPassword());
        }

        $client->loginUser($rider, 'main');
        $this->submitNewUserForm($client, 'form-short-rider', self::FOUR_CHARACTER_PLACEHOLDER);
        self::assertResponseIsSuccessful();
        self::assertNull($this->manager()->getRepository(User::class)->findOneBy(['username' => 'form-short-rider']));

        $this->submitNewUserForm($client, 'form-long-rider', self::EIGHT_CHARACTER_PLACEHOLDER);
        self::assertResponseRedirects('/admin/user');
        self::assertNotNull($this->manager()->getRepository(User::class)->findOneBy(['username' => 'form-long-rider']));

        $crawler = $client->request('GET', '/user/'.$rider->getId().'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('button.btn-primary')->form();
        $form['user[password][first]'] = self::SEVEN_CHARACTER_PLACEHOLDER;
        $form['user[password][second]'] = self::SEVEN_CHARACTER_PLACEHOLDER;
        $form['user[oldPassword]'] = self::EIGHT_CHARACTER_PLACEHOLDER;
        $client->submit($form);

        self::assertResponseIsSuccessful();
        $this->manager()->clear();
        $reloaded = $this->manager()->getRepository(User::class)->find($rider->getId());
        self::assertSame($originalHash, $reloaded->getPassword());
    }

    private function submitNewUserForm(KernelBrowser $client, string $username, string $password): void
    {
        $crawler = $client->request('GET', '/admin/user/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('button.btn-primary')->form();
        $form['user[username]'] = $username;
        $form['user[email]'] = $username.'@example.test';
        $form['user[firstName]'] = 'Form';
        $form['user[lastName]'] = 'Rider';
        $form['user[password][first]'] = $password;
        $form['user[password][second]'] = $password;
        $client->submit($form);
    }

    private function bootClient(): KernelBrowser
    {
        $client = static::createClient();
        $this->rebuildSchema();
        $this->clearLoginThrottleState();

        return $client;
    }

    private function submitSignIn(KernelBrowser $client, string $username, string $password): void
    {
        $loginPage = $client->request('GET', '/login');
        $client->request('POST', '/login', [
            '_username' => $username,
            '_password' => $password,
            '_csrf_token' => $loginPage->filter('input[name="_csrf_token"]')->attr('value'),
        ]);
    }

    private function assertAuthenticationFailure(KernelBrowser $client, string $exceptionClass, string $message): void
    {
        $response = $client->getResponse();
        $location = (string) $response->headers->get('Location');
        self::assertTrue($response->isRedirect(), $message.' (status '.$response->getStatusCode().')');
        self::assertStringContainsString('/login', $location, $message.' (redirected to '.$location.')');
        self::assertStringNotContainsString('/dashboard', $location, $message);

        $error = $client->getRequest()->getSession()->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        self::assertInstanceOf($exceptionClass, $error, $message);
    }

    private function createRider(string $username, string $password, bool $administrator): User
    {
        $migrator = $this->manager()->getRepository(User::class)->findOneBy(['username' => 'db-migrator']);
        if (!$migrator instanceof User) {
            $migrator = $this->createMigrator();
        }

        $rider = $this->newUser($migrator, $username, $password, $administrator);
        $this->manager()->persist($rider);
        $this->manager()->flush();

        return $rider;
    }

    private function newUser(User $creator, string $username, string $password, bool $administrator): User
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = 'Placeholder';
        $command->lastName = 'Rider';
        $command->email = $username.'@example.test';
        $command->mobile = '';
        $command->password = $password;
        $command->isRoleAdmin = $administrator;
        $command->profilePictureFilename = '';

        return new User($command, $creator, $this->hasher());
    }

    private function giveRiderAMotorcycle(User $rider): string
    {
        $manufacturerCommand = new CreateManufacturerCommand();
        $manufacturerCommand->name = 'Placeholder Maker';
        $manufacturer = new Manufacturer($manufacturerCommand, $rider);

        $modelCommand = new CreateModelCommand();
        $modelCommand->name = 'Placeholder';
        $modelCommand->alterName = 'Model';
        $modelCommand->displacement = 125;
        $modelCommand->yearFrom = 2020;
        $modelCommand->yearTo = 2026;
        $modelCommand->vinRanges = [];
        $modelCommand->fuelTankSize = 12;
        $modelCommand->pictureFilename = 'placeholder.png';
        $model = new Model($modelCommand, $manufacturer, $rider);

        $bikeCommand = new CreateBikeCommand();
        $bikeCommand->model = $model;
        $bikeCommand->nickname = 'Placeholder Bike';
        $bikeCommand->purchasePrice = '1000.00';
        $bikeCommand->purchaseOdometer = 0;
        $bikeCommand->purchaseDate = new \DateTime('2024-01-01');
        $bikeCommand->year = 2024;
        $bikeCommand->vin = self::VIN;
        $bikeCommand->pictureFilename = 'placeholder.png';
        $bikeCommand->fuelTanksize = 12;
        $bike = new Bike($bikeCommand, $rider);

        $refueling = new CreateRefuelingCommand();
        $refueling->datetime = new \DateTime('2024-02-01 10:00:00');
        $refueling->odometer = 120;
        $refueling->fuelQuantity = '10.00';
        $refueling->price = '15.00';
        $refueling->isTankFull = true;
        $refueling->isNotBreakingContinuum = false;
        $refueling->latitude = self::LATITUDE;
        $refueling->longitude = self::LONGITUDE;
        $bike->createRefueling($refueling, $rider);

        $manager = $this->manager();
        $manager->persist($manufacturer);
        $manager->persist($model);
        $manager->persist($bike);
        $manager->flush();

        return (string) $bike->getId();
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

        $this->manager()->persist($migrator);
        $this->manager()->flush();

        return $migrator;
    }

    private function writeProperty(string $class, object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($class, $property);
        $reflection->setValue($object, $value);
    }

    private function rebuildSchema(): void
    {
        $manager = $this->manager();
        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private function clearLoginThrottleState(): void
    {
        $container = static::getContainer();
        if ($container->has('cache.rate_limiter')) {
            $container->get('cache.rate_limiter')->clear();
        }
    }

    private function manager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private static function useSqliteDatabase(): void
    {
        $url = 'sqlite:///'.self::$databasePath;
        $_SERVER['DATABASE_URL'] = $url;
        $_ENV['DATABASE_URL'] = $url;
        putenv('DATABASE_URL='.$url);
        if (!is_dir(dirname(self::$databasePath))) {
            mkdir(dirname(self::$databasePath), 0777, true);
        }
    }

    private static function writeAssetStubs(): void
    {
        $buildDir = dirname(__DIR__, 2).'/public/build';
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0777, true);
        }
        file_put_contents($buildDir.'/entrypoints.json', json_encode(['entrypoints' => new \stdClass()]));
        file_put_contents($buildDir.'/manifest.json', json_encode([
            'favicon.ico' => 'favicon.ico',
            'img/plus.png' => 'img/plus.png',
            'uploads/bikes/placeholder.png' => 'uploads/bikes/placeholder.png',
            'uploads/models/placeholder.png' => 'uploads/models/placeholder.png',
        ]));
    }
}
