const STEPS = ['requirements', 'fetch', 'checkout', 'composer', 'frontend', 'migrate', 'cache'];

const $panel = $('#admin-update-panel');
const statusUrl = $panel.data('status-url');
const channelUrl = $panel.data('channel-url');
const runUrl = $panel.data('run-url');
const updateToken = $panel.data('update-token');
const checkFailed = $panel.data('check-failed');
const startFailed = $panel.data('start-failed');
const upToDate = $panel.data('up-to-date');
const noneLabel = $panel.data('none');
const availableTemplate = $panel.data('available');

let running = false;
let outcome = 'idle';
let canStart = false;
let currentStep = null;

const $channelButtons = $panel.find('.js-update-channel');
const $channelWarning = $panel.find('.js-update-channel-warning');
const $checking = $panel.find('.js-update-checking');
const $summary = $panel.find('.js-update-summary');
const $currentVersion = $panel.find('.js-update-current-version');
const $latestVersion = $panel.find('.js-update-latest-version');
const $releaseLink = $panel.find('.js-update-release-link');
const $summaryText = $panel.find('.js-update-summary-text');
const $discardWarning = $panel.find('.js-update-discard-warning');
const $targetWarning = $panel.find('.js-update-target-warning');
const $prodWarning = $panel.find('.js-update-prod-warning');
const $error = $panel.find('.js-update-error');
const $checkButton = $panel.find('.js-update-check');
const $startButton = $panel.find('.js-update-start');
const $notesCard = $panel.find('.js-update-notes-card');
const $notes = $panel.find('.js-update-notes');
const $progressCard = $panel.find('.js-update-progress-card');
const $progressValue = $panel.find('.js-update-progress-value');
const $progressBar = $panel.find('.js-update-progress-bar');
const $progressLabel = $panel.find('.js-update-progress-label');
const $runningNotice = $panel.find('.js-update-running-notice');
const $log = $panel.find('.js-update-log');
const $reloadButton = $panel.find('.js-update-reload');
const $retryButton = $panel.find('.js-update-retry');

function onLeave(event) {
    if (!running) {
        return;
    }
    event.preventDefault();
    event.returnValue = '';
}

window.addEventListener('beforeunload', onLeave);

function highlightChannel(channel) {
    $channelButtons.each(function () {
        const $button = $(this);
        const selected = $button.data('channel') === channel;
        $button.toggleClass('btn-primary', selected);
        $button.toggleClass('btn-outline-primary', !selected);
        $button.attr('aria-pressed', selected ? 'true' : 'false');
    });
}

function showChecking() {
    $checking.prop('hidden', false);
    $summary.prop('hidden', true);
    $error.prop('hidden', true);
    $notesCard.prop('hidden', true);
    $startButton.prop('disabled', true);
}

function showError(message) {
    $checking.prop('hidden', true);
    $summary.prop('hidden', true);
    $error.prop('hidden', false).text(message);
    canStart = false;
    $startButton.prop('disabled', true);
}

function showLatest(version, url) {
    const label = version || '—';
    const safe = typeof url === 'string' && url.startsWith('https://github.com/gztproject/BikeLog/');

    $latestVersion.prop('hidden', safe).text(label);
    $releaseLink.prop('hidden', !safe);
    if (safe) {
        $releaseLink.attr('href', url).text(label);
    }
}

function render(data) {
    $checking.prop('hidden', true);
    highlightChannel(data.channel);
    $channelWarning.prop('hidden', data.channel !== 'prerelease');

    if (data.error) {
        showError(checkFailed);
        return;
    }

    $error.prop('hidden', true);
    $summary.prop('hidden', false);
    $currentVersion.text(data.currentVersion || '');
    showLatest(data.latestVersion, data.releaseUrl);
    $notesCard.prop('hidden', !data.notesHtml);
    $notes.html(data.notesHtml || '');

    const available = Boolean(data.updateAvailable);
    canStart = available && Boolean(data.canUpdate);
    $discardWarning.prop('hidden', !available);
    $targetWarning.prop('hidden', !(available && data.prerelease));
    $prodWarning.prop('hidden', !(available && !data.canUpdate));
    $startButton.prop('disabled', !canStart);

    if (!data.latestVersion) {
        $summaryText.text(noneLabel);
    } else if (available) {
        $summaryText.text(String(availableTemplate).split('__VERSION__').join(data.latestVersion));
    } else {
        $summaryText.text(upToDate);
    }
}

async function refresh(force) {
    showChecking();
    const url = new URL(statusUrl, window.location.origin);
    if (force) {
        url.searchParams.set('refresh', '1');
    }

    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(checkFailed);
        }
        render(await response.json());
    } catch (error) {
        showError(checkFailed);
    }
}

