import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

// Visual verification of the HOD review/assign panel on the WO show page
// (wo-slice-1 ticket 04). The fixture is created by tmp-wo-fixture.php and
// cleaned up right after; evidence lands in .impeccable/review/.
const WO_ID = process.argv[2];
const BASE = 'http://localhost:8000';
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const OUT = '.impeccable/review';
mkdirSync(OUT, { recursive: true });

const EMAIL = 'shot-hod@optiworks.test';
const PASSWORD = 'password';

async function login(context) {
    await context.request.get(`${BASE}/saml/denied`);
    const cookies = await context.cookies();
    const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
    if (!xsrf) {
        throw new Error('no XSRF-TOKEN cookie');
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
        throw new Error(`login failed: ${response.status()}`);
    }
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
await page.goto(`${BASE}/hq/work-orders/${WO_ID}`, {
    waitUntil: 'networkidle',
});
await page.waitForTimeout(800);
await page.screenshot({ path: `${OUT}/wo-panel-light.png`, fullPage: true });

await page.evaluate(() => document.documentElement.classList.add('dark'));
await page.waitForTimeout(400);
await page.screenshot({ path: `${OUT}/wo-panel-dark.png`, fullPage: true });

await context.close();
await browser.close();
console.log('wo panel captures done');
