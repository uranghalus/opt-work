import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const BASE = 'http://localhost:8000';
const CHROME =
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const OUT = '.impeccable/review';
mkdirSync(OUT, { recursive: true });

const EMAIL = 'shot-roles@optiworks.test';
const PASSWORD = 'password';

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    const result = await page.evaluate(
        async ({ email, password }) => {
            const token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const response = await fetch('/login', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify({ email, password }),
            });
            return { status: response.status };
        },
        { email: EMAIL, password: PASSWORD },
    );
    if (result.status !== 204 && result.status !== 200) {
        throw new Error(`login failed: ${JSON.stringify(result)}`);
    }
}

async function openConsole(page) {
    await page.goto(`${BASE}/hak-akses`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
}

const browser = await chromium.launch({
    executablePath: CHROME,
    headless: true,
});

// Desktop, light theme
{
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        deviceScaleFactor: 2,
    });
    const page = await context.newPage();
    await login(page);
    await openConsole(page);
    await page.screenshot({ path: `${OUT}/desktop.png`, fullPage: true });

    // Dirty state: grant the whole Work Order bank
    await page.getByLabel('Semua izin Work Order').click();
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${OUT}/desktop-dirty.png`, fullPage: true });

    // Sealed super_admin plate
    await page
        .locator('button[aria-pressed]', { hasText: 'Super Admin' })
        .first()
        .click();
    await page.waitForTimeout(600);
    await page.screenshot({ path: `${OUT}/desktop-sealed.png`, fullPage: true });

    // Dark theme (console stays dark; shell re-themes)
    await page.evaluate(() =>
        document.documentElement.classList.add('dark'),
    );
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${OUT}/desktop-dark.png`, fullPage: true });

    await context.close();
}

// Mobile, light theme
{
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true,
    });
    const page = await context.newPage();
    await login(page);
    await openConsole(page);
    await page.screenshot({ path: `${OUT}/mobile.png`, fullPage: true });
    await context.close();
}

await browser.close();
console.log('captures done');
