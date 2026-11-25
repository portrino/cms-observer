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

type Project = { name: string; url: string; token: string };

// logs in through the real form, so the JS double-submit CSRF cookie (csrf_protection_controller.js) is exercised too
export const test = base.extend<{ loggedIn: void; createProject: (prefix: string, cms?: 'TYPO3' | 'WordPress') => Promise<Project> }>({
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

        await use(async (prefix, cms = 'TYPO3') => {
            const name = `${prefix}-${Date.now()}`;
            await page.goto('/projects/new');
            await page.getByLabel('Name').fill(name);
            await page.getByLabel('Site URL').fill(`https://${prefix}.example.test`);
            await page.getByLabel('Type').selectOption({ label: cms });
            await page.getByRole('button', { name: 'Create' }).click();
            await page.waitForURL(/\/projects\/\d+$/);
            // shown once after creating, for clients that push a report
            const token = (await page.locator('.alert code').textContent()) ?? '';
            const project = { name, url: page.url(), token };
            created.push(project);

            return project;
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
