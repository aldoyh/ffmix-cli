const sourceNames = {
    1: 'Source 1 · Alex Morgan',
    2: 'Source 2 · Jordan Lee',
    3: 'Source 3 · Samira Khan',
    4: 'Source 4 · Riley Imani',
};

let previewSource = 1;
let pgmSource = 1;

function updateMultiviewTally() {
    document.querySelectorAll('[data-multiview-source]').forEach((tile) => {
        const source = Number(tile.dataset.multiviewSource);
        const isPreview = source === previewSource;
        const isPgm = source === pgmSource;

        tile.classList.toggle('source-selected', isPreview);
        tile.classList.toggle('source-pgm', isPgm);
        tile.setAttribute('aria-pressed', isPreview ? 'true' : 'false');
    });

    const previewName = document.querySelector('#preview-name');
    const pgmName = document.querySelector('#pgm-name');
    const onAirLabel = document.querySelector('#on-air-label');

    if (previewName) {
        previewName.textContent = sourceNames[previewSource];
    }

    if (pgmName) {
        pgmName.textContent = sourceNames[pgmSource];
    }

    if (onAirLabel) {
        onAirLabel.textContent = `ON AIR · PGM ${pgmSource}`;
    }
}

document.querySelectorAll('[data-multiview-source]').forEach((tile) => {
    tile.addEventListener('click', () => {
        previewSource = Number(tile.dataset.multiviewSource);
        updateMultiviewTally();
    });
});

document.querySelector('#take-pgm')?.addEventListener('click', () => {
    pgmSource = previewSource;
    updateMultiviewTally();
});

document.querySelector('#enable-camera')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;

    if (!navigator.mediaDevices?.getUserMedia) {
        button.textContent = 'Camera unavailable';
        button.disabled = true;
        reportRuntimeError('Camera', 'Camera capture is unavailable in this browser');
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false,
        });
        const cameraFeed = document.querySelector('#camera-feed');

        if (cameraFeed) {
            cameraFeed.srcObject = stream;
            cameraFeed.classList.add('camera-live');
        }

        button.textContent = 'Local camera live';
        button.disabled = true;
    } catch (error) {
        button.textContent = 'Camera permission needed';
        reportRuntimeError('Camera', error instanceof Error ? error.message : 'Camera permission denied or unavailable');
    }
});

function reportRuntimeError(service, message) {
    const error = document.querySelector('[data-runtime-error]');
    if (error) {
        error.textContent = `${service} error · ${message}`;
        error.closest('.footer-runtime')?.classList.add('has-error');
    }
}

function setFooterService(service, text, state = 'ready') {
    const element = document.querySelector(`[data-service="${service}"]`);
    if (!element) {
        return;
    }

    element.dataset.state = state;
    const label = element.querySelector('[data-service-text]');
    if (label) {
        label.textContent = text;
    }
}

async function pollSystemStatus() {
    try {
        const response = await fetch('/system/status', {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error(`Status check returned ${response.status}`);
        }

        const status = await response.json();
        setFooterService('web', 'Ready', 'ready');
        setFooterService(
            'watchdog',
            status.watchdog?.running ? `PID ${status.watchdog.pid}` : 'Stopped',
            status.watchdog?.running ? 'ready' : 'idle',
        );
        setFooterService(
            'stream',
            status.stream === 'running'
                ? 'Recording'
                : (status.stream === 'error' ? 'Error' : (status.stream === 'standby' ? 'Standby' : 'Ready')),
            status.stream === 'running'
                ? 'ready'
                : (status.stream === 'error' ? 'error' : 'idle'),
        );

        if (status.latest_error) {
            reportRuntimeError(status.latest_error.service, status.latest_error.message);
        }
    } catch (error) {
        setFooterService('web', 'Unavailable', 'error');
        reportRuntimeError('Web', error.message);
    }
}

window.addEventListener('error', (event) => {
    reportRuntimeError('Browser', event.message || 'Unexpected browser error');
});

window.addEventListener('unhandledrejection', (event) => {
    const message = event.reason instanceof Error ? event.reason.message : String(event.reason);
    reportRuntimeError('Browser', message || 'Unhandled browser promise rejection');
});

window.addEventListener('livewire:init', () => {
    window.Livewire?.on('error', (payload) => {
        reportRuntimeError(payload.title || 'Livewire', payload.message || 'The request failed without a detailed message');
    });
});

updateMultiviewTally();
pollSystemStatus();
window.setInterval(pollSystemStatus, 5000);