function lock(locked) {
    $channelButtons.prop('disabled', locked);
    $checkButton.prop('disabled', locked);
    if (locked) {
        $startButton.prop('disabled', true);
    }
}

function setProgress(progress, message) {
    const value = Math.max(0, Math.min(100, Number(progress) || 0));
    const text = `${value}%`;
    $progressValue.text(text);
    $progressBar.css('width', text).text(text).attr('aria-valuenow', String(value));
    $progressLabel.text(message || '');
}

function markStep(step, failed = false) {
    if (step && step !== 'done') {
        currentStep = step;
    }

    const index = STEPS.indexOf(step);
    $panel.find('[data-step]').each(function () {
        const $item = $(this);
        const itemIndex = STEPS.indexOf($item.data('step'));
        if (step === 'done') {
            $item.attr('data-state', 'done');
            return;
        }
        if (failed && $item.data('step') === (step || currentStep)) {
            $item.attr('data-state', 'failed');
            return;
        }
        if (index === -1) {
            return;
        }
        if (itemIndex < index) {
            $item.attr('data-state', 'done');
        } else if (itemIndex === index) {
            $item.attr('data-state', failed ? 'failed' : 'current');
        } else {
            $item.attr('data-state', 'pending');
        }
    });
}

function resetProgress() {
    currentStep = null;
    $panel.find('[data-step]').attr('data-state', 'pending');
    $progressBar.addClass('progress-bar-animated progress-bar-striped').removeClass('bg-danger bg-success');
    $runningNotice.prop('hidden', false);
    setProgress(0, '');
}

function appendLog(message) {
    $log.append(document.createTextNode(`${message}\n`));
    $log.scrollTop($log[0].scrollHeight);
}

function fail(message) {
    outcome = 'failed';
    markStep(currentStep, true);
    appendLog(message);
    $progressBar.removeClass('progress-bar-animated').addClass('bg-danger');
    $runningNotice.prop('hidden', true);
}

function handleEvent(event) {
    switch (event.type) {
    case 'meta':
        appendLog(`Updating from ${event.currentVersion} to ${event.targetVersion}.`);
        break;
    case 'progress':
        markStep(event.step);
        setProgress(event.progress, event.message);
        break;
    case 'output':
        appendLog(event.channel === 'stderr' ? `[stderr] ${event.message}` : event.message);
        break;
    case 'complete':
        outcome = 'success';
        markStep('done');
        setProgress(100, event.message);
        $progressBar.removeClass('progress-bar-animated').addClass('bg-success');
        $runningNotice.prop('hidden', true);
        break;
    case 'failed':
        fail(event.message || startFailed);
        setProgress(event.progress, event.message);
        break;
    default:
        appendLog(JSON.stringify(event));
        break;
    }
}

function consumeLines(chunk) {
    String(chunk).split('\n').filter((line) => line.trim() !== '').forEach((line) => {
        try {
            handleEvent(JSON.parse(line));
        } catch (error) {
            appendLog(line);
        }
    });
}

async function stream() {
    const response = await fetch(runUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/x-ndjson',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams({ token: updateToken }),
    });

    if (!response.ok) {
        throw new Error(startFailed);
    }

    if (!response.body || typeof response.body.getReader !== 'function') {
        consumeLines(await response.text());
        return;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();
        buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';
        consumeLines(lines.join('\n'));
        if (done) {
            break;
        }
    }

    if (buffer.trim() !== '') {
        consumeLines(buffer);
    }
}

async function start() {
    if (running || $startButton.prop('disabled')) {
        return;
    }

    running = true;
    outcome = 'running';
    lock(true);
    resetProgress();
    $progressCard.prop('hidden', false);
    $reloadButton.prop('hidden', true);
    $retryButton.prop('hidden', true);
    $log.text('');

    try {
        await stream();
    } catch (error) {
        fail(error.message || startFailed);
    } finally {
        running = false;
        lock(false);
        $startButton.prop('disabled', !canStart || outcome === 'success');
        $reloadButton.prop('hidden', outcome !== 'success');
        $retryButton.prop('hidden', outcome !== 'failed');
    }
}

$checkButton.on('click', () => {
    if (!running) {
        refresh(true);
    }
});

$channelButtons.on('click', async function () {
    if (running) {
        return;
    }

    const channel = $(this).data('channel');
    highlightChannel(channel);
    showChecking();

    try {
        const response = await fetch(channelUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({
                token: updateToken,
                channel,
            }),
        });

        if (!response.ok) {
            await refresh(false);
            $error.prop('hidden', false).text(checkFailed);
            return;
        }
    } catch (error) {
        showError(checkFailed);
        return;
    }

    await refresh(true);
});

$startButton.on('click', start);
$retryButton.on('click', start);
$reloadButton.on('click', () => window.location.reload());

refresh(false);
