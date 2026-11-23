<?php

namespace App\Tests\Security;

use App\Controller\UpdateController;
use App\Kernel;
use PHPUnit\Framework\TestCase;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

/**
 * The production updater must not install or execute a release archive that
 * fails signature verification, and it must not execute the updater script
 * shipped inside an archive.
 *
 * Contract the implementation has to meet:
 *
 * - Trust only the PEM SubjectPublicKeyInfo at config/update-release.pub in
 *   the installation being updated.
 * - Download BikeLog-$VER-build.tar.gz.sig from
 *   https://github.com/gztproject/BikeLog/releases/download/$VER/
 *   next to the archive. The signature is the raw output of
 *   `openssl dgst -sha256 -sign` and must verify with
 *   `openssl dgst -sha256 -verify` before the archive is extracted.
 * - Extract the bytes that were verified. Opening the download path again
 *   after another process has read it is not enough: tar then sees different
 *   bytes, and those bytes must not be extracted or executed.
 * - Keep running Scripts/update.sh from the installation that is already on
 *   disk. Do not exec the copy inside the archive, even when its signature
 *   is valid and even when that copy contains the BIKELOG_UPDATE_PROJECT_DIR
 *   marker.
 * - Do not request the Git tag archive
 *   https://github.com/gztproject/BikeLog/archive/refs/tags/$VER.tar.gz
 *   when the packaged asset is missing or rejected.
 *
 * Refusing only after tar has created unsigned-extract-marker or
 * poison-marker, or after the archive's updater has written the proof file,
 * is a failure. composer install, the frontend build, the installation
 * replacement, doctrine migrations, and cache:clear all run only after that
 * extract, so they are covered by the same refusal.
 */
class UnsignedReleaseUpdateTest extends TestCase
{
    private const REPO_SCRIPT = __DIR__.'/../../Scripts/update.sh';

    private const CANARY_LINE = 'UPDATE_TEST_CANARY=placeholder';

    /** @var list<string> */
    private array $cleanup = [];

    /** @var array<string, string> */
    private array $savedEnv = [];

    private static string $preloadLibrary = '';

