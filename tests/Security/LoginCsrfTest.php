<?php

namespace App\Tests\Security;

use App\Entity\Bike\CreateBikeCommand;
use App\Entity\Manufacturer\CreateManufacturerCommand;
use App\Entity\Model\CreateModelCommand;
use App\Entity\Refueling\Refueling;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Login CSRF is off, so a cross-site POST of an existing account's
 * username and password signs the browser into that account.
 *
 * These tests describe the fixed behavior. They fail while the firewall
 * accepts that POST.
 */
final class LoginCsrfTest extends WebTestCase
{
	private const PLACEHOLDER_PASSWORD = 'placeholder-password';
	private const ATTACKER_USERNAME = 'attacker';
	private const CROSS_SITE_ORIGIN = 'http://evil.example';
	private const ONE_WEEK_SECONDS = 604800;

	public static function setUpBeforeClass(): void
	{
		self::useSqliteDatabase();
		self::writeAssetStubs();
		self::bootKernel();
		self::resetSchema();
		self::createAttackerAccount();
		self::ensureKernelShutdown();
	}

	public function testSameSessionTokenStillSignsIn(): void
	{
		$client = $this->loginWithTokenFromSameSession();

		$client->request('GET', '/dashboard');
		self::assertResponseIsSuccessful();
	}

	public function testCrossSiteLoginWithoutTokenDoesNotStartSession(): void
	{
		$client = $this->postCrossSiteLogin(null, false);

		$this->assertBrowserIsNotSignedIn($client);
	}

	public function testCrossSiteLoginWithEmptyTokenDoesNotStartSession(): void
	{
		$client = $this->postCrossSiteLogin('', true);

		$this->assertBrowserIsNotSignedIn($client);
		$this->assertNoWeekLongRememberMeCookie($client);
	}

	public function testCrossSiteLoginWithArbitraryTokenDoesNotStartSession(): void
	{
		$client = $this->postCrossSiteLogin('placeholder-csrf-token', true);

		$this->assertBrowserIsNotSignedIn($client);
		$this->assertNoWeekLongRememberMeCookie($client);
	}

	public function testCrossSiteLoginWithTokenFromAnotherSessionDoesNotStartSession(): void
	{
		self::ensureKernelShutdown();
		$otherBrowser = static::createClient();
		$loginPage = $otherBrowser->request('GET', '/login');
		self::assertResponseIsSuccessful();
		$tokenFromAnotherSession = $loginPage->filter('input[name="_csrf_token"]')->attr('value');
		self::assertNotSame('', $tokenFromAnotherSession);

		$client = $this->postCrossSiteLogin($tokenFromAnotherSession, true);

		$this->assertBrowserIsNotSignedIn($client);
		$this->assertNoWeekLongRememberMeCookie($client);
	}

	public function testRememberMeChoiceOnCrossSiteLoginDoesNotKeepTheSession(): void
	{
		$client = $this->postCrossSiteLogin(null, true);

		$this->assertNoWeekLongRememberMeCookie($client);
		$this->assertBrowserIsNotSignedIn($client);

		$rememberMe = $client->getCookieJar()->get('REMEMBERME');
		self::ensureKernelShutdown();
		$later = static::createClient();
		if ($rememberMe !== null && $rememberMe->getExpiresTime() > time()) {
			$later->getCookieJar()->set($rememberMe);
		}
		$later->request('GET', '/dashboard');
		self::assertResponseRedirects('/login');
	}

	public function testRefuelingSavedAfterCrossSiteLoginIsNotStoredOnTheAttackerAccount(): void
	{
		$owner = $this->loginWithTokenFromSameSession();
		$this->submitRefueling($owner, '11.111111', '22.222222', 'same-origin-marker', 10, '01. 01. 2020');
		self::assertSame(1, $this->countRefuelings('same-origin-marker'));
		$stored = $this->findRefueling('same-origin-marker');
		self::assertSame('11.111111', $this->decimalString($stored->getLatitude()));
		self::assertSame('22.222222', $this->decimalString($stored->getLongitude()));
		self::assertSame(self::ATTACKER_USERNAME, $stored->getBike()->getOwner()->getUserIdentifier());

		$victim = $this->postCrossSiteLogin(null, true);
		$this->submitRefueling($victim, '12.345678', '98.765432', 'cross-site-marker', 20, '02. 01. 2020');

		self::assertSame(0, $this->countRefuelings('cross-site-marker'));
		$this->assertBrowserIsNotSignedIn($victim);
		$this->assertNoWeekLongRememberMeCookie($victim);
	}

