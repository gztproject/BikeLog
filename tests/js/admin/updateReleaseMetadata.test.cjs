const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../../..');
const updateSourcePath = path.join(root, 'assets/js/admin/update.js');
const versionSourcePath = path.join(root, 'assets/js/common/update/version.js');
const jqueryPath = path.join(root, 'node_modules/jquery/dist/jquery.js');
const bootstrapPath = path.join(root, 'node_modules/bootstrap/dist/js/bootstrap.js');
const chromeCandidates = [
    '/usr/local/bin/google-chrome',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
];
const chromePath = process.env.CHROME_PATH
    || chromeCandidates.find((candidate) => fs.existsSync(candidate))
    || chromeCandidates[0];

const PLACEHOLDER_UPDATE_TOKEN = 'csrf-token-placeholder';

function browserUpdateSource() {
    const updateSource = fs.readFileSync(updateSourcePath, 'utf8');
    const requireLine = "const { isNewerVersion } = require('../common/update/version');";
    assert.equal(
        updateSource.includes(requireLine),
        true,
        'update.js must keep loading isNewerVersion from the local helper'
    );

    const versionSource = fs.readFileSync(versionSourcePath, 'utf8');
    const withoutRequire = updateSource.replace(requireLine, '');

    return `
        const isNewerVersion = (function loadVersionHelper() {
            const module = { exports: {} };
            const exports = module.exports;
            ${versionSource}
            return module.exports.isNewerVersion;
        }());
        ${withoutRequire}
    `;
}

function releaseDocument(releaseUrl, checkUrl) {
    return `<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>update</title></head>
<body>
<section id="admin-update-panel"
    data-check-url="${checkUrl}"
    data-run-url="/admin/update/do"
    data-update-token="${PLACEHOLDER_UPDATE_TOKEN}"
    data-app-env="prod"
    data-release-api-url="${releaseUrl}">
    <button type="button" id="btn-check-updates">Check</button>
</section>
<div class="modal fade" id="updateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" id="btn-close-update-modal" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="update-modal__placeholder">Check</div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btn-cancel-update" data-dismiss="modal">Cancel</button>
                <button type="button" id="btn-do-update" disabled="true">Update</button>
            </div>
        </div>
    </div>
</div>
<script src="/jquery.js"></script>
<script src="/bootstrap.js"></script>
<script src="/update.js"></script>
<script>
    document.getElementById('btn-check-updates').click();

    const started = Date.now();
    const timer = setInterval(function publishProbe() {
        const body = document.querySelector('.modal-body');
        const settled = body && (body.querySelector('#newA') || body.querySelector('.alert-danger'));
        const attacked = window.__releaseXss || window.__urlXss || window.__currentXss || window.__notesXss;
        if (!settled && !attacked && Date.now() - started < 4000) {
            return;
        }
        clearInterval(timer);
        const link = document.getElementById('newA');
        const current = document.getElementById('currentA');
        const notes = document.getElementById('changes');
        const probe = {
            releaseXss: window.__releaseXss || null,
            urlXss: window.__urlXss || null,
            currentXss: window.__currentXss || null,
            notesXss: window.__notesXss || null,
            tokenSeenByPayload: window.__tokenSeen || null,
            link: link ? {
                text: link.textContent,
                html: link.innerHTML,
                href: link.getAttribute('href'),
            } : null,
            current: current ? {
                text: current.textContent,
                html: current.innerHTML,
            } : null,
            notes: notes ? {
                text: notes.textContent,
                html: notes.innerHTML,
            } : null,
            counts: {
                script: document.querySelectorAll('script').length,
                svg: document.querySelectorAll('.modal-body svg').length,
                img: document.querySelectorAll('.modal-body img').length,
            },
            error: body && body.querySelector('.alert-danger')
                ? body.querySelector('.alert-danger').textContent
                : null,
        };
        fetch('/result', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(probe),
        });
    }, 20);
</script>
</body>
</html>`;
}

function githubReleaseBody(release) {
    return JSON.stringify({
        tag_name: release.tag_name,
        html_url: release.html_url,
        body: release.body,
        draft: false,
        prerelease: false,
    });
}

function allowedReleaseHref(value) {
    if (typeof value !== 'string' || value === '') {
        return false;
    }
    try {
        const url = new URL(value);
        return url.protocol === 'https:' && (url.hostname === 'github.com' || url.hostname.endsWith('.github.com'));
    } catch (error) {
        return false;
    }
}

