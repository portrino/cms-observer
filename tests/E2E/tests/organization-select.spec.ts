import { expect, test } from '../fixtures';

test('a new organization can be entered from the organization select', async ({ page }) => {
    await page.goto('/projects/new');
    const select = page.getByLabel('Organization', { exact: true });
    const newOrganization = page.getByLabel('New organization');
    await expect(newOrganization).toBeHidden();

    await select.selectOption({ label: '+ New organization…' });
    await expect(newOrganization).toBeVisible();
    await expect(newOrganization).toBeFocused();

    await newOrganization.fill('Draft Agency');
    await select.selectOption({ label: 'No organization' });
    await expect(newOrganization).toBeHidden();
    await expect(newOrganization).toHaveValue('');
});
