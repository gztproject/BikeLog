<?php

namespace App\Tests\Security;

if (!class_exists(\PHPUnit\Framework\TestCase::class)) {
	require dirname(__DIR__, 2).'/vendor/autoload.php';
}

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * Anonymous clients must not be able to read the profiler when the process
 * leaves APP_ENV unset, which is how the committed environment file boots.
 *
 * These assertions fail while that profiler is still reachable.
 */
class UnauthenticatedProfilerAccessTest extends TestCase
{
	private const REMOTE_ADDRESS = '203.0.113.10';
	private const PLACEHOLDER_SECRET = 'placeholder-app-secret';
	private const PLACEHOLDER_USERNAME = 'placeholder-rider';
	private const PLACEHOLDER_PASSWORD = 'placeholder!password';

	public function testAnonymousRemoteClientCannotReadTheProfilerWhenAppEnvIsUnset(): void
	{
		$projectDir = dirname(__DIR__, 2);
		$command = [
			PHP_BINARY,
			__FILE__,
		];
		$environment = [
			'PATH' => getenv('PATH') ?: '/usr/bin',
			'HOME' => getenv('HOME') ?: '/tmp',
			'BIKELOG_PROFILER_PROBE' => '1',
			'APP_SECRET' => self::PLACEHOLDER_SECRET,
		];

		$pipes = [];
		$process = proc_open($command, [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		], $pipes, $projectDir, $environment);
		self::assertIsResource($process);
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exitCode = proc_close($process);

		$report = json_decode($stdout, true);
		self::assertIsArray($report, sprintf(
			"Profiler probe did not return JSON (exit %d).\nstdout:\n%s\nstderr:\n%s",
			$exitCode,
			self::excerpt($stdout),
			self::excerpt($stderr)
		));

		$violations = [];
		if (isset($report['boot_error'])) {
			$violations[] = 'boot: '.$report['boot_error'];
		}
		foreach ($report['responses'] ?? [] as $name => $response) {
			$leaks = $response['leak_names'] ?? [];
			$denied = (bool) ($response['denied'] ?? false);
			if ($denied && $leaks === []) {
				continue;
			}
			$violations[] = sprintf(
				'%s status=%s location=%s leaks=%s',
				$name,
				$response['status'] ?? 'none',
				$response['location'] ?? '',
				$leaks === [] ? 'none' : implode(',', $leaks)
			);
		}

		self::assertSame([], $violations, "Anonymous profiler access is still open:\n".implode("\n", $violations));
	}

	public static function probe(): void
	{
		ini_set('display_errors', '0');
		ob_start();
		try {
			$report = self::runProbe();
			ob_end_clean();
			echo json_encode($report, JSON_UNESCAPED_SLASHES);
		} catch (\Throwable $error) {
			if (ob_get_level() > 0) {
				ob_end_clean();
			}
			echo json_encode([
				'boot_error' => $error::class.': '.$error->getMessage(),
				'responses' => [],
			], JSON_UNESCAPED_SLASHES);
		}
	}

	private static function runProbe(): array
	{
		self::clearApplicationEnvironment();
		$projectDir = dirname(__DIR__, 2);
		$_SERVER['SCRIPT_FILENAME'] = '';
		$_ENV['SCRIPT_FILENAME'] = '';

		$app = require $projectDir.'/public/index.php';
		$runtime = new SymfonyRuntime([
			'project_dir' => $projectDir,
		]);
		[$app, $arguments] = $runtime->getResolver($app)->resolve();
		/** @var Kernel $kernel */
		$kernel = $app(...$arguments);

		$username = self::PLACEHOLDER_USERNAME;
		$password = self::PLACEHOLDER_PASSWORD;
		$secret = getenv('APP_SECRET');
		$databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
		if (!is_string($secret) || $secret === '' || !is_string($databaseUrl) || $databaseUrl === '') {
			throw new \RuntimeException('The application secret or database URL was not loaded.');
		}

		$responses = [];
		$responses['profiler_home'] = self::dispatch($kernel, 'GET', '/_profiler/');
		$location = $responses['profiler_home']['location'];
		if (is_string($location) && str_contains($location, '/_profiler')) {
			$responses['profiler_latest_search'] = self::dispatch($kernel, 'GET', $location);
		}

		$responses['profiler_search'] = self::dispatch($kernel, 'GET', '/_profiler/search?limit=10');
		$responses['profiler_search_bar'] = self::dispatch($kernel, 'GET', '/_profiler/search_bar');
		$responses['profiler_phpinfo'] = self::dispatch($kernel, 'GET', '/_profiler/phpinfo', [], null, $secret, $databaseUrl, $username, $password);
		$responses['profiler_open'] = self::dispatch(
			$kernel,
			'GET',
			'/_profiler/open?file=config/packages/security.yaml&line=17',
			[],
			null,
			$secret,
			$databaseUrl,
			$username,
			$password
		);
		$responses['profiler_xdebug'] = self::dispatch($kernel, 'GET', '/_profiler/xdebug', [], null, $secret, $databaseUrl, $username, $password);
		$responses['profiler_font'] = self::dispatch($kernel, 'GET', '/_profiler/font/JetBrainsMono.woff2');
		$responses['profiler_exception_stylesheet'] = self::dispatch($kernel, 'GET', '/_profiler/empty/exception.css');
		$responses['profiler_stylesheet'] = self::dispatch($kernel, 'GET', '/_wdt/styles');

		$signInBody = '_username='.$username.'&_password='.$password.'&_remember_me=on';
		$signIn = self::capture($kernel, 'POST', '/login', [
			'_username' => $username,
			'_password' => $password,
			'_remember_me' => 'on',
		], $signInBody);
		$token = $signIn['response']->headers->get('X-Debug-Token');

		if (is_string($token) && $token !== '') {
			$responses['sign_in_profile'] = self::dispatch(
				$kernel,
				'GET',
				'/_profiler/'.$token.'?panel=request',
				[],
				null,
				$secret,
				$databaseUrl,
				$username,
				$password
			);
			$responses['profiler_toolbar'] = self::dispatch(
				$kernel,
				'GET',
				'/_wdt/'.$token,
				[],
				null,
				$secret,
				$databaseUrl,
				$username,
				$password
			);
			$responses['profiler_router'] = self::dispatch(
				$kernel,
				'GET',
				'/_profiler/'.$token.'/router',
				[],
				null,
				$secret,
				$databaseUrl,
				$username,
				$password
			);
			$responses['profiler_exception'] = self::dispatch(
				$kernel,
				'GET',
				'/_profiler/'.$token.'/exception',
				[],
				null,
				$secret,
				$databaseUrl,
				$username,
				$password
			);
		}

		return [
			'app_env' => $_SERVER['APP_ENV'] ?? null,
			'app_debug' => $_SERVER['APP_DEBUG'] ?? null,
			'responses' => $responses,
		];
	}

