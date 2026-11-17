#!/usr/bin/env node
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

function parseArgs(argv) {
  const out = {};
  for (let i = 2; i < argv.length; i++) {
    const arg = argv[i];
    if (arg.startsWith('--')) {
      const [k, v] = arg.slice(2).split('=');
      out[k] = v !== undefined ? v : argv[++i];
    }
  }
  return out;
}

const args = parseArgs(process.argv);
const htmlPath = path.resolve(args.html || '');
const outDir = path.resolve(args.outDir || '');
const duration = Math.max(1.0, parseFloat(args.duration || '6.0'));
const fps = Math.max(1, parseInt(args.fps || '30', 10));
const width = Math.max(320, parseInt(args.width || '1920', 10));
const height = Math.max(180, parseInt(args.height || '1080', 10));
const chromeBin = args.chrome || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

if (!htmlPath || !fs.existsSync(htmlPath)) {
  console.error(`HTML template not found: ${htmlPath}`);
  process.exit(1);
}
if (!fs.existsSync(chromeBin)) {
  console.error(`Headless Chrome binary not found: ${chromeBin}`);
  process.exit(1);
}

fs.mkdirSync(outDir, { recursive: true });

const params = new URLSearchParams({
  manual: '1',
  duration: String(duration),
  title: args.title || 'ffMix Live Broadcast',
  subtitle: args.subtitle || 'Normalized 1080p30 Feed',
  accent: args.accent || '#06b6d4',
});
const targetUrl = `file://${htmlPath}?${params.toString()}`;

const port = 9222 + Math.floor(Math.random() * 1000);
const userDataDir = fs.mkdtempSync('/tmp/ffmix-chrome-');

const chrome = spawn(chromeBin, [
  '--headless=new',
  '--disable-gpu',
  '--no-sandbox',
  '--hide-scrollbars',
  `--window-size=${width},${height}`,
  `--remote-debugging-port=${port}`,
  `--user-data-dir=${userDataDir}`,
  'about:blank',
], { stdio: 'ignore' });

async function waitForWsUrl() {
  for (let i = 0; i < 40; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/list`);
      const list = await res.json();
      if (Array.isArray(list) && list[0]?.webSocketDebuggerUrl) {
        return list[0].webSocketDebuggerUrl;
      }
    } catch {
      // retry
    }
    await new Promise((r) => setTimeout(r, 150));
  }
  throw new Error('Timed out waiting for headless Chrome DevTools endpoint.');
}

function createCdpClient(wsUrl) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(wsUrl);
    let nextId = 1;
    const pending = new Map();

    ws.addEventListener('open', () => {
      resolve({
        send(method, paramsObj = {}) {
          const id = nextId++;
          return new Promise((res, rej) => {
            pending.set(id, { res, rej });
            ws.send(JSON.stringify({ id, method, params: paramsObj }));
          });
        },
        close() {
          ws.close();
        },
      });
    });

    ws.addEventListener('message', (evt) => {
      const msg = JSON.parse(String(evt.data));
      if (msg.id && pending.has(msg.id)) {
        const { res, rej } = pending.get(msg.id);
        pending.delete(msg.id);
        if (msg.error) rej(new Error(msg.error.message));
        else res(msg.result);
      }
    });

    ws.addEventListener('error', (err) => reject(err));
  });
}

try {
  const wsUrl = await waitForWsUrl();
  const cdp = await createCdpClient(wsUrl);

  await cdp.send('Page.enable');
  await cdp.send('Runtime.enable');
  await cdp.send('Emulation.setDeviceMetricsOverride', {
    width,
    height,
    deviceScaleFactor: 1,
    mobile: false,
  });
  await cdp.send('Emulation.setDefaultBackgroundColorOverride', {
    color: { r: 0, g: 0, b: 0, a: 0 },
  });

  await cdp.send('Page.navigate', { url: targetUrl });
  await new Promise((r) => setTimeout(r, 400));

  const totalFrames = Math.max(1, Math.round(duration * fps));
  for (let frame = 0; frame < totalFrames; frame++) {
    const t = frame / fps;
    await cdp.send('Runtime.evaluate', {
      expression: `if (typeof window.ffmixSeek === 'function') { window.ffmixSeek(${t.toFixed(4)}); }`,
      awaitPromise: false,
    });
    const shot = await cdp.send('Page.captureScreenshot', {
      format: 'png',
      fromSurface: true,
    });
    const frameName = `frame_${String(frame + 1).padStart(4, '0')}.png`;
    fs.writeFileSync(path.join(outDir, frameName), Buffer.from(shot.data, 'base64'));
  }

  cdp.close();
  console.log(JSON.stringify({ frames: totalFrames, outDir }));
} finally {
  chrome.kill('SIGKILL');
  fs.rmSync(userDataDir, { recursive: true, force: true });
}
