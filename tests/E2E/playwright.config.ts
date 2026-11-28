import { defineConfig, devices } from '@playwright/test';

// runs inside the ddev-playwright container against the dev app: `ddev playwright test`
export default defineConfig({
    testDir: './tests',
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        // the E2E database seeded by `ddev e2e-reset`, not the dev one
        baseURL: 'https://e2e.cms-observer.ddev.site',
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions: {
            slowMo: Number(process.env.PLAYWRIGHT_SLOWMO ?? 0),
            args: ['--host-resolver-rules=MAP *.ddev.site web'],
        },
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
