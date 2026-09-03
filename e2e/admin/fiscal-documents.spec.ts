import { expect, test } from '@playwright/test';

test('an admin completes the fiscal correction, related note, annulment and replacement flow', async ({ page }) => {
    await page.goto('/admin/pedidos?q=Pedido%20fiscal%20E2E');

    await expect(page.getByText('1 pedido')).toBeVisible();
    await page.getByRole('link', { name: /Ver pedido/ }).click();
    await expect(page.getByRole('heading', { name: 'Documentos fiscales' })).toBeVisible();

    let document = fiscalDocument(page, 'B001-90000002');
    await expect(document).toContainText('Historial administrativo');

    for (const viewport of [
        { width: 1920, height: 1080 },
        { width: 1366, height: 768 },
        { width: 390, height: 844 },
    ]) {
        await page.setViewportSize(viewport);
        await expect(document.getByRole('button', { name: 'Enviar comprobante' })).toBeVisible();
        await expect.poll(() => page.evaluate(() => globalThis.document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    }

    await document.getByRole('button', { name: 'Corregir comprobante' }).click();
    const mobileCorrectionModal = page.getByRole('dialog', { name: 'Corregir comprobante' });
    await expect(mobileCorrectionModal).toBeVisible();
    await expect.poll(() => page.evaluate(() => globalThis.document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await mobileCorrectionModal.getByRole('button', { name: 'Cerrar' }).click();

    await page.setViewportSize({ width: 1366, height: 768 });
    await document.getByRole('button', { name: 'Enviar comprobante' }).click();
    await expect(page.getByText('El comprobante fue programado para envio al correo fiscal del pedido.')).toBeVisible();

    document = fiscalDocument(page, 'B001-90000002');
    await expect(document).toContainText('Ultimo envio por correo: Enviado');

    await document.getByRole('button', { name: 'Corregir comprobante' }).click();
    const correctionModal = page.getByRole('dialog', { name: 'Corregir comprobante' });
    await expect(correctionModal).toBeVisible();
    await expect(correctionModal.getByRole('radio')).toHaveCount(0);
    await expect(correctionModal.locator('input[name="pdf"]')).not.toHaveAttribute('required', '');
    await expect(correctionModal.locator('input[name="series"], input[name="correlative"], input[name="issued_at"], input[name="pdf"], textarea[name="reason"]')).toHaveCount(5);
    await expect(correctionModal.locator('label[for^="fiscal-correction-reason-"] .text-danger')).toHaveText('*');
    const fieldOrder = await correctionModal.locator('input[name="series"], input[name="correlative"], input[name="issued_at"], input[name="pdf"], textarea[name="reason"]').evaluateAll((fields) => fields.map((field) => field.getAttribute('name')));
    expect(fieldOrder).toEqual(['series', 'correlative', 'issued_at', 'pdf', 'reason']);
    await correctionModal.locator('textarea[name="reason"]').fill('Sin cambios reales.');
    await correctionModal.getByRole('button', { name: 'Guardar correccion' }).click();
    await expect(page.getByText('La correccion no contiene cambios.')).toBeVisible();
    await expect(correctionModal).toBeVisible();

    await correctionModal.locator('input[name="pdf"]').setInputFiles(pdfUpload('boleta-corregida.pdf'));
    await correctionModal.locator('input[name="series"]').fill('B001');
    await correctionModal.locator('input[name="correlative"]').fill('90000003');
    await correctionModal.locator('input[name="issued_at"]').fill('2026-08-02');
    await correctionModal.locator('textarea[name="reason"]').fill('Correccion E2E integral.');
    await correctionModal.getByRole('button', { name: 'Guardar correccion' }).click();
    await expect(page.getByText('Comprobante corregido con historial privado.')).toBeVisible();

    document = fiscalDocument(page, 'B001-90000003');
    const audit = document.locator('details').filter({ hasText: 'Historial administrativo' });
    await audit.locator('summary').click();
    await expect(audit).toContainText('Correccion E2E integral.');

    await document.getByRole('button', { name: 'Registrar nota' }).click();
    const relatedNote = page.getByRole('dialog', { name: 'Registrar nota relacionada' });
    await relatedNote.locator('select[name="type"]').selectOption('credit_note');
    await relatedNote.locator('input[name="series"]').fill('BC01');
    await relatedNote.locator('input[name="correlative"]').fill('90000001');
    await relatedNote.locator('input[name="issued_at"]').fill('2026-08-02');
    await relatedNote.locator('input[name="pdf"]').setInputFiles(pdfUpload('nota-credito.pdf'));
    await relatedNote.getByRole('button', { name: 'Registrar nota' }).click();
    await expect(page.getByText('Nota fiscal relacionada registrada.')).toBeVisible();
    await expect(fiscalDocument(page, 'BC01-90000001')).toBeVisible();

    document = fiscalDocument(page, 'B001-90000003');
    await document.getByRole('button', { name: 'Registrar anulacion' }).click();
    const annulment = page.getByRole('dialog', { name: 'Registrar anulacion' });
    await annulment.locator('textarea[name="reason"]').fill('Anulacion E2E registrada externamente.');
    await annulment.getByRole('button', { name: 'Registrar anulacion' }).click();
    await expect(page.getByText('Anulacion fiscal registrada.')).toBeVisible();

    document = fiscalDocument(page, 'B001-90000003');
    await expect(document).toContainText('Anulado');
    await document.getByRole('button', { name: 'Registrar reemplazo' }).click();
    const replacement = page.getByRole('dialog', { name: 'Registrar reemplazo emitido externamente' });
    await replacement.locator('input[name="series"]').fill('B001');
    await replacement.locator('input[name="correlative"]').fill('90000004');
    await replacement.locator('input[name="issued_at"]').fill('2026-08-02');
    await replacement.locator('input[name="pdf"]').setInputFiles(pdfUpload('boleta-reemplazo.pdf'));
    await replacement.getByRole('button', { name: 'Registrar reemplazo' }).click();
    await expect(page.getByText('Comprobante de reemplazo registrado.')).toBeVisible();

    await expect(fiscalDocument(page, 'B001-90000004')).toContainText('Relacionado con: B001-90000003');
    await expect(fiscalDocument(page, 'B001-90000003').getByRole('button', { name: 'Registrar reemplazo' })).toHaveCount(0);
});

function fiscalDocument(page: import('@playwright/test').Page, reference: string) {
    return page.locator('.admin-order-record-row').filter({
        has: page.getByText(reference, { exact: true }),
    });
}

function pdfUpload(name: string) {
    return {
        name,
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n'),
    };
}