    public static function setUpBeforeClass(): void
    {
        $library = sys_get_temp_dir().'/bikelog-archive-open-preload.so';
        $compile = new Process([
            'gcc',
            '-shared',
            '-fPIC',
            '-O2',
            '-o',
            $library,
            __DIR__.'/archive_open_preload.c',
            '-ldl',
        ]);
        $compile->run();
        if (!$compile->isSuccessful()) {
            self::fail("Could not compile the archive preload:\n".$compile->getErrorOutput().$compile->getOutput());
        }
        self::$preloadLibrary = $library;
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            if ($value === '') {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv($name.'='.$value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        $this->savedEnv = [];

        foreach (array_reverse($this->cleanup) as $path) {
            if (is_dir($path) && !is_link($path)) {
                $this->removeTree($path);
            } elseif (file_exists($path) || is_link($path)) {
                unlink($path);
            }
        }
        $this->cleanup = [];
    }

    public function testUnsignedReleaseIsRejectedBeforeExtractionOrExecution(): void
    {
        $version = '9.9.9';
        $layout = $this->installFixture($version);
        $archive = $this->buildReleaseArchive($layout['work'], $version, 'unsigned-extract-marker', true);
        $this->writeDownloadMap($layout, [
            $this->assetUrl($version) => ['status' => 0, 'body' => $archive],
            $this->signatureUrl($version) => ['status' => 0, 'body' => $layout['work'].'/invalid.sig'],
        ]);
        file_put_contents($layout['work'].'/invalid.sig', 'invalid-signature');

        $result = $this->runInstalledUpdater($layout, $version, $archive, $layout['work'].'/poison-unused.tar.gz');

        $this->assertUpdaterRefusedArchive($layout, $result, 'unsigned-extract-marker');
    }

    public function testMissingPackagedAssetDoesNotUseSourceArchive(): void
    {
        $version = '9.9.9';
        $layout = $this->installFixture($version);
        $source = $this->buildReleaseArchive($layout['work'], $version, 'unsigned-extract-marker', true);
        $this->writeDownloadMap($layout, [
            $this->sourceUrl($version) => ['status' => 0, 'body' => $source],
        ]);

        $result = $this->runInstalledUpdater($layout, $version, $layout['parent'].'/'.$version.'.tar.gz', '');

        $urls = $this->requestedUrls($layout);
        self::assertNotContains(
            $this->sourceUrl($version),
            $urls,
            "The updater requested the unsigned Git tag archive.\n".$result['output']
        );
        $this->assertUpdaterRefusedArchive($layout, $result, 'unsigned-extract-marker');
    }

    public function testSignedArchiveStaysOnTheInstalledUpdaterAndUsesTheVerifiedBytes(): void
    {
        $version = '9.9.9';
        $layout = $this->installFixture($version);
        $good = $this->buildReleaseArchive($layout['work'], $version, 'good-release-marker', true, true);
        $poison = $this->buildReleaseArchive($layout['work'], $version, 'poison-marker', true, true);
        $publicKey = $layout['project'].'/config/update-release.pub';
        $signature = $layout['work'].'/archive.sig';
        $this->signArchive($good, $layout['work'].'/release-private.pem', $publicKey, $signature);
        $this->writeDownloadMap($layout, [
            $this->assetUrl($version) => ['status' => 0, 'body' => $good],
            $this->signatureUrl($version) => ['status' => 0, 'body' => $signature],
        ]);

        $result = $this->runInstalledUpdater($layout, $version, $layout['parent'].'/'.$version.'.tar.gz', $poison);

        self::assertContains(
            $this->signatureUrl($version),
            $this->requestedUrls($layout),
            "The updater did not download the detached signature.\n".$result['output']
        );
        self::assertStringContainsString(
            'Copying environment files into the new release.',
            $result['output'],
            "The installed updater did not continue after the handoff point.\n".$result['output']
        );
        $this->assertArchiveUpdaterDidNotRun($layout, $result);
        $this->assertMarkerAbsent($layout, 'poison-marker');
    }

    public function testProductionUpdateActionRejectsTheSubmittedUnsignedRelease(): void
    {
        $version = '9.9.9-rc.1';
        $layout = $this->installFixture($version, true);
        $archive = $this->buildReleaseArchive($layout['work'], $version, 'unsigned-extract-marker', true);
        $this->writeDownloadMap($layout, [
            $this->assetUrl($version) => ['status' => 0, 'body' => $archive],
            $this->signatureUrl($version) => ['status' => 0, 'body' => $layout['work'].'/invalid.sig'],
        ]);
        file_put_contents($layout['work'].'/invalid.sig', 'invalid-signature');
        $envBefore = (string) file_get_contents($layout['project'].'/.env');

        $kernel = new IsolatedProdKernel('prod', false);
        $kernel->boot();
        $this->exportChildEnvironment($this->childEnvironment($layout, $layout['parent'].'/'.$version.'.tar.gz', ''));
        try {
            $container = $kernel->getContainer();
            /** @var UpdateController $controller */
            $controller = $container->get(UpdateController::class);
            $request = Request::create('/admin/update/do', 'POST', [
                'version' => $version,
                'token' => 'placeholder',
            ]);
            $request->setSession(new Session(new MockArraySessionStorage()));
            $container->get('request_stack')->push($request);
            $token = $container->get('security.csrf.token_manager')->getToken('admin_update')->getValue();
            $request->request->set('token', $token);

            $response = $controller->doUpdate(
                $request,
                $container->get(VersionManagerInterface::class)
            );
            self::assertInstanceOf(StreamedResponse::class, $response);
            // prepareStream() discards output buffers before the process echoes,
            // so the security result is read from the proof file and the filesystem.
            $response->sendContent();
        } finally {
            $kernel->shutdown();
        }

        self::assertContains(
            $this->assetUrl($version),
            $this->requestedUrls($layout),
            'The update action did not download the submitted version.'
        );
        $this->assertArchiveUpdaterDidNotRun($layout, ['output' => '']);
        $this->assertMarkerAbsent($layout, 'unsigned-extract-marker');
        self::assertSame($envBefore, (string) file_get_contents($layout['project'].'/.env'));
        self::assertSame('kept', (string) file_get_contents($layout['project'].'/INSTALL_SENTINEL'));
        self::assertDirectoryDoesNotExist($layout['parent'].'/BikeLog-'.$version);
    }

    /**
     * @return array{parent: string, project: string, work: string, bin: string, proof: string, wgetLog: string, creatLog: string, seenFlag: string}
     */
    private function installFixture(string $version, bool $bootable = false): array
    {
        $parent = sys_get_temp_dir().'/bikelog-update-'.bin2hex(random_bytes(6));
        $project = $parent.'/project';
        $work = $parent.'/work';
        $bin = $parent.'/bin';
        foreach ([$project.'/Scripts', $project.'/config', $work, $bin] as $dir) {
            mkdir($dir, 0777, true);
        }
        $this->cleanup[] = $parent;

        copy(self::REPO_SCRIPT, $project.'/Scripts/update.sh');
        chmod($project.'/Scripts/update.sh', 0755);
        self::assertSame(
            hash_file('sha256', self::REPO_SCRIPT),
            hash_file('sha256', $project.'/Scripts/update.sh'),
            'The fixture must run the repository updater unchanged.'
        );
        file_put_contents($project.'/.env', self::CANARY_LINE."\n");
        file_put_contents($project.'/INSTALL_SENTINEL', 'kept');
        file_put_contents($project.'/VERSION', "0.0.15\n");

        if ($bootable) {
            foreach (['config', 'src', 'vendor', 'templates', 'translations', 'bin', 'public', 'composer.json', 'composer.lock'] as $item) {
                $source = dirname(self::REPO_SCRIPT, 2).'/'.$item;
                if (!file_exists($source)) {
                    continue;
                }
                if ($item === 'config') {
                    continue;
                }
                symlink($source, $project.'/'.$item);
            }
            $this->copyTree(dirname(self::REPO_SCRIPT, 2).'/config', $project.'/config');
            file_put_contents($project.'/.env', implode("\n", [
                'APP_ENV=prod',
                'APP_SECRET=test-only-secret',
                'APP_SHARE_DIR='.$parent,
                'DATABASE_URL="sqlite:///'.$parent.'/unused.db"',
                'MAILER_DSN=null://null',
                'DEFAULT_URI=http://localhost',
                "CORS_ALLOW_ORIGIN='^https?://localhost(:[0-9]+)?$'",
                self::CANARY_LINE,
                '',
            ]));
            putenv('BIKELOG_UPDATE_TEST_ROOT='.$project);
            $_ENV['BIKELOG_UPDATE_TEST_ROOT'] = $project;
            $_SERVER['BIKELOG_UPDATE_TEST_ROOT'] = $project;
            $this->savedEnv['APP_SECRET'] = getenv('APP_SECRET') === false ? '' : (string) getenv('APP_SECRET');
            putenv('APP_SECRET=test-only-secret');
            $_ENV['APP_SECRET'] = 'test-only-secret';
            $_SERVER['APP_SECRET'] = 'test-only-secret';
        }

        $proof = $work.'/proof';
        $wgetLog = $work.'/wget.log';
        $creatLog = $work.'/creat.log';
        $seenFlag = $work.'/seen';
        foreach ([$proof, $wgetLog, $creatLog] as $file) {
            file_put_contents($file, '');
        }

        $repoRoot = dirname(self::REPO_SCRIPT, 2);
        self::assertStringStartsWith(sys_get_temp_dir(), $project);
        self::assertStringNotContainsString($repoRoot.'/Scripts/update.sh', realpath($project).'/Scripts/update.sh');

        return [
            'parent' => $parent,
            'project' => $project,
            'work' => $work,
            'bin' => $bin,
            'proof' => $proof,
            'wgetLog' => $wgetLog,
            'creatLog' => $creatLog,
            'seenFlag' => $seenFlag,
        ];
    }

    private function buildReleaseArchive(string $work, string $version, string $markerName, bool $handoff, bool $bundledRuntime = false): string
    {
        $stage = $work.'/stage-'.bin2hex(random_bytes(4));
        $root = $stage.'/BikeLog-'.$version;
        mkdir($root.'/Scripts', 0777, true);
        $script = "#!/bin/bash\n";
        if ($handoff) {
            $script .= "# BIKELOG_UPDATE_PROJECT_DIR\n";
        }
        $script .= <<<'BASH'
set -u
{
    echo executed
    if [[ -n "${BIKELOG_UPDATE_PROJECT_DIR:-}" && -f "${BIKELOG_UPDATE_PROJECT_DIR}/.env" ]]; then
        if grep -q '^UPDATE_TEST_CANARY=' "${BIKELOG_UPDATE_PROJECT_DIR}/.env"; then
            echo env-readable
        fi
    fi
} >> "${BIKELOG_UPDATE_PROOF_FILE}"
exit 0
BASH;
        file_put_contents($root.'/Scripts/update.sh', $script);
        chmod($root.'/Scripts/update.sh', 0755);
        file_put_contents($root.'/'.$markerName, $markerName);
        if ($bundledRuntime) {
            mkdir($root.'/vendor', 0777, true);
            mkdir($root.'/public/build', 0777, true);
            file_put_contents($root.'/vendor/autoload.php', "<?php\n");
            file_put_contents($root.'/public/build/entrypoints.json', "{}\n");
            file_put_contents($root.'/public/build/manifest.json', "{}\n");
        }
        $archive = $work.'/'.bin2hex(random_bytes(4)).'.tar.gz';
        $tar = new Process(['tar', '-C', $stage, '-czf', $archive, 'BikeLog-'.$version]);
        $tar->mustRun();
        // The staging tree holds the marker and sits under the directory assertMarkerAbsent() scans.
        $this->removeTree($stage);

        return $archive;
    }

    private function signArchive(string $archive, string $privateKey, string $publicKey, string $signature): void
    {
        $generate = new Process([
            'openssl', 'genpkey', '-algorithm', 'RSA', '-pkeyopt', 'rsa_keygen_bits:2048', '-out', $privateKey,
        ]);
        $generate->mustRun();
        $public = new Process(['openssl', 'pkey', '-in', $privateKey, '-pubout', '-out', $publicKey]);
        $public->mustRun();
        $sign = new Process([
            'openssl', 'dgst', '-sha256', '-sign', $privateKey, '-out', $signature, $archive,
        ]);
        $sign->mustRun();
        $check = new Process([
            'openssl', 'dgst', '-sha256', '-verify', $publicKey, '-signature', $signature, $archive,
        ]);
        $check->mustRun();
    }

    /**
     * @param array<string, array{status: int, body: string}> $map
     */
    private function writeDownloadMap(array $layout, array $map): void
    {
        $manifest = [];
        foreach ($map as $url => $entry) {
            $manifest[$url] = [
                'status' => $entry['status'],
                'body' => $entry['body'],
            ];
        }
        $manifestPath = $layout['work'].'/manifest.json';
        file_put_contents($manifestPath, json_encode($manifest));
        $wget = <<<BASH
#!/bin/bash
out=""
url=""
while [[ \$# -gt 0 ]]; do
    case "\$1" in
        -O|--output-document)
            out="\$2"
            shift 2
            ;;
        --output-document=*)
            out="\${1#*=}"
            shift
            ;;
        -*)
            shift
            ;;
        *)
            url="\$1"
            shift
            ;;
    esac
done
printf '%s\\n' "\$url" >> "\$BIKELOG_TEST_WGET_LOG"
python3 - "\$url" "\$out" "\$BIKELOG_TEST_WGET_MANIFEST" <<'PY'
import json, sys
url, out, manifest = sys.argv[1:]
entries = json.load(open(manifest))
entry = entries.get(url)
if entry is None:
    open(out, "wb").close()
    raise SystemExit(8)
with open(entry["body"], "rb") as source, open(out, "wb") as target:
    target.write(source.read())
raise SystemExit(int(entry["status"]))
PY
BASH;
        file_put_contents($layout['bin'].'/wget', $wget);
        chmod($layout['bin'].'/wget', 0755);
    }

