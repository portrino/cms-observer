import { expect, test } from '../fixtures';

test.use({ viewport: { width: 375, height: 812 } });

test('pages fit the phone width', async ({ page }) => {
    for (const path of ['/', '/packages', '/organizations', '/profile']) {
        await page.goto(path);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, path).toBe(0);
    }
});

test('the sidebar is an overlay on phones', async ({ page }) => {
    const body = page.locator('body');
    const nav = page.getByRole('link', { name: 'Packages' });
    const openIcon = page.locator('.sidebar-toggle .sidebar-icon-open');
    await expect(nav).not.toBeInViewport();
    await expect(openIcon).toBeVisible();

    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
    await expect(body).toHaveClass(/sidebar-open/);
    await expect(nav).toBeInViewport();
    await expect(page.locator('.sidebar-toggle .sidebar-icon-close')).toBeVisible();
    await expect(openIcon).toBeHidden();

    // a tap next to it closes it again
    await page.mouse.click(350, 400);
    await expect(body).not.toHaveClass(/sidebar-open/);
});

test('the user menu is in the sidebar overlay on phones', async ({ page }) => {
    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
    await page.getByRole('button', { name: 'User menu' }).click();

    await expect(page.getByRole('link', { name: 'Log out' })).toBeInViewport();
});

test('the filters fold away behind a button on phones', async ({ page }) => {
    const search = page.getByRole('searchbox', { name: 'Search' });
    await expect(search).toBeHidden();

    await page.getByRole('button', { name: 'Filters' }).click();
    await expect(search).toBeVisible();
});

test('the project page fits the phone width', async ({ page }) => {
    // a reported project has version badges, the widest content of the page; the demo seed's
    await page.goto('/?q=Bergblick');
    await page.locator('#projects-results tbody a').first().click();
    await page.waitForURL(/\/projects\/\d+$/);

    const overflowing = await page.evaluate(() => [...document.querySelectorAll('.card-body:not(.p-0), .accordion-button')]
        .filter((element) => element.scrollWidth > element.clientWidth)
        .map((element) => element.textContent?.trim().slice(0, 40)));
    expect(overflowing).toEqual([]);
});
