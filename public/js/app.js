function setCategoryMenuOpen(toggle, isOpen) {
    const panelId = toggle?.getAttribute('aria-controls');
    const panel = panelId ? document.getElementById(panelId) : null;
    if (!toggle || !panel) return;

    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    panel.hidden = !isOpen;
}

function closeCategoryMenus() {
    document.querySelectorAll('[data-category-menu-toggle]').forEach(function (toggle) {
        setCategoryMenuOpen(toggle, false);
    });
}

document.addEventListener('click', function (event) {
    const adminSidebar = document.querySelector('.admin-sidebar');
    const adminSidebarBackdrop = document.querySelector('.admin-sidebar-backdrop');

    function setAdminSidebarOpen(isOpen) {
        adminSidebar?.classList.toggle('show', isOpen);
        adminSidebarBackdrop?.classList.toggle('show', isOpen);
        document.body.classList.toggle('admin-sidebar-open', isOpen);
    }

    const quantityButton = event.target.closest('[data-quantity]');
    if (quantityButton) {
        const control = quantityButton.closest('.quantity-control');
        const input = control ? control.querySelector('input') : null;
        if (!input) return;

        const step = quantityButton.dataset.quantity === 'plus' ? 1 : -1;
        const min = Number(input.min || 1);
        const max = input.max ? Number(input.max) : Infinity;
        const currentValue = Number(input.value || min);
        const nextValue = Math.max(min, currentValue + step);

        if (nextValue > max) {
            input.dataset.quantityLimitExceeded = '1';
            return;
        }

        delete input.dataset.quantityLimitExceeded;
        input.value = nextValue;
    }

    const passwordToggle = event.target.closest('[data-password-toggle]');
    if (passwordToggle) {
        const input = document.getElementById(passwordToggle.dataset.passwordToggle);
        if (!input) return;

        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        passwordToggle.setAttribute('aria-label', showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena');
        passwordToggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');

        const icon = passwordToggle.querySelector('i');
        icon?.classList.toggle('bi-eye', !showPassword);
        icon?.classList.toggle('bi-eye-slash', showPassword);
    }

    const sidebarToggle = event.target.closest('[data-admin-sidebar]');
    if (sidebarToggle) {
        setAdminSidebarOpen(!adminSidebar?.classList.contains('show'));
    }

    const sidebarClose = event.target.closest('[data-admin-sidebar-close]');
    if (sidebarClose) {
        setAdminSidebarOpen(false);
    }

    const categoryToggle = event.target.closest('[data-category-menu-toggle]');
    if (categoryToggle) {
        const isExpanded = categoryToggle.getAttribute('aria-expanded') === 'true';
        setCategoryMenuOpen(categoryToggle, !isExpanded);
    } else if (!event.target.closest('[data-category-menu]')) {
        closeCategoryMenus();
    }

    const sectionToggle = event.target.closest('[data-section-toggle]');
    if (sectionToggle) {
        const section = sectionToggle.closest('[data-admin-section]');
        const sectionBody = section ? section.querySelector('[data-section-body]') : null;
        if (!section || !sectionBody) return;

        const isExpanded = sectionToggle.getAttribute('aria-expanded') === 'true';
        sectionToggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
        section.classList.toggle('is-collapsed', isExpanded);
        sectionBody.hidden = isExpanded;
    }
});

window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) {
        document.querySelector('.admin-sidebar')?.classList.remove('show');
        document.querySelector('.admin-sidebar-backdrop')?.classList.remove('show');
        document.body.classList.remove('admin-sidebar-open');
        closeCategoryMenus();

        const accountNavigation = document.getElementById('accountNavigationOffcanvas');
        if (accountNavigation?.classList.contains('show') && window.bootstrap) {
            window.bootstrap.Offcanvas.getOrCreateInstance(accountNavigation).hide();
        }
    }
});

document.querySelectorAll('[data-admin-section]').forEach(function (section) {
    const isOpen = section.dataset.sectionOpen !== 'false';
    const toggle = section.querySelector('[data-section-toggle]');
    const sectionBody = section.querySelector('[data-section-body]');
    if (!toggle || !sectionBody) return;

    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    section.classList.toggle('is-collapsed', !isOpen);
    sectionBody.hidden = !isOpen;
});

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (tooltipTrigger) {
    if (window.bootstrap) {
        window.bootstrap.Tooltip.getOrCreateInstance(tooltipTrigger);
    }
});