    /**
     * @param array{parent: string, project: string, work: string, bin: string, proof: string, wgetLog: string, creatLog: string, seenFlag: string} $layout
     * @return array{output: string, exit: int}
     */
    private function runInstalledUpdater(array $layout, string $version, string $archivePath, string $poisonPath): array
    {
        $env = $this->childEnvironment($layout, $archivePath, $poisonPath);
        $process = new Process(
            ['bash', $layout['project'].'/Scripts/update.sh', '-v', $version],
            $layout['project'],
            $env
        );
        $process->setTimeout(30);
        $process->run();

        return [
            'output' => $process->getOutput().$process->getErrorOutput(),
            'exit' => $process->getExitCode() ?? 1,
        ];
    }

    /**
     * @param array{parent: string, project: string, work: string, bin: string, proof: string, wgetLog: string, creatLog: string, seenFlag: string} $layout
     * @return array<string, string>
     */
    private function childEnvironment(array $layout, string $archivePath, string $poisonPath): array
    {
        return [
            'PATH' => $layout['bin'].':/usr/bin:/bin',
            'HOME' => $layout['work'],
            'BIKELOG_UPDATE_PROOF_FILE' => $layout['proof'],
            'BIKELOG_TEST_WGET_LOG' => $layout['wgetLog'],
            'BIKELOG_TEST_WGET_MANIFEST' => $layout['work'].'/manifest.json',
            'BIKELOG_TEST_ARCHIVE_PATH' => $archivePath,
            'BIKELOG_TEST_POISON_ARCHIVE' => $poisonPath,
            'BIKELOG_TEST_VERIFIED_FLAG' => $layout['seenFlag'],
            'BIKELOG_TEST_CREAT_LOG' => $layout['creatLog'],
            'LD_PRELOAD' => self::$preloadLibrary,
        ];
    }

