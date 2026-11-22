import { test, expect } from '../fixtures';

test('rows of a calculation can be added and removed before saving', async ({ page, createProject }) => {
    const project = await createProject('e2e-upgrade');
    const token = await page.locator('.alert-warning code').innerText();

    // a report makes the project a TYPO3 13 site with one extension
    const response = await page.request.post('/api/v1/sites/report', {
        headers: { Authorization: `Bearer ${token}` },
        data: {
            coreVersion: '13.4.20', phpVersion: '8.3.14', dbVersion: '10.11.19-MariaDB',
            domains: ['e2e-upgrade.example.test'],
            packages: [{ name: 'news', type: 'extension', title: 'News', version: '12.3.1', active: true }],
        },
    });
    expect(response.status()).toBe(202);

    await page.goto(`${project.url}/calculations`);
    await page.getByLabel(/New calculation from TYPO3/).selectOption('14');
    await page.getByRole('button', { name: 'Start' }).click();

    const positions = page.locator('#upgrade-positions tbody tr');
    await expect(positions).toHaveCount(3);
    await positions.nth(1).getByRole('button', { name: 'Remove row' }).click();
    await expect(positions).toHaveCount(2);

    const optional = page.locator('#upgrade-calculation-form .card').filter({ has: page.locator('#upgrade-optional') });
    await optional.getByRole('button', { name: 'Add row' }).click();
    await optional.getByRole('button', { name: 'Add row' }).click();
    const optionalRows = page.locator('#upgrade-optional tbody tr');
    await optionalRows.nth(0).locator('input').first().fill('Content blocks migration');
    await optionalRows.nth(0).locator('input[name$="[maxHours]"]').fill('8');
    await optionalRows.nth(1).getByRole('button', { name: 'Remove row' }).click();

    await page.getByLabel('Hourly rate (EUR)').fill('100');
    await page.getByLabel('Project management (%)').fill('10');
    await page.getByLabel('Your name or initials').fill('E2E');
    await page.getByRole('button', { name: 'Save as new version' }).click();

    await expect(page.locator('#upgrade-positions tbody tr')).toHaveCount(2);
    await expect(page.locator('#upgrade-optional')).toContainText('Content blocks migration');
    await expect(page.locator('#upgradeTotals')).toContainText('Optional, incl. project management');
    await expect(page.locator('#upgrade-packages')).toContainText('news');
});
