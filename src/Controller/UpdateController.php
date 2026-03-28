<?php

namespace App\Controller;

use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

class UpdateController extends AbstractController
{
    private const PROGRESS_PREFIX = '__BL_PROGRESS__|';

    #[Route('/admin/update', methods: ['GET'], name: 'admin_update')]
    public function index(): Response
    {
        return $this->render('admin/update.html.twig');
    }

    #[Route('/admin/update/check', methods: ['GET'], name: 'admin_update_check')]
    public function checkForUpdates(VersionManagerInterface $manager): Response
    {
        $currentVersion = $manager->getVersion();

        return $this->json(['current_version' => $currentVersion]);
    }

    #[Route('/admin/update/do', methods: ['POST'], name: 'admin_do_update')]
    public function doUpdate(Request $request, VersionManagerInterface $manager): StreamedResponse
    {
        if (!$this->isCsrfTokenValid('admin_update', (string) $request->request->get('token', ''))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        if ('prod' !== $this->getParameter('kernel.environment')) {
            throw new BadRequestHttpException('Updates can only run in the prod environment.');
        }

        $requestedVersion = $this->normalizeRequestedVersion((string) $request->request->get('version', ''));
        $currentVersion = $this->normalizeComparableVersion((string) $manager->getVersion());

        if (version_compare($this->normalizeComparableVersion($requestedVersion), $currentVersion, '<=')) {
            throw new BadRequestHttpException('Requested version must be newer than the installed version.');
        }

        $process = $this->createUpdateProcess($requestedVersion);

        $response = new StreamedResponse(function () use ($process, $requestedVersion, $currentVersion): void {
            $this->prepareStream();

            $emit = function (array $payload): void {
                $this->emitStreamEvent($payload);
            };

            $emit([
                'type' => 'meta',
                'currentVersion' => $currentVersion,
                'targetVersion' => $requestedVersion,
            ]);
            $emit([
                'type' => 'progress',
                'progress' => 2,
                'message' => 'Preparing update process.',
            ]);

            $buffers = [
                Process::OUT => '',
                Process::ERR => '',
            ];

            $process->run(function (string $type, string $chunk) use (&$buffers, $emit): void {
                $buffers[$type] .= $chunk;

                while (($position = strpos($buffers[$type], "\n")) !== false) {
                    $line = rtrim(substr($buffers[$type], 0, $position), "\r");
                    $buffers[$type] = substr($buffers[$type], $position + 1);

                    $this->streamProcessLine($line, $type, $emit);
                }
            });

            foreach ($buffers as $type => $buffer) {
                $line = trim($buffer);

                if ('' !== $line) {
                    $this->streamProcessLine($line, $type, $emit);
                }
            }

            if ($process->isSuccessful()) {
                $emit([
                    'type' => 'complete',
                    'progress' => 100,
                    'message' => 'Update completed successfully.',
                ]);

                return;
            }

            $emit([
                'type' => 'failed',
                'progress' => 100,
                'message' => sprintf(
                    'Update failed with exit code %s.',
                    $process->getExitCode() ?? 'unknown'
                ),
            ]);
        });

        $response->headers->set('Content-Type', 'application/x-ndjson; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    private function createUpdateProcess(string $requestedVersion): Process
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $process = new Process(
            ['bash', $projectDir . '/Scripts/update.sh', '-v', $requestedVersion],
            $projectDir
        );

        $process->setTimeout(null);

        return $process;
    }

    private function normalizeRequestedVersion(string $version): string
    {
        $normalized = trim($version);

        if (!preg_match('/^v?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $normalized)) {
            throw new BadRequestHttpException('Invalid version.');
        }

        return $normalized;
    }

    private function normalizeComparableVersion(string $version): string
    {
        $normalized = strtolower(trim($version));
        $normalized = preg_replace('/^v/', '', $normalized) ?? '';
        $normalized = explode('-', $normalized, 2)[0];
        $normalized = explode('+', $normalized, 2)[0];

        return '' !== $normalized ? $normalized : '0.0.0';
    }

    private function prepareStream(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }

    private function streamProcessLine(string $line, string $channel, callable $emit): void
    {
        if ('' === trim($line)) {
            return;
        }

        if (str_starts_with($line, self::PROGRESS_PREFIX)) {
            $parts = explode('|', $line, 3);

            if (3 === count($parts) && is_numeric($parts[1])) {
                $emit([
                    'type' => 'progress',
                    'progress' => max(0, min(100, (int) $parts[1])),
                    'message' => $parts[2],
                ]);

                return;
            }
        }

        $emit([
            'type' => 'output',
            'channel' => Process::ERR === $channel ? 'stderr' : 'stdout',
            'message' => $line,
        ]);
    }

    private function emitStreamEvent(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        @ob_flush();
        flush();
    }
}