	private function loginWithTokenFromSameSession(): KernelBrowser
	{
		self::ensureKernelShutdown();
		$client = static::createClient();
		$loginPage = $client->request('GET', '/login');
		self::assertResponseIsSuccessful();
		$token = $loginPage->filter('input[name="_csrf_token"]')->attr('value');
		self::assertNotSame('', $token);

		$client->request('POST', '/login', [
			'_username' => self::ATTACKER_USERNAME,
			'_password' => self::PLACEHOLDER_PASSWORD,
			'_csrf_token' => $token,
		]);
		self::assertResponseRedirects('/dashboard');

		return $client;
	}

	private function postCrossSiteLogin(?string $csrfToken, bool $rememberMe): KernelBrowser
	{
		self::ensureKernelShutdown();
		$client = static::createClient();
		$parameters = [
			'_username' => self::ATTACKER_USERNAME,
			'_password' => self::PLACEHOLDER_PASSWORD,
		];
		if ($rememberMe) {
			$parameters['_remember_me'] = 'on';
		}
		if ($csrfToken !== null) {
			$parameters['_csrf_token'] = $csrfToken;
		}

		$client->request('POST', '/login', $parameters, [], [
			'HTTP_ORIGIN' => self::CROSS_SITE_ORIGIN,
			'HTTP_REFERER' => self::CROSS_SITE_ORIGIN.'/trap.html',
		]);

		return $client;
	}

	private function assertBrowserIsNotSignedIn(KernelBrowser $client): void
	{
		$client->request('GET', '/dashboard');
		self::assertResponseRedirects('/login');
	}

	private function assertNoWeekLongRememberMeCookie(KernelBrowser $client): void
	{
		$cookie = $client->getCookieJar()->get('REMEMBERME');
		$expiresAt = $cookie === null ? 0 : $cookie->getExpiresTime();
		self::assertLessThanOrEqual(
			time() + 60,
			$expiresAt,
			'A cross-site login must not leave a remember-me cookie that lasts '.self::ONE_WEEK_SECONDS.' seconds.'
		);
	}

	private function submitRefueling(KernelBrowser $client, string $latitude, string $longitude, string $comment, int $odometer, string $date): void
	{
		$crawler = $client->request('GET', '/dashboard/refueling/new');
		if (!$client->getResponse()->isSuccessful()) {
			return;
		}

		$form = $crawler->filter('form')->form();
		$form['refueling[datetime]'] = $date;
		$form['refueling[odometer]'] = (string) $odometer;
		$form['refueling[fuelQuantity]'] = '4';
		$form['refueling[price]'] = '3';
		$form['refueling[comment]'] = $comment;
		$form['refueling[latitude]'] = $latitude;
		$form['refueling[longitude]'] = $longitude;
		$form['refueling[isTankFull]']->tick();
		$form['refueling[isNotBreakingContinuum]']->tick();

		$client->submit($form);
		if (!$client->getResponse()->isRedirect()) {
			$errors = $crawler->filter('.form-error-list, .invalid-feedback, .form-alert')->each(static function ($node) {
				return trim($node->text());
			});
			$body = $client->getCrawler()->filter('body')->count() ? $client->getCrawler()->filter('body')->text() : $client->getResponse()->getContent();
			self::fail('Refueling form was not accepted. Errors: '.implode(' | ', $errors).' Body: '.substr((string) $body, 0, 2000));
		}
	}

	private function countRefuelings(string $comment): int
	{
		return (int) $this->entityManager()->createQuery(
			'SELECT COUNT(r.id) FROM '.Refueling::class.' r WHERE r.comment = :comment'
		)->setParameter('comment', $comment)->getSingleScalarResult();
	}

	private function findRefueling(string $comment): Refueling
	{
		$refueling = $this->entityManager()->getRepository(Refueling::class)->findOneBy(['comment' => $comment]);
		self::assertInstanceOf(Refueling::class, $refueling);

		return $refueling;
	}

