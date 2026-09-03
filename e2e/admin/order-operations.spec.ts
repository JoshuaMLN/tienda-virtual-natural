import { expect, test } from '@playwright/test';

test('an admin completes a home-delivery order through preparation, shipment and delivery', async ({ page, browser }) => {
    await openHomeOrder(page, '3 unidades');
    const code = orderCodeFrom(page);
    const customerContext = await browser.newContext({
        storageState: 'playwright/.auth/customer.json',
        viewport: { width: 1366, height: 768 },
    });
    const customerPage = await customerContext.newPage();

    await customerPage.goto(`/mi-cuenta/pedidos/${code}`);
    const deliveryNotice = customerPage.locator('.customer-order-fulfillment-notice');
    await expect(deliveryNotice.getByRole('heading', { name: 'Entrega estimada' })).toBeVisible();
    const estimateMessage = await deliveryNotice.locator('p').textContent();

    await performAction(page, 'Iniciar preparacion', 'La preparacion del pedido fue iniciada.');
    await expect(page.getByLabel('Estados del pedido').getByText('Preparando', { exact: true })).toBeVisible();
    await customerPage.reload();
    await expect(deliveryNotice.locator('p')).toHaveText(estimateMessage ?? '');

    await performAction(page, 'Marcar como enviado', 'El pedido fue marcado como enviado.');
    await expect(page.getByLabel('Estados del pedido').getByText('Enviado', { exact: true })).toBeVisible();
    await customerPage.reload();
    await expect(deliveryNotice.getByRole('heading', { name: 'Pedido en camino' })).toBeVisible();
    await expect(deliveryNotice.locator('p')).toHaveText(estimateMessage ?? '');

    await page.getByRole('button', { name: 'Registrar resultado de entrega' }).click();
    const deliveryModal = page.getByRole('dialog', { name: 'Registrar resultado de entrega' });
    await deliveryModal.locator('select[name="result"]').selectOption('delivered');
    await deliveryModal.locator('input[name="responsible_name"]').fill('Transportista E2E');
    await deliveryModal.getByRole('button', { name: 'Registrar resultado' }).click();

    await expect(page.getByText('La entrega fue confirmada y el pedido quedo completado.')).toBeVisible();
    await expect(page.getByLabel('Estados del pedido').getByText('Entregado', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Estados del pedido').getByText('Completado', { exact: true })).toBeVisible();
    await customerContext.close();
});

test('an admin records non-consuming and customer-attributable delivery incidents until reshipment payment is required', async ({ page }) => {
    await openHomeOrder(page, '1 unidad');

    await performAction(page, 'Iniciar preparacion', 'La preparacion del pedido fue iniciada.');
    await performAction(page, 'Marcar como enviado', 'El pedido fue marcado como enviado.');

    await recordIncident(page, 'store', 'Incidencia de tienda E2E.');
    await expect(page.getByText('No consume intento')).toBeVisible();
    await expect(page.getByText('0 de 3', { exact: true })).toBeVisible();

    for (let attempt = 1; attempt <= 3; attempt += 1) {
        await recordIncident(page, 'customer', `Cliente ausente en visita E2E ${attempt}.`);
    }

    await expect(page.getByLabel('Estados del pedido').getByText('Pendiente de nuevo pago de envio', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Registrar resultado de entrega' })).toHaveCount(0);
    await expect(page.getByText('No se admiten nuevas visitas hasta confirmar otro pago de envio.')).toBeVisible();
});

test('an admin completes the pickup flow through readiness and customer collection', async ({ page, browser }) => {
    await page.goto('/admin/pedidos?modalidad=pickup');

    const pickupRow = page.getByRole('row').filter({ hasText: 'Cliente E2E' }).filter({ hasText: '1 producto' });
    await expect(pickupRow).toHaveCount(1);
    await pickupRow.getByRole('link', { name: /Ver pedido/ }).click();
    const code = orderCodeFrom(page);
    const customerContext = await browser.newContext({
        storageState: 'playwright/.auth/customer.json',
        viewport: { width: 390, height: 844 },
    });
    const customerPage = await customerContext.newPage();
    await customerPage.goto(`/mi-cuenta/pedidos/${code}`);
    const pickupNotice = customerPage.locator('.customer-order-fulfillment-notice');
    await expect(pickupNotice.getByRole('heading', { name: 'Preparacion para recojo' })).toBeVisible();
    const preparationEstimate = await pickupNotice.locator('p').textContent();

    await performAction(page, 'Iniciar preparacion', 'La preparacion del pedido fue iniciada.');
    await customerPage.reload();
    await expect(pickupNotice.locator('p')).toHaveText(preparationEstimate ?? '');
    await performAction(page, 'Marcar listo para recojo', 'El pedido fue marcado como listo para recojo.');
    await expect(page.getByLabel('Estados del pedido').getByText('Listo para recoger', { exact: true })).toBeVisible();
    await customerPage.reload();
    await expect(pickupNotice.getByRole('heading', { name: 'Tu pedido esta listo para recoger' })).toBeVisible();
    await expect(pickupNotice.locator('p')).not.toHaveText(preparationEstimate ?? '');

    await performAction(page, 'Confirmar recojo', 'El recojo fue confirmado y el pedido quedo completado.');
    await expect(page.getByLabel('Estados del pedido').getByText('Recogido', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Estados del pedido').getByText('Completado', { exact: true })).toBeVisible();
    await customerContext.close();
});

async function openHomeOrder(page: import('@playwright/test').Page, quantity: string) {
    await page.goto('/admin/pedidos?modalidad=home_delivery&estado_pago=paid');

    const row = page.getByRole('row').filter({ hasText: quantity });
    await expect(row).toHaveCount(1);
    await row.getByRole('link', { name: /Ver pedido/ }).click();
}

async function performAction(page: import('@playwright/test').Page, action: string, successMessage: string) {
    await page.getByRole('button', { name: action }).click();
    const modal = page.getByRole('dialog');
    await expect(modal).toBeVisible();
    await modal.getByRole('button', { name: action }).click();
    await expect(page.getByText(successMessage)).toBeVisible();
}

async function recordIncident(page: import('@playwright/test').Page, attribution: string, reason: string) {
    await page.getByRole('button', { name: 'Registrar resultado de entrega' }).click();
    const modal = page.getByRole('dialog', { name: 'Registrar resultado de entrega' });
    await modal.locator('select[name="result"]').selectOption('incident');
    await modal.locator('select[name="attribution"]').selectOption(attribution);
    await modal.locator('input[name="responsible_name"]').fill('Transportista E2E');
    await modal.locator('textarea[name="attempt_reason"]').fill(reason);
    await modal.getByRole('button', { name: 'Registrar resultado' }).click();
    await expect(page.getByText('La incidencia de entrega fue registrada.')).toBeVisible();
}

function orderCodeFrom(page: import('@playwright/test').Page) {
    return new URL(page.url()).pathname.split('/').at(-1) ?? '';
}