	private static function clearApplicationEnvironment(): void
	{
		foreach (['APP_ENV', 'APP_DEBUG'] as $name) {
			putenv($name);
			unset($_SERVER[$name], $_ENV[$name]);
		}
		$_SERVER['argv'] = [];
		$_SERVER['argc'] = 0;
		$_GET = [];
	}

	private static function dispatch(
		HttpKernelInterface $kernel,
		string $method,
		string $uri,
		array $parameters = [],
		?string $content = null,
		string $secret = '',
		string $databaseUrl = '',
		string $username = '',
		string $password = ''
	): array {
		$captured = self::capture($kernel, $method, $uri, $parameters, $content);

		return self::describe($captured['response'], $secret, $databaseUrl, $username, $password);
	}

	/**
	 * @return array{response: Response}
	 */
	private static function capture(
		HttpKernelInterface $kernel,
		string $method,
		string $uri,
		array $parameters = [],
		?string $content = null
	): array {
		$server = $_SERVER;
		$server['REMOTE_ADDR'] = self::REMOTE_ADDRESS;
		$server['HTTP_HOST'] = 'bikelog.example';
		$server['SERVER_NAME'] = 'bikelog.example';
		unset($server['HTTPS']);
		if ($content !== null) {
			$server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
			$server['CONTENT_LENGTH'] = (string) strlen($content);
		}

		$request = Request::create($uri, $method, $parameters, [], [], $server, $content);
		$response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
		$kernel->terminate($request, $response);

		return ['response' => $response];
	}

	private static function describe(
		Response $response,
		string $secret,
		string $databaseUrl,
		string $username,
		string $password
	): array {
		$body = $response->getContent();
		$body = is_string($body) ? $body : '';
		$location = $response->headers->get('Location');
		$leaks = [];
		if ($secret !== '' && self::contains($body, $secret)) {
			$leaks[] = 'app_secret';
		}
		if ($databaseUrl !== '' && self::contains($body, $databaseUrl)) {
			$leaks[] = 'database_url';
		}
		if ($username !== '' && self::contains($body, $username)) {
			$leaks[] = 'username';
		}
		if ($password !== '' && (self::contains($body, $password) || self::contains($body, rawurlencode($password)))) {
			$leaks[] = 'password';
		}
		if (str_contains($body, 'pattern: ^/(_profiler|_wdt|css|images|js|assets|build)/')) {
			$leaks[] = 'security_config';
		}
		if (str_contains($body, 'Xdebug must be installed')) {
			$leaks[] = 'xdebug_controller';
		}

		return [
			'status' => $response->getStatusCode(),
			'location' => $location,
			'denied' => self::isDenied($response->getStatusCode(), $location) && $leaks === [],
			'leak_names' => $leaks,
		];
	}

	private static function isDenied(int $status, ?string $location): bool
	{
		if (in_array($status, [401, 403, 404], true)) {
			return true;
		}
		if (!in_array($status, [301, 302, 303, 307, 308], true)) {
			return false;
		}
		$path = parse_url((string) $location, PHP_URL_PATH);
		if (!is_string($path)) {
			return false;
		}
		if (str_starts_with($path, '/_profiler') || str_starts_with($path, '/_wdt')) {
			return false;
		}

		return $path === '/' || $path === '/login' || str_ends_with($path, '/login');
	}

	private static function contains(string $body, string $value): bool
	{
		return str_contains($body, $value)
			|| str_contains($body, htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	}

	private static function excerpt(string $text): string
	{
		$text = preg_replace('/placeholder(?:-app-secret|!password|-rider)/', '[placeholder]', $text) ?? $text;

		return substr($text, 0, 500);
	}
}

if (PHP_SAPI === 'cli' && getenv('BIKELOG_PROFILER_PROBE') === '1' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
	require dirname(__DIR__, 2).'/vendor/autoload.php';
	\App\Tests\Security\UnauthenticatedProfilerAccessTest::probe();
}
