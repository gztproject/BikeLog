const { isNewerVersion } = require('../common/update/version');

const $updatePanel = $('#admin-update-panel');
const checkUrl = $updatePanel.data('check-url');
const runUrl = $updatePanel.data('run-url');
const updateToken = $updatePanel.data('update-token');
const releaseApiUrl = $updatePanel.data('release-api-url');

let updateInProgress = false;
let lastUpdateStatus = 'idle';

function setModalBody(content) {
    $('.modal-body').html(content);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function renderLoadingState(message) {
    setModalBody(`
        <div class="update-modal__state">
            <div class="spinner-border text-primary mr-3" role="status" aria-hidden="true"></div>
            <span>${message}</span>
        </div>
    `);
}

function renderUpdateDetails(currentVersion, newVersion, newUrl, changes) {
    const notes = escapeHtml(changes || 'No release notes were provided.').replaceAll('\n', '<br>');

    setModalBody(`
        <div class="alert alert-info mb-3" id="update-status-banner"></div>
        <div class="update-modal__summary">
            <div class="update-modal__metric">
                <span class="update-modal__label">Current version</span>
                <strong id="currentA">${currentVersion}</strong>
            </div>
            <div class="update-modal__metric">
                <span class="update-modal__label">Latest version</span>
                <a href="${newUrl}" target="_blank" rel="noopener noreferrer" id="newA">${newVersion}</a>
            </div>
        </div>
        <div class="update-modal__progress-shell" id="update-progress-shell" hidden>
            <div class="update-modal__progress-header">
                <span>Progress</span>
                <strong id="update-progress-value">0%</strong>
            </div>
            <div class="progress">
                <div
                    class="progress-bar progress-bar-striped progress-bar-animated"
                    id="update-progress-bar"
                    role="progressbar"
                    style="width: 0%;"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="0"
                >0%</div>
            </div>
            <div class="update-modal__progress-label" id="update-progress-label"></div>
        </div>
        <div class="update-modal__release-notes">
            <h4>Changes</h4>
            <div id="changes">${notes}</div>
        </div>
        <div class="update-modal__log-shell" id="update-log-shell" hidden>
            <h4>Live output</h4>
            <pre class="update-modal__log" id="update-log"></pre>
        </div>
    `);
}

function renderErrorState(message) {
    setModalBody(`<div class="alert alert-danger mb-0">${message}</div>`);
}

function setStatusBanner(message, tone = 'info') {
    const $banner = $('#update-status-banner');

    $banner.removeClass('alert-info alert-success alert-danger alert-warning');
    $banner.addClass(`alert-${tone}`);
    $banner.text(message);
}

function setProgress(progress, message) {
    const normalizedProgress = Math.max(0, Math.min(100, Number(progress) || 0));
    const progressText = `${normalizedProgress}%`;

    $('#update-progress-shell').prop('hidden', false);
    $('#update-progress-value').text(progressText);
    $('#update-progress-label').text(message || '');
    $('#update-progress-bar')
        .css('width', progressText)
        .attr('aria-valuenow', normalizedProgress)
        .text(progressText);
}

function appendLogLine(message, channel = 'stdout') {
    const $log = $('#update-log');

    $('#update-log-shell').prop('hidden', false);
    $log.append(document.createTextNode(`${channel === 'stderr' ? '[stderr] ' : ''}${message}\n`));
    $log.scrollTop($log.prop('scrollHeight'));
}

function setModalLocked(locked) {
    $('#btn-close-update-modal, #btn-cancel-update').prop('disabled', locked);
    $('#btn-check-updates').prop('disabled', locked);
}

function updateActionState(currentVersion, newVersion) {
    const $button = $('#btn-do-update');

    $button.removeAttr('title').removeAttr('data-toggle');

    if (isDev()) {
        $button.prop('disabled', true);
        $button.attr('title', "Can't update in DEV env.");
        $button.attr('data-toggle', 'tooltip');
        return {
            message: 'Updates can only be started in production.',
            tone: 'warning',
        };
    }

    if (isNewerVersion(currentVersion, newVersion)) {
        $button.prop('disabled', false);
        $button.attr('data-version', newVersion);
        return {
            message: `Version ${newVersion} is ready to install.`,
            tone: 'info',
        };
    }

    $button.prop('disabled', true);
    $button.removeAttr('data-version');

    return {
        message: 'You are already on the latest release.',
        tone: 'success',
    };
}

function applyUpdateAvailabilityState(currentVersion, newVersion) {
    const state = updateActionState(currentVersion, newVersion);

    setStatusBanner(state.message, state.tone);
}

function checkUpdates() {
    renderLoadingState('Checking for updates...');
    $('#btn-do-update').prop('disabled', true).removeAttr('data-version');

    $.when(
        $.getJSON(releaseApiUrl),
        $.get(checkUrl)
    )
        .done(function onUpdatesLoaded(releaseResponse, currentResponse) {
            const releaseData = releaseResponse[0];
            const currentData = currentResponse[0];
            const currentVersion = currentData.current_version;
            const newVersion = releaseData.tag_name;
            const newUrl = releaseData.html_url;
            const changes = releaseData.body || '';

            renderUpdateDetails(currentVersion, newVersion, newUrl, changes);
            applyUpdateAvailabilityState(currentVersion, newVersion);
        })
        .fail(function onUpdateError() {
            renderErrorState('Unable to load update information.');
            $('#btn-do-update').prop('disabled', true);
        });
}

function handleStreamEvent(event) {
    switch (event.type) {
    case 'meta':
        appendLogLine(`Updating from ${event.currentVersion} to ${event.targetVersion}.`);
        break;
    case 'progress':
        setProgress(event.progress, event.message);
        setStatusBanner(event.message, 'info');
        break;
    case 'output':
        appendLogLine(event.message, event.channel);
        break;
    case 'complete':
        lastUpdateStatus = 'success';
        setProgress(event.progress, event.message);
        setStatusBanner(event.message, 'success');
        break;
    case 'failed':
        lastUpdateStatus = 'failed';
        setProgress(event.progress, event.message);
        setStatusBanner(event.message, 'danger');
        appendLogLine(event.message, 'stderr');
        break;
    default:
        appendLogLine(JSON.stringify(event));
        break;
    }
}

function handleStreamChunk(chunk, onEvent) {
    return chunk
        .split('\n')
        .filter((line) => line.trim() !== '')
        .forEach(function onLine(line) {
            try {
                onEvent(JSON.parse(line));
            } catch (error) {
                appendLogLine(line, 'stderr');
            }
        });
}

async function streamUpdate(version) {
    const response = await fetch(runUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/x-ndjson',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: $.param({
            token: updateToken,
            version,
        }),
    });

    if (!response.ok) {
        throw new Error('Unable to start the update.');
    }

    if (!response.body || typeof response.body.getReader !== 'function') {
        handleStreamChunk(await response.text(), handleStreamEvent);
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
        handleStreamChunk(lines.join('\n'), handleStreamEvent);

        if (done) {
            break;
        }
    }

    if (buffer.trim() !== '') {
        handleStreamChunk(buffer, handleStreamEvent);
    }
}

