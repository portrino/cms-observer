import { expect, test } from '../fixtures';

test('deleting a project needs the exact project name', async ({ page }) => {
    const name = `e2e-${Date.now()}`;

    await page.goto('/projects/new');
    await page.getByLabel('Name').fill(name);
    await page.getByLabel('Site URL').fill('https://e2e.example.test');
    await page.getByRole('button', { name: 'Create' }).click();
    await page.waitForURL(/\/projects\/\d+$/);

    await page.goto(`${page.url()}/edit`);
    await page.getByRole('button', { name: 'Delete project' }).first().click();

    const modal = page.getByRole('dialog', { name: 'Delete this project' });
    const submit = modal.getByRole('button', { name: 'Delete project' });
    await expect(submit).toBeDisabled();

    await modal.getByLabel('Confirmation string').fill('wrong');
    await expect(submit).toBeDisabled();

    await modal.getByLabel('Confirmation string').fill(` ${name} `);
    await expect(submit).toBeEnabled();
    await submit.click();

    await page.waitForURL('/');
    await expect(page.getByText(`Project "${name}" and all its data was deleted.`)).toBeVisible();
});