document.querySelectorAll('[data-tax-breakdown]').forEach(function (output) {
    const form = output.closest('form');
    const priceInput = form?.querySelector('[data-tax-price]');
    const affectationInput = form?.querySelector('[data-tax-affectation]');
    if (!priceInput || !affectationInput) return;

    function decimalToCents(value) {
        const normalized = String(value || '').trim();
        if (!/^\d+(?:\.\d{0,2})?$/.test(normalized)) return null;

        const parts = normalized.split('.');
        return (Number(parts[0]) * 100) + Number((parts[1] || '').padEnd(2, '0'));
    }

    function formatCents(cents) {
        return `S/ ${(cents / 100).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function renderTaxBreakdown() {
        const totalCents = decimalToCents(priceInput.value);
        const selectedOption = affectationInput.options[affectationInput.selectedIndex];
        const rateBasisPoints = Number(selectedOption?.dataset.taxRateBps || 0);

        if (totalCents === null) {
            output.textContent = 'Ingresa el precio final que pagara el cliente.';
            return;
        }

        if (affectationInput.value !== 'taxed') {
            output.textContent = `Valor de venta: ${formatCents(totalCents)} | IGV: ${formatCents(0)}`;
            return;
        }

        const netCents = Math.round((totalCents * 10000) / (10000 + rateBasisPoints));
        const taxCents = totalCents - netCents;
        output.textContent = `Valor de venta: ${formatCents(netCents)} | IGV: ${formatCents(taxCents)}`;
    }

    priceInput.addEventListener('input', renderTaxBreakdown);
    affectationInput.addEventListener('change', renderTaxBreakdown);
    renderTaxBreakdown();
});

document.querySelectorAll('[data-account-mobile-logout]').forEach(function (button) {
    button.addEventListener('click', function () {
        const offcanvasElement = button.closest('.offcanvas');
        const logoutModal = document.getElementById('logoutConfirmationModal');
        if (!offcanvasElement || !logoutModal || !window.bootstrap) return;

        const offcanvas = window.bootstrap.Offcanvas.getOrCreateInstance(offcanvasElement);
        button.disabled = true;
        offcanvasElement.addEventListener('hidden.bs.offcanvas', function () {
            button.disabled = false;
            window.bootstrap.Modal.getOrCreateInstance(logoutModal).show();
        }, { once: true });
        offcanvas.hide();
    });
});

document.querySelectorAll('[data-address-form]').forEach(function (form) {
    const catalogElement = form.querySelector('[data-address-location-catalog]');
    const provinceInput = form.querySelector('[data-address-province]');
    const districtInput = form.querySelector('[data-address-district]');
    const departmentInput = form.querySelector('[data-address-department]');
    const ubigeoInput = form.querySelector('[data-address-ubigeo]');
    if (!catalogElement || !provinceInput || !districtInput || !departmentInput || !ubigeoInput) return;

    let catalog = {};

    try {
        catalog = JSON.parse(catalogElement.textContent || '{}');
    } catch (error) {
        return;
    }

    function populateDistricts(selectedDistrict = '') {
        const province = catalog[provinceInput.value];
        const placeholder = document.createElement('option');

        districtInput.replaceChildren();
        placeholder.value = '';
        placeholder.textContent = province ? 'Selecciona un distrito' : 'Selecciona primero una provincia';
        districtInput.appendChild(placeholder);
        districtInput.disabled = !province;
        departmentInput.value = province?.department || '';

        (province?.districts || []).forEach(function (district) {
            const option = document.createElement('option');
            option.value = district.code;
            option.textContent = district.name + (district.delivery_available === false ? ' - No disponible' : '');
            option.selected = district.code === selectedDistrict;
            option.disabled = district.delivery_available === false;
            option.dataset.deliveryAvailable = district.delivery_available === false ? '0' : '1';
            districtInput.appendChild(option);
        });

        ubigeoInput.value = districtInput.value;
    }

    provinceInput.addEventListener('change', function () {
        populateDistricts();
    });

    districtInput.addEventListener('change', function () {
        ubigeoInput.value = districtInput.value;
    });

    populateDistricts(form.dataset.selectedDistrict || '');
});

document.querySelectorAll('[data-checkout-contact-address-form]').forEach(function (form) {
    const choices = form.querySelectorAll('[data-checkout-address-choice]');
    const deliveryMethods = form.querySelectorAll('[data-checkout-delivery-method]');
    const addressSection = form.querySelector('[data-checkout-address-section]');
    const newAddressPanel = form.querySelector('[data-checkout-new-address]');
    const pickupDetails = form.querySelector('[data-checkout-pickup-details]');
    const submitButton = form.querySelector('[data-checkout-contact-submit]');
    const feedback = form.querySelector('[data-checkout-delivery-feedback]');
    const feedbackIcon = form.querySelector('[data-checkout-delivery-feedback-icon]');
    const feedbackMessage = form.querySelector('[data-checkout-delivery-feedback-message]');
    const whatsappLink = form.querySelector('[data-checkout-delivery-whatsapp]');
    const baseSummaryElement = form.querySelector('[data-checkout-base-summary]');
    const quoteReferenceInput = form.querySelector('[data-checkout-quote-reference]');
    const pickupAddress = form.querySelector('[data-checkout-pickup-address]');
    const pickupWindow = form.querySelector('[data-checkout-pickup-window]');
    const pickupHoldDays = form.querySelector('[data-checkout-pickup-hold-days]');
    const quoteUrl = form.dataset.checkoutQuoteUrl;
    let baseSummary = {};
    let quoteController = null;
    let quoteSequence = 0;
    const hasInitialQuote = Boolean(
        form.querySelector('[data-checkout-delivery-method]:checked')
        && form.dataset.initialQuote === '1'
        && quoteReferenceInput?.value,
    );
    let quoteReady = hasInitialQuote;

    try {
        baseSummary = JSON.parse(baseSummaryElement?.textContent || '{}');
    } catch (error) {
        baseSummary = {};
    }

    function selectedMethod() {
        return form.querySelector('[data-checkout-delivery-method]:checked')?.value || '';
    }

    function setQuoteReference(reference = '') {
        const normalizedReference = typeof reference === 'string' ? reference.trim() : '';
        quoteReady = normalizedReference !== '';

        if (quoteReferenceInput) {
            quoteReferenceInput.value = normalizedReference;
        }
    }

    function setFeedback(style, message) {
        if (!feedback || !feedbackMessage || !feedbackIcon) return;

        const styles = ['success', 'warning', 'secondary', 'danger'];
        const icons = {
            success: 'bi-check-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            danger: 'bi-x-circle-fill',
            secondary: 'bi-info-circle-fill',
        };

        styles.forEach(function (item) {
            feedback.classList.toggle('alert-' + item, item === style);
        });
        feedbackIcon.innerHTML = '<i class="bi ' + icons[style] + '" aria-hidden="true"></i>';
        feedbackMessage.textContent = message;
        whatsappLink?.classList.toggle('d-none', style !== 'warning');
    }

    function setSummaryBusy(isBusy) {
        document.querySelectorAll('[data-checkout-summary]').forEach(function (summary) {
            summary.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        });

        if (submitButton) {
            submitButton.disabled = isBusy || !quoteReady;
        }
    }

    function renderSummary(amounts, shippingLabel, note) {
        if (!amounts) return;

        document.querySelectorAll('[data-checkout-overview-total]').forEach(function (total) {
            total.textContent = amounts.formatted_total;
        });

        document.querySelectorAll('[data-checkout-summary]').forEach(function (summary) {
            const fields = {
                '[data-checkout-subtotal]': amounts.formatted_products_subtotal,
                '[data-checkout-shipping]': shippingLabel,
                '[data-checkout-taxable]': amounts.formatted_taxable_value,
                '[data-checkout-exempt]': amounts.formatted_exempt_value,
                '[data-checkout-unaffected]': amounts.formatted_unaffected_value,
                '[data-checkout-tax]': amounts.formatted_tax,
                '[data-checkout-total]': amounts.formatted_total,
            };

            Object.entries(fields).forEach(function ([selector, value]) {
                const element = summary.querySelector(selector);
                if (element && value !== undefined) element.textContent = value;
            });

            summary.querySelector('[data-checkout-taxable-row]')?.classList.toggle('d-none', !(amounts.taxable_value_cents > 0));
            summary.querySelector('[data-checkout-exempt-row]')?.classList.toggle('d-none', !(amounts.exempt_value_cents > 0));
            summary.querySelector('[data-checkout-unaffected-row]')?.classList.toggle('d-none', !(amounts.unaffected_value_cents > 0));

            const summaryNote = summary.querySelector('[data-checkout-summary-note]');
            if (summaryNote) summaryNote.textContent = note;
        });
    }

    function resetSummary(note) {
        renderSummary(baseSummary.amounts, 'Por calcular', note || 'El envio se sumara al elegir la modalidad de entrega.');
    }

    function safeUrl(value, fallback = '#') {
        try {
            const url = new URL(String(value || ''), window.location.origin);

            return ['http:', 'https:'].includes(url.protocol) ? url.href : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function checkoutItemElement(item) {
        const row = document.createElement('article');
        const productUrl = safeUrl(item.url);
        const imageUrl = safeUrl(item.image_url, '');
        const imageLink = document.createElement('a');
        const image = document.createElement('img');
        const content = document.createElement('div');
        const nameLink = document.createElement('a');
        const details = document.createElement('div');
        const total = document.createElement('strong');
        const quantity = Math.max(0, Number(item.quantity || 0));

        row.className = 'checkout-product-row';
        row.dataset.checkoutItem = String(item.product_id || '');

        imageLink.className = 'checkout-product-image';
        imageLink.href = productUrl;
        imageLink.setAttribute('aria-label', 'Ver ' + String(item.name || 'producto'));
        if (imageUrl) image.src = imageUrl;
        image.alt = String(item.name || 'Producto');
        imageLink.appendChild(image);

        content.className = 'checkout-product-content';
        nameLink.className = 'fw-bold';
        nameLink.href = productUrl;
        nameLink.textContent = String(item.name || 'Producto');
        content.appendChild(nameLink);

        if (item.description) {
            const description = document.createElement('p');
            description.className = 'small text-muted mb-1';
            description.textContent = String(item.description);
            content.appendChild(description);
        }

        details.className = 'small text-muted';
        details.appendChild(document.createTextNode(
            quantity + ' x ' + String(item.formatted_unit_price || 'S/ 0.00'),
        ));

        if (item.tax_label) {
            const separator = document.createElement('span');
            separator.className = 'mx-1';
            separator.setAttribute('aria-hidden', 'true');
            separator.textContent = '\u00b7';
            details.appendChild(separator);
            details.appendChild(document.createTextNode(String(item.tax_label)));
        }

        content.appendChild(details);
        total.className = 'checkout-product-total';
        total.textContent = String(item.formatted_total || item.formatted_subtotal || 'S/ 0.00');
        row.append(imageLink, content, total);

        return row;
    }

    function syncCheckoutCart(cart, checkout) {
        if (!cart) return;

        const checkoutPage = form.closest('[data-checkout-page]');
        const warnings = Array.isArray(checkout?.warnings)
            ? checkout.warnings
            : (Array.isArray(cart.warnings) ? cart.warnings : []);
        const items = Array.isArray(checkout?.items)
            ? checkout.items
            : (Array.isArray(cart.items) ? cart.items : []);
        const productCount = Number(checkout?.product_count ?? cart.product_count ?? items.length);
        const totalQuantity = Number(checkout?.total_quantity ?? cart.total_quantity ?? 0);

        syncCartUi({ cart: cart, warnings: warnings });
        renderWarnings(checkoutPage?.querySelector('[data-cart-warnings]'), warnings);

        if (checkout?.amounts) {
            baseSummary = checkout;
        }

        const itemsContainer = checkoutPage?.querySelector('[data-checkout-items]');
        if (itemsContainer) {
            itemsContainer.replaceChildren(...items.map(checkoutItemElement));
        }

        const quantityLabel = checkoutPage?.querySelector('[data-checkout-total-quantity]');
        if (quantityLabel) {
            quantityLabel.textContent = totalQuantity + (totalQuantity === 1 ? ' unidad' : ' unidades');
        }

        document.querySelectorAll('[data-checkout-products]').forEach(function (counter) {
            counter.textContent = productCount + ' (' + totalQuantity + (totalQuantity === 1 ? ' unidad)' : ' unidades)');
        });
    }

    function renderPickupDetails(delivery) {
        if (!pickupDetails || delivery?.method !== 'pickup') return;

        if (pickupAddress) pickupAddress.textContent = String(delivery.pickup_address || 'Por confirmar');
        if (pickupWindow) pickupWindow.textContent = String(delivery.pickup_availability_label || 'en una fecha por confirmar');
        if (pickupHoldDays) pickupHoldDays.textContent = String(delivery.pickup_hold_days ?? '0');
        pickupDetails.classList.remove('d-none');
    }

    function syncAddressChoice() {
        const method = selectedMethod();
        const isHomeDelivery = method === 'home_delivery';
        const selected = form.querySelector('[data-checkout-address-choice]:checked');
        const isNew = isHomeDelivery && selected?.value === 'new';
        const provinceInput = newAddressPanel?.querySelector('[data-address-province]');

        addressSection?.classList.toggle('d-none', !isHomeDelivery);
        if (method !== 'pickup') {
            pickupDetails?.classList.add('d-none');
        }

        choices.forEach(function (choice) {
            const available = choice.dataset.deliveryAvailable !== '0';
            choice.disabled = !isHomeDelivery || !available;
            choice.required = isHomeDelivery && available;
        });

        newAddressPanel?.classList.toggle('d-none', !isNew);
        newAddressPanel?.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.matches('[data-checkout-new-address-input]') && field.dataset.locked === '1') {
                return;
            }

            field.disabled = !isNew || (
                field.matches('[data-address-district]')
                && !provinceInput?.value
            );
        });

    }

    function quotePayload() {
        const method = selectedMethod();

        if (method === 'pickup') {
            return { delivery_method: method };
        }

        if (method !== 'home_delivery') return null;

        const selected = form.querySelector('[data-checkout-address-choice]:checked');
        if (!selected || selected.disabled) return null;

        if (selected.value === 'new') {
            const ubigeo = newAddressPanel?.querySelector('[data-address-district]')?.value || '';
            return ubigeo ? { delivery_method: method, ubigeo: ubigeo } : null;
        }

        const addressId = Number(selected.dataset.addressId || 0);
        return addressId ? { delivery_method: method, address_id: addressId } : null;
    }

    async function requestQuote() {
        const payload = quotePayload();
        const sequence = ++quoteSequence;

        quoteController?.abort();
        quoteController = null;
        setQuoteReference('');

        if (selectedMethod() === 'pickup') {
            pickupDetails?.classList.add('d-none');
        }

        if (!payload || !quoteUrl) {
            setSummaryBusy(false);
            const unavailableAddress = form.querySelector('[data-checkout-address-choice]:checked[data-delivery-available="0"]');

            if (unavailableAddress && selectedMethod() === 'home_delivery') {
                resetSummary('La direccion seleccionada no tiene entrega a domicilio disponible.');
                setFeedback('warning', 'La entrega a domicilio no esta disponible para el distrito seleccionado. Elige otra direccion, recojo o consulta por WhatsApp.');
            } else {
                resetSummary('Selecciona una direccion y un distrito disponible para calcular el envio.');
                setFeedback('secondary', 'Completa la modalidad y la direccion para obtener una cotizacion.');
            }
            return;
        }

        quoteController = new AbortController();
        setSummaryBusy(true);
        setFeedback('secondary', 'Calculando la tarifa y el total actualizado...');

        try {
            const response = await fetch(quoteUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
                signal: quoteController.signal,
            });
            const data = await response.json();

            if (sequence !== quoteSequence) return;

            syncCheckoutCart(data.cart, data.checkout);

            if (data.cart && (!Array.isArray(data.cart.items) || data.cart.items.length === 0)) {
                const redirectUrl = safeUrl(data.redirect_url, '');

                setFeedback('warning', data.message || 'Tu carrito esta vacio. Regresa al carrito para continuar.');
                if (redirectUrl) window.location.assign(redirectUrl);
                return;
            }

            if (!response.ok || !data.delivery?.available) {
                pickupDetails?.classList.add('d-none');
                const amounts = data.checkout?.amounts || data.delivery?.summary?.amounts || baseSummary.amounts;
                renderSummary(amounts, 'No disponible', 'Elige otra direccion o modalidad para continuar.');
                setFeedback('warning', data.message || 'La modalidad seleccionada no esta disponible.');
                return;
            }

            const delivery = data.delivery;
            const shippingLabel = delivery.shipping_fee_cents === 0 ? 'Gratis' : delivery.formatted_shipping_fee;

            if (!delivery.quote_reference) {
                pickupDetails?.classList.add('d-none');
                resetSummary('No pudimos validar la referencia de la cotizacion. Intentalo nuevamente.');
                setFeedback('danger', 'La cotizacion no pudo ser validada. Selecciona nuevamente la modalidad de entrega.');
                return;
            }

            setQuoteReference(delivery.quote_reference);
            renderSummary(
                delivery.summary?.amounts,
                shippingLabel,
                delivery.method_label + '. Tarifa final con IGV incluido.',
            );
            renderPickupDetails(delivery);
            setFeedback('success', delivery.message || 'Cotizacion actualizada.');
        } catch (error) {
            if (error.name === 'AbortError') return;

            pickupDetails?.classList.add('d-none');
            resetSummary('No pudimos actualizar la cotizacion. Intentalo nuevamente.');
            setFeedback('danger', 'No pudimos conectar con el servicio de cotizacion. Intentalo nuevamente.');
        } finally {
            if (sequence === quoteSequence) setSummaryBusy(false);
        }
    }

    choices.forEach(function (choice) {
        choice.addEventListener('change', function () {
            syncAddressChoice();
            requestQuote();
        });
    });
    deliveryMethods.forEach(function (method) {
        method.addEventListener('change', function () {
            syncAddressChoice();
            requestQuote();
        });
    });
    newAddressPanel?.querySelector('[data-address-province]')?.addEventListener('change', requestQuote);
    newAddressPanel?.querySelector('[data-address-district]')?.addEventListener('change', requestQuote);
    form.addEventListener('submit', function (event) {
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        if (quoteReady && quoteReferenceInput?.value) {
            form.dataset.submitting = '1';
            form.setAttribute('aria-busy', 'true');

            if (submitButton) {
                submitButton.disabled = true;
                const label = submitButton.querySelector('span');
                if (label) label.textContent = 'Guardando...';
            }

            return;
        }

        event.preventDefault();
        setFeedback('warning', 'Obten una cotizacion vigente antes de continuar al comprobante.');
        feedback?.focus({ preventScroll: false });
    });

    syncAddressChoice();
    if (hasInitialQuote) {
        setQuoteReference(quoteReferenceInput?.value || '');
        setSummaryBusy(false);
    } else {
        requestQuote();
    }
});

document.querySelectorAll('[data-checkout-fiscal-form]').forEach(function (form) {
    const types = form.querySelectorAll('[data-checkout-fiscal-type]');
    const panels = form.querySelectorAll('[data-checkout-fiscal-panel]');
    const submitButton = form.querySelector('[data-checkout-fiscal-submit]');

    function syncFiscalType() {
        const selectedType = form.querySelector('[data-checkout-fiscal-type]:checked')?.value || 'receipt';

        panels.forEach(function (panel) {
            const active = panel.dataset.checkoutFiscalPanel === selectedType;

            panel.classList.toggle('d-none', !active);
            panel.querySelectorAll('[data-checkout-fiscal-input]').forEach(function (field) {
                field.disabled = !active;
            });
        });
    }

    types.forEach(function (type) {
        type.addEventListener('change', syncFiscalType);
    });

    form.addEventListener('submit', function (event) {
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = '1';
        form.setAttribute('aria-busy', 'true');

        if (submitButton) {
            submitButton.disabled = true;
            const label = submitButton.querySelector('span');
            if (label) label.textContent = 'Revisando...';
        }
    });

    syncFiscalType();
});

document.querySelectorAll('[data-checkout-page]').forEach(function (page) {
    const activeStage = page.querySelector('[data-checkout-stage]:not([hidden])');
    const errorTarget = activeStage?.querySelector('.is-invalid:not([disabled]), [data-checkout-error]');

    if (!errorTarget) return;

    if (!errorTarget.matches('input, select, textarea, button, a[href]')) {
        errorTarget.setAttribute('tabindex', '-1');
    }

    window.requestAnimationFrame(function () {
        errorTarget.focus({ preventScroll: false });
    });
});

document.querySelectorAll('[data-checkout-overview]').forEach(function (overview) {
    const mobileViewport = window.matchMedia('(max-width: 991.98px)');

    function syncOverview(event) {
        overview.open = !event.matches;
    }

    syncOverview(mobileViewport);
    mobileViewport.addEventListener('change', syncOverview);
});

const defaultAddressForm = document.querySelector('[data-default-address-form]');
document.querySelectorAll('[data-default-address-radio]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        if (!defaultAddressForm || !radio.checked) return;

        defaultAddressForm.setAttribute('aria-busy', 'true');
        document.querySelectorAll('[data-default-address-radio]').forEach(function (addressRadio) {
            addressRadio.disabled = addressRadio !== radio;
        });

        if (typeof defaultAddressForm.requestSubmit === 'function') {
            defaultAddressForm.requestSubmit();
        } else {
            defaultAddressForm.submit();
        }
    });
});

const deleteAddressModal = document.getElementById('deleteAddressModal');
if (deleteAddressModal) {
    const deleteForm = deleteAddressModal.querySelector('[data-address-delete-form]');
    const deleteLabel = deleteAddressModal.querySelector('[data-address-delete-label]');
    const defaultNote = deleteAddressModal.querySelector('[data-address-delete-default-note]');

    deleteAddressModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger || !deleteForm || !deleteLabel || !defaultNote) return;

        deleteForm.action = trigger.dataset.addressAction || '';
        deleteLabel.textContent = trigger.dataset.addressLabel || 'esta direccion';
        defaultNote.classList.toggle('d-none', trigger.dataset.addressDefault !== '1');
    });
}

document.querySelectorAll('[data-inventory-movement-form]').forEach(function (form) {
    const type = form.querySelector('[data-movement-type]');
    const quantityField = form.querySelector('[data-movement-quantity-field]');
    const adjustmentField = form.querySelector('[data-movement-adjustment-field]');
    const quantityInput = form.querySelector('[data-movement-quantity]');
    const newStockInput = form.querySelector('[data-movement-new-stock]');
    if (!type || !quantityField || !adjustmentField) return;

    function syncMovementFields() {
        const isAdjustment = type.value === 'adjustment';

        quantityField.classList.toggle('d-none', isAdjustment);
        adjustmentField.classList.toggle('d-none', !isAdjustment);
        quantityInput?.toggleAttribute('required', !isAdjustment);
        newStockInput?.toggleAttribute('required', isAdjustment);
    }

    type.addEventListener('change', syncMovementFields);
    syncMovementFields();
});

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function updateCartCount(cart) {
    if (!cart) return;

    document.querySelectorAll('[data-cart-count]').forEach(function (counter) {
        counter.textContent = cart.total_quantity || 0;
    });
}

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value ?? '';

    return element.innerHTML;
}

function ensureToastContainer() {
    let container = document.querySelector('[data-cart-toast-container]');

    if (!container) {
        container = document.createElement('div');
        container.className = 'cart-toast-container toast-container position-fixed top-0 start-50 translate-middle-x p-3';
        container.style.zIndex = '1080';
        container.setAttribute('data-cart-toast-container', '');
        document.body.appendChild(container);
    }

    return container;
}

function showCartToast(message, type) {
    if (!message) return;

    const container = ensureToastContainer();
    const toast = document.createElement('div');
    const isError = type === 'error';
    const iconClass = isError ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success';

    toast.className = 'cart-toast cart-toast-' + (isError ? 'error' : 'success') + ' toast align-items-center';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = [
        '<div class="d-flex align-items-center">',
        '<i class="bi ' + iconClass + ' cart-toast-icon ms-3"></i>',
        '<div class="toast-body">' + escapeHtml(message) + '</div>',
        '<button type="button" class="btn-close me-3 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>',
        '</div>',
    ].join('');

    container.appendChild(toast);

    if (window.bootstrap) {
        const instance = window.bootstrap.Toast.getOrCreateInstance(toast, { delay: 2600 });
        toast.addEventListener('hidden.bs.toast', function () {
            toast.remove();
        });
        instance.show();
    } else {
        setTimeout(function () {
            toast.remove();
        }, 2600);
    }
}

function renderWarnings(container, warnings) {
    if (!container) return;

    const hasWarnings = warnings && warnings.length;
    const list = container.querySelector('[data-cart-warnings-list]') || container;
    container.classList.toggle('d-none', !hasWarnings);
    list.innerHTML = hasWarnings ? warnings.map(function (warning) {
        return '<div>' + escapeHtml(warning) + '</div>';
    }).join('') : '';
}

function cartItemMarkup(item) {
    return [
        '<tr data-cart-page-item data-product-id="' + item.product_id + '">',
        '<td><div class="d-flex align-items-center gap-3">',
        '<a class="thumb-sm flex-shrink-0" href="' + item.url + '" style="background-image: url(\'' + item.image_url + '\')"></a>',
        '<div><a class="fw-bold" href="' + item.url + '">' + escapeHtml(item.name) + '</a><br>',
        '<span class="small text-muted">' + escapeHtml(item.description || 'Producto natural') + '</span></div>',
        '</div></td>',
        '<td><strong>' + item.formatted_unit_price + '</strong></td>',
        '<td><div class="quantity-control">',
        '<button data-quantity="minus" data-cart-update-button type="button">-</button>',
        '<input type="number" value="' + item.quantity + '" min="1" max="' + Math.max(1, item.stock) + '" data-cart-page-quantity data-cart-update-url="' + item.update_url + '">',
        '<button data-quantity="plus" data-cart-update-button type="button">+</button>',
        '</div></td>',
        '<td><strong>' + item.formatted_subtotal + '</strong></td>',
        '<td><button class="btn btn-link text-muted" type="button" data-cart-remove data-cart-remove-url="' + item.remove_url + '" aria-label="Eliminar ' + escapeHtml(item.name) + '"><i class="bi bi-trash"></i></button></td>',
        '</tr>',
    ].join('');
}

function drawerItemMarkup(item) {
    return [
        '<div class="cart-drawer-item d-flex gap-3" data-cart-drawer-item data-product-id="' + item.product_id + '">',
        '<a class="thumb-sm flex-shrink-0" href="' + item.url + '" style="background-image: url(\'' + item.image_url + '\')"></a>',
        '<div class="flex-grow-1 min-w-0">',
        '<a class="cart-drawer-item-title d-block" href="' + item.url + '">' + escapeHtml(item.name) + '</a>',
        '<div class="small text-muted mb-2">' + item.formatted_unit_price + ' c/u</div>',
        '<div class="d-flex align-items-center justify-content-between gap-2">',
        '<div class="quantity-control cart-drawer-quantity">',
        '<button data-quantity="minus" data-cart-update-button type="button">-</button>',
        '<input type="number" value="' + item.quantity + '" min="1" max="' + Math.max(1, item.stock) + '" data-cart-drawer-quantity data-cart-update-url="' + item.update_url + '" aria-label="Cantidad de ' + escapeHtml(item.name) + '">',
        '<button data-quantity="plus" data-cart-update-button type="button">+</button>',
        '</div>',
        '<strong class="small">' + item.formatted_subtotal + '</strong>',
        '<button class="btn btn-link btn-sm text-muted p-0" type="button" data-cart-remove data-cart-remove-url="' + item.remove_url + '" aria-label="Eliminar ' + escapeHtml(item.name) + '"><i class="bi bi-trash"></i></button>',
        '</div></div></div>',
    ].join('');
}

function renderCartDrawer(cart, warnings) {
    const drawer = document.querySelector('[data-cart-drawer]');
    if (!drawer || !cart) return;

    const isEmpty = !cart.items || cart.items.length === 0;
    const count = drawer.querySelector('[data-cart-drawer-count]');
    if (count) count.textContent = cart.total_quantity || 0;

    drawer.querySelector('[data-cart-drawer-empty]')?.classList.toggle('d-none', !isEmpty);
    drawer.querySelector('[data-cart-drawer-filled]')?.classList.toggle('d-none', isEmpty);
    renderWarnings(drawer.querySelector('[data-cart-drawer-warnings]'), warnings || cart.warnings || []);

    const itemsContainer = drawer.querySelector('[data-cart-drawer-items]');
    if (itemsContainer) {
        itemsContainer.innerHTML = isEmpty ? '' : cart.items.map(drawerItemMarkup).join('');
    }

    const subtotal = drawer.querySelector('[data-cart-drawer-subtotal]');
    const total = drawer.querySelector('[data-cart-drawer-total]');
    if (subtotal) subtotal.textContent = cart.formatted_subtotal || 'S/ 0.00';
    if (total) total.textContent = cart.formatted_total || 'S/ 0.00';
}

function renderCartPage(cart, warnings) {
    const page = document.querySelector('[data-cart-page]');
    if (!page || !cart) return;

    const isEmpty = !cart.items || cart.items.length === 0;
    page.querySelector('[data-cart-filled]')?.classList.toggle('d-none', isEmpty);
    page.querySelector('[data-cart-empty]')?.classList.toggle('d-none', !isEmpty);
    renderWarnings(page.querySelector('[data-cart-warnings]'), warnings || cart.warnings || []);

    const itemsContainer = page.querySelector('[data-cart-page-items]');
    if (itemsContainer) {
        itemsContainer.innerHTML = isEmpty ? '' : cart.items.map(cartItemMarkup).join('');
    }

    const products = page.querySelector('[data-cart-summary-products]');
    const subtotal = page.querySelector('[data-cart-summary-subtotal]');
    const total = page.querySelector('[data-cart-summary-total]');
    if (products) products.textContent = (cart.product_count || 0) + ' (' + (cart.total_quantity || 0) + ' unidades)';
    if (subtotal) subtotal.textContent = cart.formatted_subtotal || 'S/ 0.00';
    if (total) total.textContent = cart.formatted_total || 'S/ 0.00';

    page.querySelectorAll('[data-cart-clear]').forEach(function (button) {
        button.disabled = false;
    });
}

function syncCartUi(data) {
    if (!data || !data.cart) return;

    updateCartCount(data.cart);
    renderCartDrawer(data.cart, data.warnings);
    renderCartPage(data.cart, data.warnings);
}

async function cartRequest(url, method, body) {
    const response = await fetch(url, {
        method: method,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body ? JSON.stringify(body) : null,
    });
    const data = await response.json();
    syncCartUi(data);

    return { response: response, data: data };
}

function cartQuantityFrom(button) {
    const quantityRoot = button.closest('[data-cart-form]') || button.closest('[data-cart-quantity-modal]');
    const quantityInput = quantityRoot ? quantityRoot.querySelector('[data-cart-quantity]') : null;
    const value = Number(quantityInput?.value || 1);

    return Math.max(1, Number.isFinite(value) ? value : 1);
}

function openCartQuantityModal(button) {
    const modal = document.querySelector('[data-cart-quantity-modal]');
    if (!modal || !window.bootstrap) {
        submitCartAdd(button);
        return;
    }

    const stock = Math.max(1, Number(button.dataset.cartModalStock || 1));
    const submit = modal.querySelector('[data-cart-modal-submit]');
    const quantityInput = modal.querySelector('[data-cart-quantity]');
    const name = modal.querySelector('[data-cart-modal-name]');
    const price = modal.querySelector('[data-cart-modal-price]');
    const image = modal.querySelector('[data-cart-modal-image]');
    const stockLabel = modal.querySelector('[data-cart-modal-stock-label]');

    if (submit) {
        submit.dataset.cartProductId = button.dataset.cartProductId || '';
        submit.dataset.cartUrl = button.dataset.cartUrl || '';
    }

    if (quantityInput) {
        quantityInput.value = '1';
        quantityInput.max = String(stock);
        delete quantityInput.dataset.quantityLimitExceeded;
    }

    if (name) name.textContent = button.dataset.cartModalName || 'Producto natural';
    if (price) price.textContent = button.dataset.cartModalPrice || 'S/ 0.00';
    if (stockLabel) stockLabel.textContent = 'Disponible: ' + stock + ' unidad' + (stock === 1 ? '' : 'es');
    if (image) image.style.backgroundImage = "url('" + (button.dataset.cartModalImage || '') + "')";

    window.bootstrap.Modal.getOrCreateInstance(modal).show();
}

async function submitCartAdd(button) {
    const productId = Number(button.dataset.cartProductId);
    const url = button.dataset.cartUrl;

    if (!productId || !url) return;

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Agregando';

    try {
        const result = await cartRequest(url, 'POST', {
            product_id: productId,
            quantity: cartQuantityFrom(button),
        });

        showCartToast(result.data.message || (result.response.ok ? 'Producto agregado al carrito.' : 'No se pudo agregar el producto.'), result.response.ok ? 'success' : 'error');
        if (result.response.ok) {
            const modal = button.closest('[data-cart-quantity-modal]');
            if (modal && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modal).hide();
            }
        }
    } catch (error) {
        showCartToast('No se pudo conectar con el carrito. Intentalo nuevamente.', 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
}

async function submitCartUpdate(input) {
    const quantity = Math.max(1, Number(input.value || 1));
    const url = input.dataset.cartUpdateUrl;
    if (!url) return;

    if (input.dataset.quantityLimitExceeded === '1') {
        delete input.dataset.quantityLimitExceeded;
        showCartToast('No hay stock suficiente. Stock disponible: ' + input.max + '.', 'error');
        return;
    }

    try {
        const result = await cartRequest(url, 'PATCH', { quantity: quantity });
        showCartToast(result.data.message || 'Carrito actualizado.', result.response.ok ? 'success' : 'error');
    } catch (error) {
        showCartToast('No se pudo actualizar el carrito.', 'error');
    }
}

async function submitCartRemove(button) {
    const url = button.dataset.cartRemoveUrl;
    if (!url) return;

    button.disabled = true;

    try {
        const result = await cartRequest(url, 'DELETE');
        showCartToast(result.data.message || 'Producto retirado del carrito.', result.response.ok ? 'success' : 'error');
    } catch (error) {
        showCartToast('No se pudo retirar el producto.', 'error');
        button.disabled = false;
    }
}

async function submitCartClear(button) {
    const page = document.querySelector('[data-cart-page]');
    const url = page?.dataset.cartClearUrl;
    if (!url) return;

    button.disabled = true;

    try {
        const result = await cartRequest(url, 'DELETE');
        showCartToast(result.data.message || 'Carrito vaciado.', result.response.ok ? 'success' : 'error');
    } catch (error) {
        showCartToast('No se pudo vaciar el carrito.', 'error');
        button.disabled = false;
    }
}

async function submitCartWarningsClear(button) {
    const root = button.closest('[data-cart-page], [data-cart-drawer], [data-checkout-page]');
    const url = root?.dataset.cartWarningsClearUrl;
    if (!url) return;

    button.disabled = true;

    try {
        const result = await cartRequest(url, 'DELETE');
        if (result.response.ok) {
            document.querySelectorAll('[data-cart-warnings], [data-cart-drawer-warnings]').forEach(function (container) {
                renderWarnings(container, []);
            });
        }
    } catch (error) {
        showCartToast('No se pudo cerrar el aviso del carrito.', 'error');
    } finally {
        button.disabled = false;
    }
}

document.addEventListener('click', function (event) {
    const cartButton = event.target.closest('[data-cart-add]');

    if (cartButton) {
        event.preventDefault();
        if (cartButton.matches('[data-cart-modal-trigger]') && !cartButton.closest('[data-cart-form]')) {
            openCartQuantityModal(cartButton);
            return;
        }
        submitCartAdd(cartButton);
    }

    const updateButton = event.target.closest('[data-cart-update-button]');
    if (updateButton) {
        const control = updateButton.closest('.quantity-control');
        const input = control ? control.querySelector('[data-cart-page-quantity], [data-cart-drawer-quantity]') : null;
        if (input) {
            submitCartUpdate(input);
        }
    }

    const removeButton = event.target.closest('[data-cart-remove]');
    if (removeButton) {
        event.preventDefault();
        submitCartRemove(removeButton);
    }

    const clearButton = event.target.closest('[data-cart-clear]');
    if (clearButton) {
        event.preventDefault();
        submitCartClear(clearButton);
    }

    const warningsClearButton = event.target.closest('[data-cart-warnings-clear]');
    if (warningsClearButton) {
        event.preventDefault();
        submitCartWarningsClear(warningsClearButton);
    }
});

document.addEventListener('change', function (event) {
    const quantityInput = event.target.closest('[data-cart-page-quantity], [data-cart-drawer-quantity]');

    if (quantityInput) {
        submitCartUpdate(quantityInput);
    }
});

document.querySelectorAll('[data-cart-count][data-cart-info-url]').forEach(function (counter) {
    fetch(counter.dataset.cartInfoUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (data) {
            if (data) {
                syncCartUi(data);
            }
        });
});

document.querySelectorAll('[data-product-gallery]').forEach(function (gallery) {
    const mainImage = gallery.querySelector('[data-product-gallery-main]');
    const openButton = gallery.querySelector('[data-product-gallery-open]');
    const modalElement = document.getElementById('productImageModal');
    const modalImage = modalElement ? modalElement.querySelector('[data-product-gallery-modal-image]') : null;
    if (!mainImage || !openButton) return;

    function setCurrentImage(url, alt) {
        mainImage.style.backgroundImage = "url('" + url + "')";
        openButton.dataset.imageUrl = url;
        openButton.dataset.imageAlt = alt;

        if (modalImage) {
            modalImage.src = url;
            modalImage.alt = alt;
        }
    }

    gallery.querySelectorAll('[data-product-gallery-thumb]').forEach(function (thumbnail) {
        thumbnail.addEventListener('click', function () {
            const url = thumbnail.dataset.imageUrl;
            const alt = thumbnail.dataset.imageAlt || '';
            if (!url) return;

            gallery.querySelectorAll('[data-product-gallery-thumb]').forEach(function (item) {
                const isActive = item === thumbnail;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            setCurrentImage(url, alt);
        });
    });

    openButton.addEventListener('click', function () {
        const url = openButton.dataset.imageUrl;
        const alt = openButton.dataset.imageAlt || '';
        if (!url || !modalElement || !modalImage || !window.bootstrap) return;

        modalImage.src = url;
        modalImage.alt = alt;
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
});

document.querySelectorAll('[data-image-preview]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
        const modalElement = document.getElementById('adminImagePreviewModal');
        const modalImage = modalElement ? modalElement.querySelector('[data-image-preview-modal-image]') : null;
        const url = trigger.dataset.imageUrl;
        const alt = trigger.dataset.imageAlt || '';
        if (!modalElement || !modalImage || !url || !window.bootstrap) return;

        modalImage.src = url;
        modalImage.alt = alt;
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
});

document.querySelectorAll('[data-slug-source]').forEach(function (source) {
    const target = document.querySelector(source.dataset.slugTarget);
    if (!target) return;

    let slugTouched = Boolean(target.value);

    target.addEventListener('input', function () {
        slugTouched = Boolean(target.value);
    });

    source.addEventListener('blur', function () {
        if (slugTouched || !source.value.trim()) return;

        const url = new URL(source.dataset.slugUrl, window.location.origin);
        url.searchParams.set('name', source.value.trim());
        if (source.dataset.slugIgnore) {
            url.searchParams.set('ignore', source.dataset.slugIgnore);
        }

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (data && data.slug && !target.value) {
                    target.value = data.slug;
                }
            });
    });
});

document.querySelectorAll('[data-image-cropper]').forEach(function (cropper) {
    const input = cropper.querySelector('[data-cropper-input]');
    const output = cropper.querySelector('[data-cropper-output]');
    const canvas = cropper.querySelector('[data-cropper-canvas]');
    const zoomInput = cropper.querySelector('[data-cropper-zoom]');
    const removeButton = cropper.querySelector('[data-cropper-remove]');
    const removeInput = cropper.querySelector('[data-cropper-remove-input]');
    const currentMedia = cropper.querySelector('[data-current-media]');
    const placeholder = cropper.querySelector('[data-cropper-placeholder]');
    const frame = canvas ? canvas.closest('.cropper-frame') : null;
    if (!input || !output || !canvas) return;

    const width = Number(cropper.dataset.cropperWidth || canvas.width || 800);
    const height = Number(cropper.dataset.cropperHeight || canvas.height || 600);
    const context = canvas.getContext('2d');
    const image = new Image();
    let loaded = false;
    let offsetX = 0;
    let offsetY = 0;
    let isDragging = false;
    let lastPointerX = 0;
    let lastPointerY = 0;
    let currentZoom = Number(zoomInput?.value || 1);
    let shouldWriteOutput = false;

    canvas.width = width;
    canvas.height = height;
    canvas.style.aspectRatio = width + ' / ' + height;
    if (frame) {
        frame.style.aspectRatio = width + ' / ' + height;
    }

    function setPlaceholderVisible(isVisible) {
        frame?.classList.toggle('has-cropper-placeholder', isVisible);
        placeholder?.classList.toggle('d-none', !isVisible);
    }

    function getMetrics(zoom) {
        zoom = zoom || currentZoom;
        const scale = Math.max(width / image.width, height / image.height) * zoom;
        const drawWidth = image.width * scale;
        const drawHeight = image.height * scale;

        return {
            drawWidth: drawWidth,
            drawHeight: drawHeight,
            minX: Math.min(0, width - drawWidth),
            minY: Math.min(0, height - drawHeight),
            maxX: 0,
            maxY: 0,
        };
    }

    function clampPosition() {
        const metrics = getMetrics();

        offsetX = Math.min(metrics.maxX, Math.max(metrics.minX, offsetX));
        offsetY = Math.min(metrics.maxY, Math.max(metrics.minY, offsetY));
    }

    function centerImage() {
        const metrics = getMetrics();

        offsetX = (width - metrics.drawWidth) / 2;
        offsetY = (height - metrics.drawHeight) / 2;
        clampPosition();
    }

    function draw() {
        if (!loaded) return;

        const metrics = getMetrics();
        clampPosition();

        context.clearRect(0, 0, width, height);
        context.drawImage(image, offsetX, offsetY, metrics.drawWidth, metrics.drawHeight);

        if (shouldWriteOutput) {
            output.value = canvas.toDataURL('image/jpeg', 0.9);
        }
    }

    function markCropperChanged() {
        if (!loaded) return;

        shouldWriteOutput = true;
    }

    function loadImage(source, writeOutput) {
        image.onload = function () {
            loaded = true;
            shouldWriteOutput = writeOutput;
            setPlaceholderVisible(false);
            if (!shouldWriteOutput) {
                output.value = '';
            }
            if (zoomInput) {
                zoomInput.value = 1;
            }
            currentZoom = 1;
            centerImage();
            draw();
        };

        image.onerror = function () {
            loaded = false;
            shouldWriteOutput = false;
            output.value = '';
            context.clearRect(0, 0, width, height);
            setPlaceholderVisible(true);
        };

        image.src = source;
    }

    function pointerPosition(event) {
        const source = event.touches ? event.touches[0] : event;

        return {
            x: source.clientX,
            y: source.clientY,
        };
    }

    function startDrag(event) {
        if (!loaded) return;

        const position = pointerPosition(event);
        isDragging = true;
        lastPointerX = position.x;
        lastPointerY = position.y;
        canvas.classList.add('is-dragging');
        event.preventDefault();
    }

    function moveDrag(event) {
        if (!isDragging) return;

        const position = pointerPosition(event);
        const rect = canvas.getBoundingClientRect();
        const scaleX = width / rect.width;
        const scaleY = height / rect.height;

        offsetX += (position.x - lastPointerX) * scaleX;
        offsetY += (position.y - lastPointerY) * scaleY;
        lastPointerX = position.x;
        lastPointerY = position.y;
        markCropperChanged();
        draw();
        event.preventDefault();
    }

    function endDrag() {
        isDragging = false;
        canvas.classList.remove('is-dragging');
    }

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];
        if (!file) return;

        if (removeInput) {
            removeInput.value = '0';
        }

        loadImage(URL.createObjectURL(file), true);
    });

    removeButton?.addEventListener('click', function () {
        if (removeInput) {
            removeInput.value = '1';
        }

        input.value = '';
        output.value = '';
        loaded = false;
        shouldWriteOutput = false;
        context.clearRect(0, 0, width, height);
        setPlaceholderVisible(true);
        currentMedia?.classList.add('d-none');
    });

    zoomInput?.addEventListener('input', function () {
        if (!loaded) return;

        const nextZoom = Number(zoomInput.value || 1);
        const previousMetrics = getMetrics(currentZoom);
        const centerXRatio = (width / 2 - offsetX) / previousMetrics.drawWidth;
        const centerYRatio = (height / 2 - offsetY) / previousMetrics.drawHeight;
        const nextMetrics = getMetrics(nextZoom);

        offsetX = width / 2 - nextMetrics.drawWidth * centerXRatio;
        offsetY = height / 2 - nextMetrics.drawHeight * centerYRatio;
        currentZoom = nextZoom;
        markCropperChanged();
        draw();
    });

    canvas.addEventListener('mousedown', startDrag);
    canvas.addEventListener('touchstart', startDrag, { passive: false });
    window.addEventListener('mousemove', moveDrag);
    window.addEventListener('touchmove', moveDrag, { passive: false });
    window.addEventListener('mouseup', endDrag);
    window.addEventListener('touchend', endDrag);

    if (cropper.dataset.cropperPreviewUrl) {
        loadImage(cropper.dataset.cropperPreviewUrl, false);
    } else {
        setPlaceholderVisible(true);
    }
});
