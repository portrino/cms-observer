import { expect, test } from '../fixtures';

// relies on the WordPress.org sync in the dev DB: wordpress-seo has a description of several thousand characters
test('package details cut a long description and show every source link with an icon', async ({ page, createProject }) => {
    await page.setViewportSize({ width: 1600, height: 900 });
    const project = await createProject('e2e-package-details', 'WordPress');
    const response = await page.request.post('/api/v1/sites/report', {
        headers: { Authorization: `Bearer ${project.token}` },
        data: {
            coreVersion: '6.8.1', phpVersion: '8.3.14', dbVersion: '10.11.9-MariaDB', domains: ['e2e-package-details.example.test'],
            packages: [{ name: 'wordpress-seo', title: 'Yoast SEO', version: '25.0', active: true, composerName: 'wpackagist-plugin/wordpress-seo', type: 'plugin' }],
        },
    });
    expect(response.status()).toBe(202);

    await page.goto(project.url);
    await page.getByRole('button', { name: /wordpress-seo/ }).click();
    const details = page.locator('.accordion-collapse.show');

    const description = details.locator('.package-description');
    await expect(description).toBeVisible();
    const clamp = await description.evaluate((el) => ({
        height: el.clientHeight,
        fullHeight: el.scrollHeight,
        lineHeight: parseFloat(getComputedStyle(el).lineHeight),
    }));
    expect(clamp.height).toBeLessThanOrEqual(3 * clamp.lineHeight + 1);
    expect(clamp.fullHeight).toBeGreaterThan(clamp.height);

    await expect(details.locator('.package-meta dt')).toHaveText(['Composer', 'Links']);
    await expect(details.locator('.package-meta dd code')).toHaveText('wpackagist-plugin/wordpress-seo');
    const links = details.locator('.package-links a');
    await expect(links).toHaveText(['WordPress.org', 'Repository']);
    for (const link of await links.all()) {
        await expect(link.locator('svg')).toHaveCount(1);
    }
});

// relies on the Wordfence sync in the dev DB: wordpress-seo 1.0 is affected by several known vulnerabilities
test('package details show a known vulnerability as an alert above the version table', async ({ page, createProject }) => {
    await page.setViewportSize({ width: 1600, height: 900 });
    const project = await createProject('e2e-package-vulnerability', 'WordPress');
    const response = await page.request.post('/api/v1/sites/report', {
        headers: { Authorization: `Bearer ${project.token}` },
        data: {
            coreVersion: '6.8.1', phpVersion: '8.3.14', dbVersion: '10.11.9-MariaDB', domains: ['e2e-package-vulnerability.example.test'],
            packages: [{ name: 'wordpress-seo', title: 'Yoast SEO', version: '1.0', active: true, type: 'plugin' }],
        },
    });
    expect(response.status()).toBe(202);

    await page.goto(project.url);
    await page.getByRole('button', { name: /wordpress-seo/ }).click();
    const details = page.locator('.accordion-collapse.show');

    const alert = details.locator('.alert-danger');
    await expect(alert).toBeVisible();
    await expect(alert.locator('a.alert-link svg')).toHaveCount(1);
    await expect(alert).toContainText('CVSS');
    const alertBottom = (await alert.boundingBox())!.y + (await alert.boundingBox())!.height;
    expect(alertBottom).toBeLessThanOrEqual((await details.locator('table').boundingBox())!.y);
});

