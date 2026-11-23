<?php

namespace App\Tests\EventSubscriber;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Disallowed locales must be ignored before the request locale is stored and
 * before the translator writes a catalogue. These tests fail while a locale
 * outside the configured languages (en, sl) is still applied.
 */
final class LocaleCacheExhaustionTest extends WebTestCase
{
	private const ENABLED_LOCALES = ['en', 'sl'];

	public function testSignInQueryLocaleDoesNotLeaveACatalogue(): void
	{
		$locale = self::freshLocale();
		$client = static::createClient();

		$client->request('GET', '/login', ['_locale' => $locale]);

		self::assertDisallowedLocaleIgnored($client, $locale);
		$client->request('GET', '/login');
		self::assertDisallowedLocaleIgnored($client, $locale);
	}

	public function testHomepageQueryLocaleDoesNotLeaveACatalogue(): void
	{
		$locale = self::freshLocale();
		$client = static::createClient();

		$client->request('GET', '/', ['_locale' => $locale]);

		self::assertDisallowedLocaleIgnored($client, $locale);
		$client->request('GET', '/');
		self::assertDisallowedLocaleIgnored($client, $locale);
	}

	public function testSignInFormBodyLocaleDoesNotLeaveACatalogue(): void
	{
		$locale = self::freshLocale();
		$client = static::createClient();

		$client->request('POST', '/login', ['_locale' => $locale]);
		self::assertSame([], self::catalogueFiles($locale), 'The sign-in form stored a catalogue for a disallowed locale before the following page render.');

		$client->request('GET', '/login');
		self::assertDisallowedLocaleIgnored($client, $locale);
	}

	public function testTranslatorSymbolLocaleDoesNotLeaveACatalogue(): void
	{
		$locale = 'q7-zz.a@b'.bin2hex(random_bytes(2));
		$client = static::createClient();

		$client->request('GET', '/login', ['_locale' => $locale]);

		self::assertDisallowedLocaleIgnored($client, $locale);
		$client->request('GET', '/login');
		self::assertDisallowedLocaleIgnored($client, $locale);
	}

	public function testPreviouslyStoredSessionLocaleDoesNotLeaveACatalogue(): void
	{
		$locale = self::freshLocale();
		$client = static::createClient();
		$client->request('GET', '/login', ['_locale' => 'en']);

		$session = $client->getRequest()->getSession();
		$session->set('_locale', $locale);
		$session->save();

		$client->request('GET', '/login');

		self::assertDisallowedLocaleIgnored($client, $locale, false);
	}

	public function testConfiguredLanguagesAreStillAccepted(): void
	{
		foreach (self::ENABLED_LOCALES as $locale) {
			self::ensureKernelShutdown();
			$client = static::createClient();
			$client->request('GET', '/login', ['_locale' => $locale]);

			self::assertSame(200, $client->getResponse()->getStatusCode());
			self::assertNotFalse($client->getResponse()->getContent());
			self::assertStringContainsString('<html lang="'.$locale.'"', (string) $client->getResponse()->getContent());
			self::assertSame($locale, $client->getRequest()->getSession()->get('_locale'));
		}
	}

	private static function assertDisallowedLocaleIgnored(KernelBrowser $client, string $locale, bool $requireSessionCleared = true): void
	{
		$response = $client->getResponse();
		$content = (string) $response->getContent();
		$problems = [];

		if ($response->getStatusCode() !== 200) {
			$problems[] = 'sign-in page returned HTTP '.$response->getStatusCode();
		}
		if (!preg_match('/<html lang="(?:en|sl)"/', $content)) {
			$problems[] = 'response locale was not limited to en or sl';
		}
		if (str_contains($content, '<html lang="'.$locale.'"')) {
			$problems[] = 'response locale was the disallowed value';
		}

		if ($requireSessionCleared && $client->getRequest()->hasSession(true)) {
			$stored = $client->getRequest()->getSession()->get('_locale');
			if ($stored !== null && !in_array($stored, self::ENABLED_LOCALES, true)) {
				$problems[] = 'session kept the disallowed locale';
			}
		}

		$problems = array_merge($problems, self::catalogueFiles($locale));

		self::assertSame([], $problems);
	}

	/**
	 * @return list<string>
	 */
	private static function catalogueFiles(string $locale): array
	{
		$directory = static::getContainer()->getParameter('kernel.cache_dir').'/translations';
		if (!is_dir($directory)) {
			return [];
		}

		$matches = glob($directory.'/catalogue.'.$locale.'.*');
		if ($matches === false) {
			return [];
		}

		$files = [];
		foreach ($matches as $match) {
			$files[] = $match.' ('.(is_file($match) ? (string) filesize($match) : '0').' bytes)';
		}

		return $files;
	}

	private static function freshLocale(): string
	{
		return 'q7'.bin2hex(random_bytes(6));
	}
}