    /**
     * @param array<string, string> $env
     */
    private function exportChildEnvironment(array $env): void
    {
        foreach ($env as $name => $value) {
            if (!array_key_exists($name, $this->savedEnv)) {
                $current = getenv($name);
                $this->savedEnv[$name] = $current === false ? '' : (string) $current;
            }
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    /**
     * @param array{parent: string, project: string, proof: string, creatLog: string} $layout
     * @param array{output: string, exit: int} $result
     */
    private function assertUpdaterRefusedArchive(array $layout, array $result, string $marker): void
    {
        $this->assertArchiveUpdaterDidNotRun($layout, $result);
        $this->assertMarkerAbsent($layout, $marker);
        self::assertNotSame(0, $result['exit'], "The updater exited successfully.\n".$result['output']);
        self::assertSame(self::CANARY_LINE, trim((string) file_get_contents($layout['project'].'/.env')));
        self::assertSame('kept', (string) file_get_contents($layout['project'].'/INSTALL_SENTINEL'));
        self::assertDirectoryDoesNotExist($layout['parent'].'/BikeLog-9.9.9');
        self::assertDirectoryDoesNotExist($layout['parent'].'/BikeLog-9.9.9-rc.1');
    }

    /**
     * @param array{proof: string} $layout
     * @param array{output: string} $result
     */
    private function assertArchiveUpdaterDidNotRun(array $layout, array $result): void
    {
        $proof = (string) file_get_contents($layout['proof']);
        self::assertStringNotContainsString('executed', $proof, "The archive updater ran.\n".$result['output']."\n".$proof);
        self::assertStringNotContainsString('env-readable', $proof, "The archive updater read the installation environment file.\n".$proof);
    }

    /**
     * @param array{parent: string, project: string, creatLog: string} $layout
     */
    private function assertMarkerAbsent(array $layout, string $marker): void
    {
        $created = (string) file_get_contents($layout['creatLog']);
        self::assertStringNotContainsString($marker, $created, "The archive was extracted before it was refused.\n".$created);
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($layout['parent'], \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getFilename() === $marker) {
                $found[] = $file->getPathname();
            }
        }
        self::assertSame([], $found, 'Extracted archive marker is still on disk.');
    }

    /**
     * @param array{wgetLog: string} $layout
     * @return list<string>
     */
    private function requestedUrls(array $layout): array
    {
        $lines = file($layout['wgetLog'], FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    private function assetUrl(string $version): string
    {
        return 'https://github.com/gztproject/BikeLog/releases/download/'.$version.'/BikeLog-'.$version.'-build.tar.gz';
    }

    private function signatureUrl(string $version): string
    {
        return $this->assetUrl($version).'.sig';
    }

    private function sourceUrl(string $version): string
    {
        return 'https://github.com/gztproject/BikeLog/archive/refs/tags/'.$version.'.tar.gz';
    }

    private function copyTree(string $source, string $destination): void
    {
        if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
            self::fail('Could not create '.$destination);
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $destination.substr($item->getPathname(), strlen($source));
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
                continue;
            }
            $dir = dirname($target);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            copy($item->getPathname(), $target);
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }
        rmdir($path);
    }
}

final class IsolatedProdKernel extends Kernel
{
    public function getProjectDir(): string
    {
        $root = getenv('BIKELOG_UPDATE_TEST_ROOT');
        if (!is_string($root) || $root === '') {
            throw new \RuntimeException('BIKELOG_UPDATE_TEST_ROOT is not set.');
        }

        return $root;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new PublishUpdateServicesPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}

final class PublishUpdateServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ([VersionManagerInterface::class, 'security.csrf.token_manager', 'request_stack'] as $id) {
            if ($container->hasAlias($id)) {
                $container->getAlias($id)->setPublic(true);
            }
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            }
        }
    }
}