function startHarness(release, currentVersion) {
    const updateScript = browserUpdateSource();
    const jquery = fs.readFileSync(jqueryPath);
    const bootstrap = fs.readFileSync(bootstrapPath);
    let pageUrl = '';
    let resultResolve;
    let resultReject;
    const result = new Promise((resolve, reject) => {
        resultResolve = resolve;
        resultReject = reject;
    });

    const server = http.createServer((request, response) => {
        const url = new URL(request.url, pageUrl || 'http://127.0.0.1');
        if (request.method === 'GET' && url.pathname === '/') {
            response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            response.end(releaseDocument(`${pageUrl}/releases/latest`, `${pageUrl}/admin/update/check`));
            return;
        }
        if (request.method === 'GET' && url.pathname === '/jquery.js') {
            response.writeHead(200, { 'Content-Type': 'text/javascript; charset=utf-8' });
            response.end(jquery);
            return;
        }
        if (request.method === 'GET' && url.pathname === '/bootstrap.js') {
            response.writeHead(200, { 'Content-Type': 'text/javascript; charset=utf-8' });
            response.end(bootstrap);
            return;
        }
        if (request.method === 'GET' && url.pathname === '/update.js') {
            response.writeHead(200, { 'Content-Type': 'text/javascript; charset=utf-8' });
            response.end(updateScript);
            return;
        }
        if (request.method === 'GET' && url.pathname === '/releases/latest') {
            response.writeHead(200, {
                'Content-Type': 'application/json; charset=utf-8',
                'Access-Control-Allow-Origin': '*',
            });
            response.end(githubReleaseBody(release));
            return;
        }
        if (request.method === 'GET' && url.pathname === '/admin/update/check') {
            response.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            response.end(JSON.stringify({ current_version: currentVersion }));
            return;
        }
        if (request.method === 'POST' && url.pathname === '/result') {
            const chunks = [];
            request.on('data', (chunk) => chunks.push(chunk));
            request.on('end', () => {
                response.writeHead(204);
                response.end();
                resultResolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));
            });
            return;
        }
        response.writeHead(404);
        response.end();
    });

    return new Promise((resolve, reject) => {
        server.listen(0, '127.0.0.1', () => {
            const { port } = server.address();
            pageUrl = `http://127.0.0.1:${port}`;
            const profile = fs.mkdtempSync(path.join('/tmp', 'bikelog-update-xss-'));
            const chrome = spawn(chromePath, [
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                `--user-data-dir=${profile}`,
                pageUrl,
            ], { stdio: 'ignore' });

            const timeout = setTimeout(() => {
                resultReject(new Error('Timed out waiting for the update dialog to render'));
            }, 60000);

            const finish = async () => {
                clearTimeout(timeout);
                await new Promise((done) => {
                    chrome.once('exit', done);
                    chrome.kill('SIGKILL');
                });
                server.close();
                try {
                    fs.rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 50 });
                } catch (error) {
                    // The browser can still be releasing its profile after exit.
                }
            };

            result.then(async (probe) => {
                await finish();
                resolve(probe);
            }, async (error) => {
                await finish();
                reject(error);
            });
        });
        server.on('error', reject);
    });
}

async function openUpdateDialog(release, currentVersion = '1.0.0') {
    for (const filePath of [jqueryPath, bootstrapPath, chromePath]) {
        assert.equal(fs.existsSync(filePath), true, `missing test runtime file: ${filePath}`);
    }
    return startHarness(release, currentVersion);
}

test('release tag markup is inserted as text and does not run', async () => {
    const tagName = "<script>window.__releaseXss=1;window.__tokenSeen=document.getElementById('admin-update-panel').getAttribute('data-update-token')</script>";
    const probe = await openUpdateDialog({
        tag_name: tagName,
        html_url: `https://github.com/gztproject/BikeLog/releases/tag/${encodeURIComponent(tagName)}`,
        body: 'Ordinary release notes',
    });

    assert.equal(probe.error, null);
    assert.equal(
        probe.releaseXss,
        null,
        `tag script ran in the page (token seen: ${probe.tokenSeenByPayload})`
    );
    assert.equal(probe.tokenSeenByPayload, null);
    assert.equal(probe.counts.svg, 0);
    assert.equal(probe.counts.img, 0);
    assert.ok(probe.link, 'latest version link was not rendered');
    assert.equal(probe.link.text, tagName);
    assert.equal(probe.link.html.includes('<script'), false);
    assert.equal(allowedReleaseHref(probe.link.href), true);
});

