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

    await page.goto(project.url);
    await page.getByRole('button', { name: 'More actions' }).click();
    await page.getByRole('link', { name: 'Upgrade calculations' }).click();
    await page.getByLabel(/New calculation from TYPO3/).selectOption('14');
    await page.getByRole('button', { name: 'Start' }).click();
    await page.waitForURL(/calculations\/new/);

    // on a phone only the tables scroll sideways, not the page
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'page overflow on a phone').toBe(0);
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.reload();

    const positions = page.locator('#upgrade-positions tbody tr');
    await expect(positions).toHaveCount(3);
    await positions.nth(1).getByRole('button', { name: 'Remove row' }).click();
    await expect(positions).toHaveCount(2);
    // by keyboard, then back by dragging
    await positions.nth(1).getByRole('button', { name: /Move row/ }).press('ArrowUp');
    await expect(positions.nth(0).locator('input[name$="[label]"]')).toHaveValue(/^Testing/);
    await positions.nth(0).getByRole('button', { name: /Move row/ }).dragTo(positions.nth(1), { targetPosition: { x: 10, y: 30 } });
    await expect(positions.nth(0).locator('input[name$="[label]"]')).toHaveValue('Core upgrade');
    await positions.nth(1).getByRole('button', { name: /Move row/ }).dragTo(positions.nth(0), { targetPosition: { x: 10, y: 2 } });
    await expect(positions.nth(0).locator('input[name$="[label]"]')).toHaveValue(/^Testing/);

    const optional = page.locator('#upgrade-calculation-form .card').filter({ has: page.locator('#upgrade-optional') });
    await optional.getByRole('button', { name: 'Add row' }).click();
    await optional.getByRole('button', { name: 'Add row' }).click();
    const optionalRows = page.locator('#upgrade-optional tbody tr');
    await optionalRows.nth(0).locator('input[name$="[label]"]').fill('Content blocks migration');
    await optionalRows.nth(0).locator('input[name$="[maxHours]"]').fill('8');
    await optionalRows.nth(1).getByRole('button', { name: 'Remove row' }).click();

    // at 1600 px with the sidebar open, no section scrolls sideways, and the row actions
    // line up with "Add row" in the card header
    await page.setViewportSize({ width: 1600, height: 1000 });
    for (const section of ['positions', 'packages', 'optional']) {
        const card = page.locator('#upgrade-calculation-form .card').filter({ has: page.locator(`#upgrade-${section}`) });
        const overflow = await card.locator('.table-responsive').evaluate((body) => body.scrollWidth - body.clientWidth);
        expect(overflow, `sideways scrolling of ${section}`).toBe(0);
        const addRow = await card.getByRole('button', { name: 'Add row' }).boundingBox();
        const remove = await card.getByRole('button', { name: 'Remove row' }).first().boundingBox();
        expect(Math.round(remove!.x + remove!.width), `remove button of ${section}`).toBe(Math.round(addRow!.x + addRow!.width));
    }

    await page.getByLabel('Hourly rate (EUR)').fill('100');
    await page.getByLabel('Project management (%)').fill('10');
    await page.getByLabel('Your name or initials').fill('E2E');
    // "Final version?" sits right of the save button, on its line
    const final = await page.getByLabel('Final version?').boundingBox();
    const save = await page.getByRole('button', { name: 'Save as new version' }).boundingBox();
    expect(Math.abs((final!.y + final!.height / 2) - (save!.y + save!.height / 2))).toBeLessThan(4);
    expect(final!.x).toBeGreaterThan(save!.x + save!.width);
    const hint = await page.getByText('Marks the version sent to the customer').boundingBox();
    expect(hint!.x, 'hint right of the checkbox').toBeGreaterThan(final!.x + final!.width);
    expect(Math.abs((hint!.y + hint!.height / 2) - (save!.y + save!.height / 2)), 'hint on the line of the save button').toBeLessThan(6);

    await page.getByRole('button', { name: 'Save as new version' }).click();

    await expect(page.locator('#upgrade-positions tbody tr')).toHaveCount(2);
    await expect(page.locator('#upgrade-positions tbody tr').first()).toContainText('Testing');
    await expect(page.locator('#upgrade-optional')).toContainText('Content blocks migration');
    await expect(page.locator('#upgradeTotals')).toContainText('Optional, incl. project management');
    await expect(page.locator('#upgrade-packages')).toContainText('news');
});
