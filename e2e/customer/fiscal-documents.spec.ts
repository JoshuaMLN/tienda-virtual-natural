import { expect, test } from '@playwright/test';

test('a customer sees only the current fiscal document and can download its private PDF', async ({ page }) => {
    await page.goto('/mi-cuenta/pedidos');

    const orderLinks = await page.locator('a[href*="/mi-cuenta/pedidos/"]').evaluateAll((links) =>
        links.map((link) => link.getAttribute('href')).filter((href): href is string => href !== null),
    );
    let fiscalOrderFound = false;

    for (const orderLink of orderLinks) {
        await page.goto(orderLink);

        if (await page.getByText('B001-90000002', { exact: true }).isVisible()) {
            fiscalOrderFound = true;
            break;
        }
    }

    expect(fiscalOrderFound).toBe(true);
    const documents = page.getByRole('heading', { name: 'Documentos fiscales' }).locator('..');
    await expect(documents).toContainText('B001-90000002');
    await expect(documents).not.toContainText('Historial administrativo');
    await expect(documents).not.toContainText('Correccion inicial para QA E2E.');

    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: /Descargar Boleta B001-90000002 en PDF/ }).click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toBe('boleta-B001-90000002.pdf');
});