test('svg markup in the release tag does not run through innerHTML', async () => {
    const tagName = '<svg/onload=window.__releaseXss=1>';
    const probe = await openUpdateDialog({
        tag_name: tagName,
        html_url: 'https://github.com/gztproject/BikeLog/releases/tag/v9.9.9',
        body: 'Ordinary release notes',
    });

    assert.equal(probe.releaseXss, null);
    assert.equal(probe.counts.svg, 0);
    assert.equal(probe.link.text, tagName);
});

test('release page URL markup does not run and only github https links stay linked', async () => {
    const breakout = 'https://github.com/gztproject/BikeLog/releases/tag/v1"></a><script>window.__urlXss=1</script>';
    const breakoutProbe = await openUpdateDialog({
        tag_name: 'v9.9.9',
        html_url: breakout,
        body: 'Ordinary release notes',
    });

    assert.equal(breakoutProbe.urlXss, null);
    assert.equal(breakoutProbe.counts.svg, 0);
    assert.ok(breakoutProbe.link, 'version text should stay in the dialog when the release URL is unsafe');
    assert.equal(breakoutProbe.link.text, 'v9.9.9');
    assert.equal(
        breakoutProbe.link.href == null || allowedReleaseHref(breakoutProbe.link.href),
        true
    );

    const scriptUrlProbe = await openUpdateDialog({
        tag_name: 'v9.9.9',
        html_url: 'javascript:window.__urlXss=1',
        body: 'Ordinary release notes',
    });

    assert.equal(scriptUrlProbe.urlXss, null);
    if (scriptUrlProbe.link) {
        assert.equal(allowedReleaseHref(scriptUrlProbe.link.href), true);
    }

    const foreignProbe = await openUpdateDialog({
        tag_name: 'v9.9.9',
        html_url: 'https://github.com.evil.example/gztproject/BikeLog/releases/tag/v9.9.9',
        body: 'Ordinary release notes',
    });

    if (foreignProbe.link) {
        assert.equal(allowedReleaseHref(foreignProbe.link.href), true);
        assert.equal(foreignProbe.link.href.includes('evil.example'), false);
    }
});

test('installed version from the check endpoint is inserted as text', async () => {
    const currentVersion = '<script>window.__currentXss=1</script>';
    const probe = await openUpdateDialog({
        tag_name: 'v9.9.9',
        html_url: 'https://github.com/gztproject/BikeLog/releases/tag/v9.9.9',
        body: 'Ordinary release notes',
    }, currentVersion);

    assert.equal(probe.currentXss, null);
    assert.equal(probe.counts.svg, 0);
    assert.ok(probe.current);
    assert.equal(probe.current.text, currentVersion);
    assert.equal(probe.current.html.includes('<script'), false);
});

test('release notes stay text when the body contains markup', async () => {
    const notes = '<script>window.__notesXss=1</script>\nSecond line <img src=x onerror=window.__notesXss=1>';
    const probe = await openUpdateDialog({
        tag_name: 'v9.9.9',
        html_url: 'https://github.com/gztproject/BikeLog/releases/tag/v9.9.9',
        body: notes,
    });

    assert.equal(probe.notesXss, null);
    assert.equal(probe.counts.img, 0);
    assert.ok(probe.notes);
    assert.equal(probe.notes.text.includes('<script>window.__notesXss=1</script>'), true);
    assert.equal(probe.notes.text.includes('Second line'), true);
    assert.equal(probe.notes.html.includes('<script'), false);
    assert.equal(probe.notes.html.includes('<img'), false);
});

test('a normal github release still shows the tag and release link', async () => {
    const probe = await openUpdateDialog({
        tag_name: 'v9.9.9',
        html_url: 'https://github.com/gztproject/BikeLog/releases/tag/v9.9.9',
        body: 'Fixed the updater.',
    }, 'v1.0.0');

    assert.equal(probe.releaseXss, null);
    assert.equal(probe.error, null);
    assert.ok(probe.link);
    assert.equal(probe.link.text, 'v9.9.9');
    assert.equal(probe.link.href, 'https://github.com/gztproject/BikeLog/releases/tag/v9.9.9');
    assert.equal(probe.current.text, 'v1.0.0');
    assert.equal(probe.notes.text.includes('Fixed the updater.'), true);
});
