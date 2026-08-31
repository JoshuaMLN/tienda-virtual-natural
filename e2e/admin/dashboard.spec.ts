import { expect, test } from '@playwright/test';

test('an admin can open the dashboard', async ({ page }) => {
    await page.goto('/admin');

    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
});
