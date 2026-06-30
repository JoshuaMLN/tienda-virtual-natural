document.addEventListener('click', function (event) {
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
        document.querySelector('.admin-sidebar')?.classList.toggle('show');
    }
});

document.querySelectorAll('[data-checkout-option]').forEach(function (option) {
    option.addEventListener('change', function () {
        document.querySelectorAll('[data-checkout-panel]').forEach(function (panel) {
            panel.classList.toggle('d-none', panel.dataset.checkoutPanel !== option.value);
        });
    });
});
