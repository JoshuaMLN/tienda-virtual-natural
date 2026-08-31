import { expect, test } from '@playwright/test';

test('the public home page responds without a fatal error', async ({ page }, testInfo) => {
    const failedRequests: string[] = [];
    page.on('requestfailed', (request) => {
        failedRequests.push(`${request.method()} ${request.url()} — ${request.failure()?.errorText ?? 'unknown error'}`);
    });

    const response = await page.goto('/');

    expect(response, 'La pagina de inicio debe responder.').not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
    await expect(page.getByRole('heading', { name: /vive bien/i })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Whoops');

    await testInfo.attach('failed-requests.txt', {
        body: failedRequests.join('\n') || 'No hubo solicitudes fallidas.',
        contentType: 'text/plain',
    });
});
