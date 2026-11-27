import { expect, test } from '../fixtures';

// relies on the TYPO3 core releases in the dev DB: TYPO3 14 releases exist
test('the core section shows the releases of newer majors only behind its switch, remembered', async ({ page, createProject }) => {
    await page.setViewportSize({ width: 1600, height: 900 });
    const project = await createProject('e2e-newer-core', 'TYPO3');
    const response = await page.request.post('/api/v1/sites/report', {
        headers: { Authorization: `Bearer ${project.token}` },
        data: { coreVersion: '13.4.20', phpVersion: '8.3.14', dbVersion: '10.11.9-MariaDB', domains: ['e2e-newer-core.example.test'] },
    });
    expect(response.status()).toBe(202);
    await page.evaluate(() => localStorage.removeItem('cms-observer:newer-core:core'));

    await page.goto(project.url);
    const section = page.locator('[data-newer-core-key-value="core"]');
    const toggle = section.getByRole('switch', { name: 'Show newer cores' });
    await section.locator('.accordion-button').click();
    const newerMajor = section.locator('tr.row-newer-core', { hasText: /^\s*14\./ }).first();

    await expect(toggle).not.toBeChecked();
    await expect(newerMajor).toBeHidden();

    // right of the heading, on its line
    const heading = await section.getByRole('heading', { name: 'Core' }).boundingBox();
    const switchBox = await toggle.boundingBox();
    expect(switchBox!.x).toBeGreaterThan(heading!.x + heading!.width);
    expect(Math.abs((switchBox!.y + switchBox!.height / 2) - (heading!.y + heading!.height / 2))).toBeLessThan(6);

    await toggle.check();
    await expect(newerMajor).toBeVisible();
    // newest first across the majors: the newer major above the own one
    await expect(section.locator('tbody tr').first()).toContainText(/^\s*1[4-9]\./);
    await expect(section.locator('tbody tr').last()).toContainText(/^\s*13\./);
    // the packages section has a switch of its own
    await expect(page.locator('[data-newer-core-key-value="packages"]').getByRole('switch')).not.toBeChecked();

    await page.reload();
    await expect(section.getByRole('switch', { name: 'Show newer cores' })).toBeChecked();
    await section.locator('.accordion-button').click();
    await expect(newerMajor).toBeVisible();

    await section.getByRole('switch', { name: 'Show newer cores' }).uncheck();
    await expect(newerMajor).toBeHidden();
});
