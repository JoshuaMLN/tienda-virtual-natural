import { expect, test } from '@playwright/test';

test('a customer can open the profile and is denied the admin dashboard', async ({ page }) => {
    await page.goto('/mi-cuenta/perfil');

    await expect(page).toHaveURL(/\/mi-cuenta\/perfil$/);
    await expect(page.getByRole('heading', { name: 'Mi perfil' })).toBeVisible();

    const response = await page.goto('/admin');

    expect(response, 'El dashboard admin debe responder al acceso no autorizado.').not.toBeNull();
    expect(response!.status()).toBe(403);
});
