import { expect, test } from '../fixtures';

// Filters apply while typing/selecting (auto_submit_controller.js) and only swap the results frame

test('package filter applies without a submit button', async ({ page }) => {
    await page.goto('/packages');
    await expect(page.getByRole('button', { name: 'Filter' })).toBeHidden();

    const search = page.getByRole('searchbox', { name: 'Search' });
    await search.fill(`no-such-package-${Date.now()}`);

    await expect(page.getByText('No packages match.')).toBeVisible();
    await expect(page).toHaveURL(/[?&]q=no-such-package-/);
    await expect(search).toBeFocused();

    await page.getByRole('combobox', { name: 'Package type' }).selectOption({ label: 'WordPress Plugin' });
    await expect(page).toHaveURL(/[?&]type=wordpress-plugin/);
});

test('history filter applies without a submit button', async ({ page, createProject }) => {
    await createProject('e2e-history');

    await page.getByRole('link', { name: 'History', exact: true }).click();
    await expect(page.getByText('No changes recorded.')).toBeVisible();

    await page.getByRole('combobox', { name: 'Type of change' }).selectOption({ label: 'Package removed' });
    await expect(page.getByText('No changes recorded for this filter.')).toBeVisible();
    await expect(page).toHaveURL(/[?&]type=package-removed/);
});

test('project filters apply without a submit button', async ({ page }) => {
    await page.goto('/');

    await page.getByRole('searchbox', { name: 'Search' }).fill(`no-such-project-${Date.now()}`);
    await expect(page.getByText('No projects match.')).toBeVisible();
    await expect(page).toHaveURL(/[?&]q=no-such-project-/);

    await page.getByRole('combobox', { name: 'Security' }).selectOption({ label: 'PHP/database warnings' });
    await expect(page).toHaveURL(/[?&]security=infrastructure/);
    await page.getByRole('combobox', { name: 'SLA' }).selectOption({ label: 'With SLA' });
    await expect(page).toHaveURL(/[?&]sla=with/);
});
