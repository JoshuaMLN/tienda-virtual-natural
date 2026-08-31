import { expect, test as setup } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const authDirectory = resolve('playwright/.auth');
const customerState = resolve(authDirectory, 'customer.json');
const adminState = resolve(authDirectory, 'admin.json');
const customerEmail = requiredEnv('E2E_CUSTOMER_EMAIL');
const adminEmail = requiredEnv('E2E_ADMIN_EMAIL');

setup.beforeAll(() => {
    mkdirSync(authDirectory, { recursive: true });
});

setup('customer logs in through the UI and saves storage state', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#login-email').fill(customerEmail);
    await page.locator('#login-password').fill(requiredEnv('E2E_CUSTOMER_PASSWORD'));
    await page.locator('form[action$="/login"] button[type="submit"]').click();

    await page.waitForURL('**/mi-cuenta/perfil');
    await expect(page.getByRole('heading', { name: 'Mi perfil' })).toBeVisible();
    await page.context().storageState({ path: customerState });
});

setup('admin logs in through the UI and saves storage state', async ({ page }) => {
    await page.goto('/admin/login');
    await page.locator('#admin-login-email').fill(adminEmail);
    await page.locator('#admin-login-password').fill(requiredEnv('E2E_ADMIN_PASSWORD'));
    await page.locator('form[action$="/admin/login"] button[type="submit"]').click();

    await page.waitForURL('**/admin');
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    await page.context().storageState({ path: adminState });
});

function requiredEnv(name: string): string {
    const value = process.env[name]?.trim();

    if (!value) {
        throw new Error(`${name} debe estar definido para ejecutar Playwright.`);
    }

    return value;
}