$('#btn-check-updates').on('click', function onCheckUpdatesClick() {
    const $confirm = $('#updateModal');

    $confirm.modal('show');
    checkUpdates();
});

$('#btn-do-update').on('click', async function onDoUpdateClick() {
    const version = $('#btn-do-update').attr('data-version');

    if (!version || updateInProgress) {
        return;
    }

    updateInProgress = true;
    lastUpdateStatus = 'running';
    setModalLocked(true);
    $('#btn-do-update').prop('disabled', true);
    setStatusBanner('Starting update. Keep this dialog open.', 'info');
    setProgress(0, 'Waiting for the updater to start.');
    $('#update-log').text('');
    $('#update-log-shell').prop('hidden', false);

    try {
        await streamUpdate(version);

        if (lastUpdateStatus === 'success') {
            window.setTimeout(checkUpdates, 1200);
        }
    } catch (error) {
        lastUpdateStatus = 'failed';
        setStatusBanner(error.message || 'Update failed.', 'danger');
        appendLogLine(error.message || 'Update failed.', 'stderr');
    } finally {
        updateInProgress = false;
        setModalLocked(false);

        if (lastUpdateStatus !== 'success' && $('#btn-do-update').attr('data-version') && !isDev()) {
            $('#btn-do-update').prop('disabled', false);
        }
    }
});

function isDev() {
    const environment = String($updatePanel.data('app-env') || '').toLowerCase();

    return environment !== 'prod';
}
