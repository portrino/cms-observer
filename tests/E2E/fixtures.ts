import { test as base } from '@playwright/test';

export { expect } from '@playwright/test';

/**
 * Dev-DB logins, create them once:
 *   ddev console app:user:create e2e@example.test E2E e2e-password
 *   ddev console app:user:create e2e-2fa@example.test "E2E 2FA" e2e-password   (two-factor.spec.ts)
 */
export const credentials = {
    email: process.env.E2E_EMAIL ?? 'e2e@example.test',
    password: process.env.E2E_PASSWORD ?? 'e2e-password',
};

type Project = { name: string; url: string };

// logs in through the real form, so the JS double-submit CSRF cookie (csrf_protection_controller.js) is exercised too
export const test = base.extend<{ loggedIn: void; createProject: (prefix: string) => Promise<Project> }>({
    loggedIn: [async ({ page }, use) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill(credentials.email);
        await page.getByLabel('Password').fill(credentials.password);
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL('/');
        await use();
    }, { auto: true }],

    // creates a project in the dev DB and deletes it again after the test, also when the test failed
    createProject: async ({ page }, use) => {
        const created: Project[] = [];

        await use(async (prefix) => {
            const name = `${prefix}-${Date.now()}`;
            await page.goto('/projects/new');
            await page.getByLabel('Name').fill(name);
            await page.getByLabel('Site URL').fill(`https://${prefix}.example.test`);
            await page.getByRole('button', { name: 'Create' }).click();
            await page.waitForURL(/\/projects\/\d+$/);
            created.push({ name, url: page.url() });

            return { name, url: page.url() };
        });

        for (const project of created) {
            await page.goto(`${project.url}/edit`);
            await page.getByRole('button', { name: 'Delete project' }).first().click();
            const modal = page.getByRole('dialog', { name: 'Delete this project' });
            await modal.getByLabel('Confirmation string').fill(project.name);
            await modal.getByRole('button', { name: 'Delete project' }).click();
            await page.waitForURL('/');
        }
    },
});
