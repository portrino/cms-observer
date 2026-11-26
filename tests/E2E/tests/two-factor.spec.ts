import { expect, Page, test } from '@playwright/test';
import { totp } from '../totp';

// note: toHaveURL, not waitForURL - Turbo-driven form redirects change the URL without a full page load

// own user and no auto-login fixture: a run aborted halfway must not lock the shared e2e user out
const user = { email: 'e2e-2fa@example.test', password: 'e2e-password' };

// returns the logo's box on the login page, the 2FA page must show it the same way
async function logIn(page: Page): Promise<{ x: number; y: number; width: number; height: number } | null> {
    await page.goto('/logout');
    await page.goto('/login');
    const logo = await page.getByAltText('CMS Observer').boundingBox();
    await page.getByLabel('Email').fill(user.email);
    await page.getByLabel('Password').fill(user.password);
    await page.getByRole('button', { name: 'Log in' }).click();

    return logo;
}

async function submitCode(page: Page, code: string): Promise<void> {
    await expect(page).toHaveURL('/2fa');
    await page.getByLabel('Authentication code').fill(code);
    await page.getByRole('button', { name: 'Verify' }).click();
}

test('set up two-factor authentication and log in with a code', async ({ page }) => {
    await logIn(page);
    await expect(page).toHaveURL('/');

    await page.goto('/profile/2fa/enable');
    const secret = (await page.locator('code.user-select-all').textContent())!.replace(/\s/g, '');
    await expect(page.getByAltText('QR code for your authenticator app')).toBeVisible();

    await page.getByLabel('Code from your authenticator app').fill(totp(secret));
    await page.getByRole('button', { name: 'Enable two-factor authentication' }).click();
    await expect(page).toHaveURL('/profile/2fa/backup-codes');
    const backupCodes = (await page.locator('#backup-codes li').allTextContents()).map(code => code.trim());

    try {
        const loginLogo = await logIn(page);
        await expect(page).toHaveURL('/2fa');
        // same size and place, so switching from the login to the 2FA page doesn't jump
        expect(await page.getByAltText('CMS Observer').boundingBox()).toEqual(loginLogo);
        await submitCode(page, totp(secret));
        await expect(page).toHaveURL('/');
        await expect(page.getByRole('heading', { name: 'Projects' })).toBeVisible();
    } finally {
        // leave the user without 2FA for the next run, independent of where the test stopped
        // (if even this fails: ddev console app:user:reset-2fa e2e-2fa@example.test)
        await logIn(page);
        await submitCode(page, backupCodes[0]);
        await expect(page).toHaveURL('/'); // let the code submit finish before navigating away
        await page.goto('/profile/2fa/disable');
        await page.getByLabel('Current password').fill(user.password);
        await page.getByLabel('Code from your authenticator app or a backup code').fill(backupCodes[1]);
        await page.getByRole('button', { name: 'Disable two-factor authentication' }).click();
        await expect(page).toHaveURL('/profile');
    }
});
