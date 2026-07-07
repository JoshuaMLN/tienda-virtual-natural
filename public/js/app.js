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
        const nextValue = Math.max(1, Number(input.value || 1) + step);
        input.value = nextValue;
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

document.querySelectorAll('[data-checkout-option]').forEach(function (option) {
    option.addEventListener('change', function () {
        document.querySelectorAll('[data-checkout-panel]').forEach(function (panel) {
            panel.classList.toggle('d-none', panel.dataset.checkoutPanel !== option.value);
        });
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
