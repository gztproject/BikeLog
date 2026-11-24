<?php

namespace App\Tests\Security;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * An upgraded install may keep a pre-lock .env without LOCK_DSN because
 * Update.sh checkouts keep untracked files such as .env / .env.local.
 * Sign-in must still work when that variable is missing.
 */
final class LockDsnDefaultTest extends WebTestCase
{
    public function testSignInDoesNotCrashWhenLockDsnIsUnset(): void
    {
        $path = sys_get_temp_dir().'/bikelog-lock-dsn-default-test.sqlite';
        if (is_file($path)) {
            unlink($path);
        }
        $url = 'sqlite:///'.$path;
        putenv('DATABASE_URL='.$url);
        $_ENV['DATABASE_URL'] = $url;
        $_SERVER['DATABASE_URL'] = $url;

        unset($_ENV['LOCK_DSN'], $_SERVER['LOCK_DSN']);
        putenv('LOCK_DSN');

        self::ensureKernelShutdown();
        $kernel = self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::ensureKernelShutdown();

        $client = static::createClient();
        $loginPage = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $token = $loginPage->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotSame('', $token);

        $client->request('POST', '/login', [
            '_username' => 'missing-account',
            '_password' => 'wrong-password',
            '_csrf_token' => $token,
        ]);

        $response = $client->getResponse();
        $body = (string) $response->getContent();
        self::assertStringNotContainsString(
            'LOCK_DSN',
            $body,
            'POST /login failed because LOCK_DSN was missing'
        );
        self::assertStringNotContainsString('Environment variable not found', $body);
        self::assertNotSame(500, $response->getStatusCode(), 'Unexpected 500. Body: '.substr($body, 0, 500));
        self::assertTrue($response->isRedirect(), 'Expected a login redirect, got status '.$response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }
}
