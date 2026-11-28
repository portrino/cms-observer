import { expect, test } from '../fixtures';

// relies on the TER versions of news in the dev DB
test('a refresh shows the previous notes of a changed row and needs it checked', async ({ page, createProject }) => {
    await page.setViewportSize({ width: 1600, height: 1000 });
    const project = await createProject('e2e-upgrade-refresh');
    const report = (version: string) => page.request.post('/api/v1/sites/report', {
        headers: { Authorization: `Bearer ${project.token}` },
        data: {
            coreVersion: '13.4.20', phpVersion: '8.3.14', dbVersion: '10.11.19-MariaDB', domains: ['e2e-upgrade-refresh.example.test'],
            packages: [{ name: 'news', type: 'extension', title: 'News', version, active: true }],
        },
    });
    expect((await report('12.3.1')).status()).toBe(202);

    await page.goto(`${project.url}/calculations/new?target=14`);
    // the rate comes from the settings of the dev DB
    await page.getByLabel('Your name or initials').fill('JD');
    await page.getByRole('button', { name: 'Save as new version' }).click();
    await expect(page).toHaveURL(/calculations\/\d+/);
    const calculationUrl = page.url().replace(/\?.*$/, '');

    // updated in the maintenance since: its row is judged anew
    expect((await report('14.0.0')).status()).toBe(202);
    await page.goto(`${calculationUrl}/refresh`);
    const row = page.locator('#upgrade-packages tr.upgrade-changed-row').filter({ has: page.locator('input[value="georgringer/news"]') });
    await expect(row).toHaveCount(1);
    const background = () => row.locator('td').nth(1).evaluate((cell) => getComputedStyle(cell).backgroundColor);
    const highlighted = await background();

    await row.getByRole('button', { name: 'Edit notes' }).click();
    const dialog = page.getByRole('dialog', { name: 'Notes' });
    const previous = dialog.locator('details', { hasText: 'Notes of the previous version' });
    await expect(previous).toBeVisible();
    await previous.locator('summary').click();
    // 12.3.1 → 14.x crossed a major with breaking changes, 14.0.0 has a smaller update left
    await expect(previous).toContainText('Attention! Big version jump');
    await previous.getByRole('button', { name: 'Add to the editor' }).click();
    const editor = dialog.locator('.CodeMirror');
    await expect(editor).toContainText('According to the changelog no special upgrade steps needed');
    await expect(editor).toContainText('Attention! Big version jump', { useInnerText: true });
    await dialog.getByRole('button', { name: 'Cancel' }).click();

    // not saved until the changed row is checked
    await page.getByLabel('Your name or initials').fill('PM');
    await page.getByRole('button', { name: 'Save as new version' }).click();
    await expect(row.locator('.invalid-feedback')).toContainText('Check the changed row before saving.');

    await row.getByRole('switch', { name: 'Checked' }).check();
    expect(await background(), 'no longer highlighted once checked').not.toBe(highlighted);
    await page.getByLabel('Your name or initials').fill('PM');
    await page.getByRole('button', { name: 'Save as new version' }).click();
    await expect(page).toHaveURL(/calculations\/\d+/);
});
