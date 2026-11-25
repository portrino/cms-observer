import { expect, test } from '../fixtures';

test('sidebar collapse survives a page load', async ({ page }) => {
    const body = page.locator('body');
    const closeIcon = page.locator('.sidebar-toggle .sidebar-icon-close');
    const openIcon = page.locator('.sidebar-toggle .sidebar-icon-open');
    await expect(body).not.toHaveClass(/sidebar-collapsed/);
    await expect(closeIcon).toBeVisible();
    await expect(openIcon).toBeHidden();

    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
    await expect(body).toHaveClass(/sidebar-collapsed/);
    // the icon shows what the next click does
    await expect(openIcon).toBeVisible();
    await expect(closeIcon).toBeHidden();

    await page.reload();
    await expect(body).toHaveClass(/sidebar-collapsed/);
    await expect(openIcon).toBeVisible();
});

test('the user menu sits at the bottom of the sidebar, also when collapsed', async ({ page }) => {
    const profile = page.getByRole('link', { name: 'Profile' });

    await page.getByRole('button', { name: 'User menu' }).click();
    await expect(profile).toBeVisible();
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
    await page.getByRole('button', { name: 'User menu' }).click();
    // opens beyond the narrow sidebar instead of being cut off by it
    await expect(profile).toBeInViewport({ ratio: 1 });
    await profile.click();
    await page.waitForURL('/profile');

    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
});

test('the brand opens the system information, also when collapsed', async ({ page }) => {
    const brand = page.getByRole('button', { name: 'System information' });
    const menu = page.locator('.system-info');

    await brand.click();
    await expect(menu.getByText('Application context')).toBeVisible();
    await expect(menu).toBeInViewport({ ratio: 1 });
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
    await brand.click();
    // opens beyond the narrow sidebar instead of being cut off by it
    await expect(menu).toBeInViewport({ ratio: 1 });
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
});

test('the filters stay in view while scrolling', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 400 });
    // the longest list in the dev DB, so there is something to scroll
    await page.goto('/packages');
    const filters = page.locator('.page-filters');
    const lineOpacity = () => filters.evaluate((el) => getComputedStyle(el, '::after').opacity);
    // the line below them only shows once they stick
    expect(await lineOpacity()).toBe('0');
    await page.mouse.wheel(0, 2000);

    await expect.poll(lineOpacity).toBe('1');
    await expect(page.getByRole('searchbox', { name: 'Search' })).toBeInViewport();
    await expect(page.getByRole('heading', { name: 'Packages across all projects' })).toBeInViewport();
});