	private function decimalString(mixed $value): string
	{
		return number_format((float) $value, 6, '.', '');
	}

	private function entityManager(): EntityManagerInterface
	{
		$manager = static::getContainer()->get('doctrine')->getManager();
		$manager->clear();

		return $manager;
	}

	private static function useSqliteDatabase(): void
	{
		$path = sys_get_temp_dir().'/bikelog-login-csrf-test.sqlite';
		if (is_file($path)) {
			unlink($path);
		}
		$url = 'sqlite:///'.$path;
		putenv('DATABASE_URL='.$url);
		$_ENV['DATABASE_URL'] = $url;
		$_SERVER['DATABASE_URL'] = $url;
	}

	private static function writeAssetStubs(): void
	{
		$directory = dirname(__DIR__, 2).'/public/build';
		if (!is_dir($directory)) {
			mkdir($directory, 0777, true);
		}
		$entrypoints = $directory.'/entrypoints.json';
		if (!is_file($entrypoints)) {
			$entries = [];
			foreach (['js/app', 'css/app', 'js/refueling/new'] as $name) {
				$entries[$name] = ['js' => [], 'css' => []];
			}
			file_put_contents($entrypoints, json_encode(['entrypoints' => $entries]));
		}
		$manifest = $directory.'/manifest.json';
		if (!is_file($manifest)) {
			file_put_contents($manifest, '{}');
		}
	}

	private static function resetSchema(): void
	{
		$manager = static::getContainer()->get('doctrine')->getManager();
		$metadata = $manager->getMetadataFactory()->getAllMetadata();
		$tool = new SchemaTool($manager);
		$tool->dropSchema($metadata);
		$tool->createSchema($metadata);
	}

	private static function createAttackerAccount(): void
	{
		$manager = static::getContainer()->get('doctrine')->getManager();
		$now = (new \DateTimeImmutable('2020-01-01 00:00:00'))->format('Y-m-d H:i:s');
		$manager->getConnection()->insert('app_users', [
			'id' => '00000000-0000-0000-0000-000000000000',
			'created_by_id' => '00000000-0000-0000-0000-000000000000',
			'username' => 'DbMigrator',
			'first_name' => 'Database',
			'last_name' => 'Migrator',
			'password' => '',
			'roles' => 'a:0:{}',
			'email' => 'migrator@example.test',
			'mobile' => null,
			'is_active' => 0,
			'created_on' => $now,
			'profile_picture_filename' => '',
		]);

		$migrator = $manager->getRepository(User::class)->find('00000000-0000-0000-0000-000000000000');
		$command = new CreateUserCommand();
		$command->username = self::ATTACKER_USERNAME;
		$command->firstName = 'A';
		$command->lastName = 'Attacker';
		$command->email = 'attacker@example.test';
		$command->mobile = null;
		$command->isRoleAdmin = false;
		$command->password = self::PLACEHOLDER_PASSWORD;
		$hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
		$attacker = new User($command, $migrator, $hasher);

		$manufacturerCommand = new CreateManufacturerCommand();
		$manufacturerCommand->name = 'Test Maker';
		$manufacturer = $attacker->createManufacturer($manufacturerCommand);

		$modelCommand = new CreateModelCommand();
		$modelCommand->name = 'Test Model';
		$modelCommand->alterName = 'Test';
		$modelCommand->displacement = 100;
		$modelCommand->yearFrom = 2020;
		$modelCommand->yearTo = 2020;
		$modelCommand->fuelTankSize = 10;
		$modelCommand->vinRanges = [];
		$model = $manufacturer->createModel($modelCommand, $attacker);

		$bikeCommand = new CreateBikeCommand();
		$bikeCommand->model = $model;
		$bikeCommand->nickname = 'Test Bike';
		$bikeCommand->purchasePrice = '1.00';
		$bikeCommand->purchaseOdometer = 0;
		$bikeCommand->purchaseDate = new \DateTimeImmutable('2020-01-01');
		$bikeCommand->year = 2020;
		$bikeCommand->vin = 'TESTVIN';
		$bikeCommand->fuelTanksize = 10;
		$bike = $attacker->createBike($bikeCommand);

		$manager->persist($attacker);
		$manager->persist($manufacturer);
		$manager->persist($model);
		$manager->persist($bike);
		$manager->flush();
	}
}
