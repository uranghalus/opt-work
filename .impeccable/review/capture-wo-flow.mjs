import { chromium } from 'playwright-core';

// Full-flow verification: submit the review decision (execute immediately),
// then capture the post-review state — hod_approved badge, Keputusan HOD
// card, and the assign panel listing the department's employees.
const WO_ID = process.argv[2];
const BASE = 'http://localhost:8000';
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

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

await page.getByRole('button', { name: 'Simpan Keputusan' }).click();
await page.waitForURL(`**/hq/work-orders/${WO_ID}`, { timeout: 15000 });
await page.waitForLoadState('networkidle');
await page.waitForTimeout(600);

await page.screenshot({ path: '.impeccable/review/wo-panel-approved.png', fullPage: true });

await page.evaluate(() => document.documentElement.classList.add('dark'));
await page.waitForTimeout(400);
await page.screenshot({ path: '.impeccable/review/wo-panel-approved-dark.png', fullPage: true });

await context.close();
await browser.close();
console.log('flow captures done');
