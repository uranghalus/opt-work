import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

// Visual verification for the Izin page (`/settings/permissions`) — the
// leftover manual check from 2026-10-13 (TODO item: light + dark + dialog
// error state), now automated the same way as the Peran console captures.
//
// Modes:
//   base  — light + dark, as a role.read/permission.read-only viewer
//           (ticket 03 state: action buttons hidden)
//   error — with permission.create granted: open "Tambah Izin", submit
//           empty, capture the validation error state
const MODE = process.argv[2] ?? 'base';
const BASE = 'http://localhost:8000';
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const OUT = '.impeccable/review';
mkdirSync(OUT, { recursive: true });

const EMAIL = 'shot-perm@optiworks.test';
const PASSWORD = 'password';

async function login(context) {
    // The app is SSO-first: only POST /login exists (no login GET view), so
    // bootstrap the session via the public denial page — it passes the web
    // middleware and sets XSRF-TOKEN + laravel-session cookies. Playwright's
    // request context shares the cookie jar with the pages.
    await context.request.get(`${BASE}/saml/denied`);

    const cookies = await context.cookies();
    const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
    if (!xsrf) {
        throw new Error('no XSRF-TOKEN cookie after GET /saml/denied');
    }

    const response = await context.request.post(`${BASE}/login`, {
        headers: {
            Referer: `${BASE}/login`,
            'X-XSRF-TOKEN': decodeURIComponent(xsrf.value),
            Accept: 'application/json',
        },
        data: { email: EMAIL, password: PASSWORD },
    });
    if (response.status() !== 204 && response.status() !== 200) {
        throw new Error(
            `login failed: ${response.status()} ${await response.text()}`,
        );
    }
}

async function openPage(page) {
    await page.goto(`${BASE}/settings/permissions`, {
        waitUntil: 'networkidle',
    });
    await page.waitForTimeout(800);
}

const browser = await chromium.launch({
    executablePath: CHROME,
    headless: true,
});

const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
});
const page = await context.newPage();
await login(context);
await openPage(page);

if (MODE === 'base') {
    await page.screenshot({ path: `${OUT}/perm-light.png`, fullPage: true });

    await page.evaluate(() =>
        document.documentElement.classList.add('dark'),
    );
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${OUT}/perm-dark.png`, fullPage: true });
    console.log('base captures done');
} else {
    await page.getByRole('button', { name: 'Tambah Izin' }).click();
    await page.waitForTimeout(400);
    // Submit empty: both required fields fail → wait for the validation
    // errors to render (aria-invalid flags), not the in-flight spinner.
    await page.getByRole('button', { name: 'Buat Izin' }).click();
    await page
        .locator('[aria-invalid="true"]')
        .first()
        .waitFor({ timeout: 10000 });
    await page.waitForTimeout(300);
    await page.screenshot({
        path: `${OUT}/perm-dialog-error.png`,
        fullPage: true,
    });
    console.log('error capture done');
}

await context.close();
await browser.close();
