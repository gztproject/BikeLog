<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The public mail route must not hand a message to the mailer for a caller
 * who has no session. These assertions fail while that route still sends.
 */
class PublicMailRouteTest extends WebTestCase
{
    private ?string $originalMailerDsn = null;

    protected function tearDown(): void
    {
        $this->restoreMailerDsn();
        parent::tearDown();
    }

    public function testUnauthenticatedGetDoesNotSendThroughTheDefaultMailer(): void
    {
        $this->useMailerDsn('null://null');
        $client = static::createClient();

        $client->request('GET', '/email');

        self::assertNull($this->securityToken(), 'The request had no authenticated session.');
        self::assertEmailCount(0, null, 'An unauthenticated GET must not hand a message to the mailer.');
    }

    public function testRepeatedUnauthenticatedGetsDoNotSend(): void
    {
        $this->useMailerDsn('null://null');
        $client = static::createClient();
        $accepted = 0;

        for ($i = 0; $i < 3; ++$i) {
            $client->request('GET', '/email');
            $accepted += count(self::getMailerMessages());
        }

        self::assertSame(0, $accepted, 'Repeating the public request must not hand a message to the mailer.');
    }

    public function testUnauthenticatedHeadDoesNotSendThroughTheDefaultMailer(): void
    {
        $this->useMailerDsn('null://null');
        $client = static::createClient();

        $client->request('HEAD', '/email');

        self::assertEmailCount(0, null, 'HEAD is routed to the same action and must not hand a message to the mailer.');
    }

    public function testUnauthenticatedPostDoesNotSendThroughTheDefaultMailer(): void
    {
        $this->useMailerDsn('null://null');
        $client = static::createClient();

        $client->request('POST', '/email');

        self::assertEmailCount(0, null, 'An unauthenticated POST must not hand a message to the mailer.');
    }

    public function testUnauthenticatedGetsDoNotDeliverToAConfiguredSmtpTransport(): void
    {
        $directory = sys_get_temp_dir().'/bikelog-mail-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $addressFile = $directory.'/address';
        $logFile = $directory.'/count';
        $stopFile = $directory.'/stop';
        $server = dirname(__DIR__).'/Support/RecordingSmtpServer.php';

        $process = proc_open(
            [PHP_BINARY, $server, $addressFile, $logFile, $stopFile],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);

        try {
            $address = $this->waitForFile($addressFile);
            [$host, $port] = explode(':', $address, 2);
            $this->useMailerDsn(sprintf('smtp://%s:%s?auto_tls=false', $host, $port));

            $client = static::createClient();
            $client->request('GET', '/email');
            $client->request('GET', '/email');

            self::assertSame('0', $this->readCount($logFile), 'A configured SMTP transport must not receive a message from an unauthenticated request.');
        } finally {
            file_put_contents($stopFile, '1');
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            @unlink($addressFile);
            @unlink($logFile);
            @unlink($stopFile);
            @rmdir($directory);

            if ($stdout !== '' || $stderr !== '') {
                fwrite(STDERR, $stdout.$stderr);
            }
        }
    }

    private function securityToken(): mixed
    {
        /** @var TokenStorageInterface $storage */
        $storage = static::getContainer()->get('security.token_storage');

        return $storage->getToken();
    }

    private function useMailerDsn(string $dsn): void
    {
        if ($this->originalMailerDsn === null) {
            $current = $_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? getenv('MAILER_DSN');
            $this->originalMailerDsn = $current === false ? null : $current;
        }

        static::ensureKernelShutdown();
        $_ENV['MAILER_DSN'] = $dsn;
        $_SERVER['MAILER_DSN'] = $dsn;
        putenv('MAILER_DSN='.$dsn);
    }

    private function restoreMailerDsn(): void
    {
        if ($this->originalMailerDsn === null) {
            return;
        }

        $_ENV['MAILER_DSN'] = $this->originalMailerDsn;
        $_SERVER['MAILER_DSN'] = $this->originalMailerDsn;
        putenv('MAILER_DSN='.$this->originalMailerDsn);
        $this->originalMailerDsn = null;
    }

    private function waitForFile(string $path): string
    {
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            if (is_file($path)) {
                $contents = trim((string) file_get_contents($path));
                if ($contents !== '') {
                    return $contents;
                }
            }
            usleep(20000);
        }

        self::fail('The local SMTP listener did not start.');
    }

    private function readCount(string $logFile): string
    {
        if (!is_file($logFile)) {
            return '0';
        }

        return trim((string) file_get_contents($logFile));
    }
}
