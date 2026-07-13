const applyTheme = () => {
    const storedTheme = localStorage.getItem('retailpos.theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    document.documentElement.classList.toggle('dark', storedTheme === 'dark' || (!storedTheme && prefersDark));
};

const closeDropdowns = (except = null) => {
    document.querySelectorAll('[id$="-menu"]').forEach((menu) => {
        if (menu !== except) {
            menu.classList.add('hidden');
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    applyTheme();

    const body = document.body;
    const sidebarCollapsed = localStorage.getItem('retailpos.sidebar.collapsed') === 'true';

    body.classList.toggle('sidebar-collapsed', sidebarCollapsed);

    document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
        button.addEventListener('click', () => body.classList.add('sidebar-mobile-open'));
    });

    document.querySelectorAll('[data-sidebar-close], [data-sidebar-overlay]').forEach((button) => {
        button.addEventListener('click', () => body.classList.remove('sidebar-mobile-open'));
    });

    document.querySelectorAll('[data-sidebar-collapse]').forEach((button) => {
        button.addEventListener('click', () => {
            const collapsed = !body.classList.contains('sidebar-collapsed');

            body.classList.toggle('sidebar-collapsed', collapsed);
            localStorage.setItem('retailpos.sidebar.collapsed', String(collapsed));
        });
    });

    document.querySelectorAll('[data-dropdown-button]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();

            const menu = document.getElementById(button.dataset.dropdownButton);

            if (!menu) {
                return;
            }

            const isHidden = menu.classList.contains('hidden');

            closeDropdowns(menu);
            menu.classList.toggle('hidden', !isHidden);
        });
    });

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copyText;

            if (!text || !navigator.clipboard) {
                return;
            }

            await navigator.clipboard.writeText(text);
            const originalLabel = button.textContent;
            button.textContent = 'Copied';

            window.setTimeout(() => {
                button.textContent = originalLabel;
            }, 1600);
        });
    });

    document.addEventListener('click', () => closeDropdowns());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            body.classList.remove('sidebar-mobile-open');
            closeDropdowns();
        }
    });

    const posApp = document.querySelector('[data-pos-app]');

    if (!posApp) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const parse = (value, fallback) => {
        try { return JSON.parse(value || ''); } catch { return fallback; }
    };
    const state = {
        cart: parse(posApp.dataset.initialCart, []),
        customer: parse(posApp.dataset.initialCustomer, null),
        suggestions: {},
        paymentMethod: 'cash',
    };
    const money = (value) => Number(value || 0).toFixed(2);
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const manualDiscount = () => Math.max(0, Number(document.querySelector('[data-pos-discount]')?.value || 0));
    const subtotal = () => state.cart.reduce((total, item) => total + Number(item.price) * Number(item.quantity), 0);
    const total = () => Math.max(0, subtotal() - manualDiscount());

    const productMarkup = (product) => `<button type="button" data-pos-product='${escapeHtml(JSON.stringify(product))}' class="pos-product-card text-left"><span class="grid aspect-square place-items-center rounded-md bg-teal-50 text-lg font-semibold text-teal-700">${escapeHtml(product.name).slice(0, 1)}</span><span class="mt-2 block truncate text-sm font-semibold">${escapeHtml(product.name)}</span><span class="mt-1 flex items-center justify-between text-xs text-slate-500"><span>${escapeHtml(product.sku || '')}</span><span class="font-semibold text-teal-700">${money(product.price)}</span></span></button>`;

    const bindProducts = (scope = document) => {
        scope.querySelectorAll('[data-pos-product]').forEach((button) => {
            if (button.dataset.posBound) return;
            button.dataset.posBound = 'true';
            button.addEventListener('click', () => addProduct(parse(button.dataset.posProduct, null)));
        });
    };

    const addProduct = (product) => {
        if (!product) return;
        const existing = state.cart.find((item) => Number(item.id) === Number(product.id));
        if (existing) existing.quantity = Number(existing.quantity) + 1;
        else state.cart.push({ ...product, quantity: 1 });
        render();
    };

    const changeQuantity = (id, amount) => {
        const item = state.cart.find((cartItem) => Number(cartItem.id) === Number(id));
        if (!item) return;
        item.quantity = Number(item.quantity) + amount;
        if (item.quantity <= 0) state.cart = state.cart.filter((cartItem) => Number(cartItem.id) !== Number(id));
        render();
    };

    const bindCartActions = (scope = document) => {
        scope.querySelectorAll('[data-pos-quantity]').forEach((button) => {
            if (button.dataset.posBound) return;
            button.dataset.posBound = 'true';
            button.addEventListener('click', () => changeQuantity(button.dataset.productId, Number(button.dataset.posQuantity)));
        });
    };

    const customerMarkup = (customer) => {
        if (!customer) return '';
        return `<div class="rounded-lg border border-teal-100 bg-teal-50 p-3"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-900">${escapeHtml(customer.name)}</p><p class="mt-1 text-xs text-slate-600">${escapeHtml(customer.group || 'Customer')} · ${escapeHtml(customer.mobile || '')}</p></div><span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-teal-700">${escapeHtml(customer.loyalty_points)} pts</span></div><div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600"><span>Wallet ${money(customer.wallet_balance)}</span><span>Last ${escapeHtml(customer.last_purchase_at || 'None')}</span><span>Birthday ${escapeHtml(customer.birthday || '—')}</span><span class="truncate">${escapeHtml(customer.retention_note || '')}</span></div></div>`;
    };

    const quickCustomerMarkup = () => `<div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><p class="text-sm font-medium">New customer</p><div class="mt-2 flex gap-2"><input data-pos-quick-name placeholder="Name" class="min-w-0 flex-1"><button type="button" data-pos-quick-save class="rounded-lg bg-teal-700 px-3 text-sm font-semibold text-white">Add</button></div></div>`;

    const bindQuickCustomer = () => {
        document.querySelectorAll('[data-pos-quick-save]').forEach((button) => {
            if (button.dataset.posBound) return;
            button.dataset.posBound = 'true';
            button.addEventListener('click', async () => {
                const mobile = document.querySelector('[data-pos-customer-mobile]')?.value.trim();
                const name = document.querySelector('[data-pos-quick-name]')?.value.trim();
                if (!mobile) return;
                const response = await fetch(posApp.dataset.quickCustomerUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ mobile, name }) });
                if (!response.ok) return;
                const payload = await response.json();
                state.customer = payload.customer;
                state.suggestions = payload.suggestions || {};
                render();
            });
        });
    };

    const suggestionMarkup = () => {
        if (!state.customer) return '';
        const labels = { regular: 'Regular', frequent: 'Frequent', recent: 'Recent', last: 'Last purchased', addons: 'Add-ons' };
        return Object.entries(state.suggestions || {}).filter(([, products]) => products?.length).map(([key, products]) => `<section class="mb-4"><h3 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">${labels[key]}</h3><div class="flex gap-2 overflow-x-auto pb-1">${products.map((product) => `<button type="button" data-pos-product='${escapeHtml(JSON.stringify(product))}' class="min-w-32 rounded-lg border border-teal-100 bg-white p-2 text-left text-xs shadow-sm"><span class="block truncate font-semibold">${escapeHtml(product.name)}</span><span class="mt-1 block text-teal-700">${money(product.price)}</span></button>`).join('')}</div></section>`).join('');
    };

    const render = () => {
        const sub = subtotal();
        const discount = manualDiscount();
        const finalTotal = total();
        const cartMarkup = state.cart.map((item) => `<div class="flex items-center gap-3 rounded-lg border border-slate-100 bg-white p-2"><span class="grid size-9 shrink-0 place-items-center rounded-md bg-slate-100 text-xs font-bold text-slate-600">${escapeHtml(item.name).slice(0, 1)}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">${escapeHtml(item.name)}</p><p class="text-xs text-slate-500">${money(item.price)} each</p></div><div class="flex items-center gap-1"><button type="button" data-pos-quantity="-1" data-product-id="${item.id}" class="grid size-8 place-items-center rounded-md bg-slate-100 text-slate-600" aria-label="Reduce quantity">−</button><span class="w-7 text-center text-sm font-semibold">${item.quantity}</span><button type="button" data-pos-quantity="1" data-product-id="${item.id}" class="grid size-8 place-items-center rounded-md bg-slate-100 text-slate-600" aria-label="Increase quantity">+</button></div></div>`).join('');
        document.querySelectorAll('[data-pos-cart-items]').forEach((node) => { node.innerHTML = cartMarkup; });
        document.querySelectorAll('[data-pos-empty-cart]').forEach((node) => node.classList.toggle('hidden', state.cart.length > 0));
        document.querySelectorAll('[data-pos-subtotal]').forEach((node) => { node.textContent = money(sub); });
        document.querySelectorAll('[data-pos-discount-total]').forEach((node) => { node.textContent = money(discount); });
        document.querySelectorAll('[data-pos-total]').forEach((node) => { node.textContent = money(finalTotal); });
        document.querySelectorAll('[data-pos-cart-count]').forEach((node) => { node.textContent = `${state.cart.reduce((count, item) => count + Number(item.quantity), 0)} items`; });
        document.querySelectorAll('[data-pos-customer-card]').forEach((node) => { node.innerHTML = customerMarkup(state.customer); });
        document.querySelectorAll('[data-pos-quick-customer]').forEach((node) => { node.classList.toggle('hidden', Boolean(state.customer)); node.innerHTML = state.customer ? '' : quickCustomerMarkup(); });
        document.querySelectorAll('[data-pos-suggestions]').forEach((node) => { node.innerHTML = suggestionMarkup(); });
        document.querySelectorAll('[data-pos-payment-amount]').forEach((node) => { if (!node.value || Number(node.value) < finalTotal) node.value = money(finalTotal); });
        document.querySelectorAll('[data-payment-method]').forEach((node) => node.classList.toggle('is-selected', node.dataset.paymentMethod === state.paymentMethod));
        bindCartActions(); bindProducts(); bindQuickCustomer();
    };

    const searchProducts = async (value) => {
        const url = new URL(posApp.dataset.catalogUrl, window.location.origin);
        if (value) url.searchParams.set('q', value);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const payload = await response.json();
        const markup = payload.products.map(productMarkup).join('');
        document.querySelectorAll('[data-pos-products], [data-pos-products-mobile]').forEach((node) => { node.innerHTML = markup; });
        document.querySelectorAll('[data-pos-product-count]').forEach((node) => { node.textContent = `${payload.products.length} available`; });
        bindProducts();
    };

    const lookupCustomer = async () => {
        const mobile = document.querySelector('[data-pos-customer-mobile]')?.value.trim();
        if (!mobile) return;
        document.querySelectorAll('[data-pos-customer-mobile]').forEach((input) => { input.value = mobile; });
        const url = new URL(posApp.dataset.customerUrl, window.location.origin); url.searchParams.set('mobile', mobile);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const payload = await response.json();
        state.customer = payload.customer;
        state.suggestions = payload.suggestions || {};
        render();
    };

    const submitSale = (action) => {
        if (!state.cart.length) return;
        const form = document.querySelector('[data-pos-submit]');
        form.action = action === 'hold' ? posApp.dataset.holdUrl : posApp.dataset.checkoutUrl;
        form.innerHTML = `<input type="hidden" name="_token" value="${csrf}">`;
        const append = (name, value) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value ?? ''; form.append(input); };
        append('customer_id', state.customer?.id || '');
        append('device_type', window.matchMedia('(min-width: 1024px)').matches ? 'desktop' : 'mobile');
        append('manual_discount_amount', manualDiscount());
        append('coupon_code', document.querySelector('[data-pos-coupon]')?.value || '');
        state.cart.forEach((item, index) => { append(`items[${index}][product_id]`, item.id); append(`items[${index}][quantity]`, item.quantity); append(`items[${index}][unit_price]`, item.price); });
        if (action === 'checkout') { append('payments[0][method]', state.paymentMethod); append('payments[0][amount]', document.querySelector('[data-pos-payment-amount]')?.value || total()); }
        form.submit();
    };

    document.querySelectorAll('[data-pos-scanner]').forEach((input) => {
        input.addEventListener('input', () => searchProducts(input.value));
        input.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); const match = [...document.querySelectorAll('[data-pos-product]')].map((node) => parse(node.dataset.posProduct, null)).find((product) => product?.barcode === input.value || product?.sku === input.value); if (match) addProduct(match); else searchProducts(input.value); } });
    });
    document.querySelectorAll('[data-pos-search]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-pos-scanner]')?.focus()));
    document.querySelectorAll('[data-pos-customer-search]').forEach((button) => button.addEventListener('click', lookupCustomer));
    document.querySelectorAll('[data-pos-clear]').forEach((button) => button.addEventListener('click', () => { state.cart = []; render(); }));
    document.querySelectorAll('[data-pos-discount]').forEach((input) => input.addEventListener('input', render));
    document.querySelectorAll('[data-payment-method]').forEach((button) => button.addEventListener('click', () => { state.paymentMethod = button.dataset.paymentMethod; render(); }));
    document.querySelectorAll('[data-pos-hold]').forEach((button) => button.addEventListener('click', () => submitSale('hold')));
    document.querySelectorAll('[data-pos-checkout]').forEach((button) => button.addEventListener('click', () => submitSale('checkout')));
    document.querySelectorAll('[data-pos-open-cart]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-pos-cart-drawer]')?.classList.remove('hidden')));
    document.querySelectorAll('[data-pos-close-cart]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-pos-cart-drawer]')?.classList.add('hidden')));
    document.querySelectorAll('[data-pos-mobile-tab]').forEach((button) => button.addEventListener('click', () => { document.querySelectorAll('[data-pos-mobile-tab]').forEach((tab) => tab.classList.toggle('is-active', tab === button)); document.querySelectorAll('[data-pos-mobile-pane]').forEach((pane) => pane.classList.toggle('hidden', pane.dataset.posMobilePane !== button.dataset.posMobileTab)); }));
    if ('serviceWorker' in navigator && window.location.pathname.startsWith('/pos')) navigator.serviceWorker.register('/pos-sw.js').catch(() => {});
    bindProducts();
    render();
});
