import { expect, test } from '../fixtures';

// TYPO3 and WordPress versions differ in length; the badges after them must still start in one line
test('core badges line up across rows of different CMS', async ({ page, createProject }) => {
    await page.setViewportSize({ width: 1600, height: 900 });
    const prefix = `e2e-badges-${Date.now()}`;
    const projects = [
        { ...(await createProject(`${prefix}-t3`, 'TYPO3')), coreVersion: '13.4.21', host: `${prefix}-t3.example.test` },
        { ...(await createProject(`${prefix}-wp`, 'WordPress')), coreVersion: '6.5.2', host: `${prefix}-wp.example.test` },
    ];
    for (const project of projects) {
        const response = await page.request.post('/api/v1/sites/report', {
            headers: { Authorization: `Bearer ${project.token}` },
            data: { coreVersion: project.coreVersion, phpVersion: '8.3.14', dbVersion: '10.11.9-MariaDB', domains: [project.host] },
        });
        expect(response.status()).toBe(202);
    }

    await page.goto(`/?q=${prefix}`);
    const badgeStarts = [];
    for (const project of projects) {
        const coreCell = page.getByRole('row').filter({ hasText: project.name }).locator('td', { has: page.locator('small.fw-bold', { hasText: project.coreVersion }) });
        await expect(coreCell).toContainText(project.coreVersion);
        badgeStarts.push((await coreCell.locator('.badge').first().boundingBox())?.x ?? NaN);
    }

    expect(Math.abs(badgeStarts[0] - badgeStarts[1])).toBeLessThan(1);
});
