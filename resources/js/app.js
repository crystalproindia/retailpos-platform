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

    document.querySelectorAll('[data-pos-receipt-width]').forEach((button) => {
        button.addEventListener('click', () => {
            document.body.dataset.posReceiptWidth = button.dataset.posReceiptWidth;
            document.querySelectorAll('[data-pos-receipt-width]').forEach((option) => option.classList.toggle('is-selected', option === button));
        });
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
        manualDiscount: 0,
        paymentAmount: 0,
        categoryId: '',
    };
    const money = (value) => Number(value || 0).toFixed(2);
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const manualDiscount = () => Math.max(0, Number(state.manualDiscount || 0));
    const subtotal = () => state.cart.reduce((total, item) => total + Number(item.price) * Number(item.quantity), 0);
    const total = () => Math.max(0, subtotal() - manualDiscount());

    const productMarkup = (product) => `<button type="button" data-pos-product='${escapeHtml(JSON.stringify(product))}' class="pos-product-card text-left"><span class="pos-product-visual">${product.image ? `<img src="${escapeHtml(product.image)}" alt="" class="size-full object-cover">` : escapeHtml(product.name).slice(0, 1)}</span><span class="mt-3 flex items-start justify-between gap-2"><span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900">${escapeHtml(product.name)}</span><span class="mt-1 block truncate text-xs text-slate-500">${escapeHtml(product.brand || product.category || 'Product')}</span></span><span class="shrink-0 text-sm font-bold text-teal-700">${money(product.price)}</span></span><span class="mt-2 flex items-center justify-between gap-2 text-xs"><span class="truncate text-slate-400">${escapeHtml(product.sku || '')}</span>${product.track_inventory ? `<span class="${product.low_stock ? 'text-amber-700' : 'text-emerald-700'} font-semibold">${Number(product.available_stock || 0)} in stock</span>` : ''}</span></button>`;

    const showFeedback = (message, type = 'success') => {
        document.querySelectorAll('[data-pos-feedback]').forEach((node) => {
            node.textContent = message;
            node.classList.toggle('is-error', type === 'error');
            node.classList.remove('hidden');
            window.clearTimeout(Number(node.dataset.timeout));
            node.dataset.timeout = String(window.setTimeout(() => node.classList.add('hidden'), 2200));
        });
    };

    const focusScanner = () => window.setTimeout(() => [...document.querySelectorAll('[data-pos-scanner]')].find((input) => input.offsetParent !== null)?.focus(), 30);

    const bindProducts = (scope = document) => {
        scope.querySelectorAll('[data-pos-product]').forEach((button) => {
            if (button.dataset.posBound) return;
            button.dataset.posBound = 'true';
            button.addEventListener('click', () => addProduct(parse(button.dataset.posProduct, null)));
        });
    };

    const addProduct = (product) => {
        if (!product) return;
        if (product.track_inventory && Number(product.available_stock) <= 0) {
            showFeedback(`${product.name} is out of stock.`, 'error');
            return;
        }
        const existing = state.cart.find((item) => Number(item.id) === Number(product.id));
        if (existing && product.track_inventory && Number(product.available_stock) > 0 && Number(existing.quantity) >= Number(product.available_stock)) {
            showFeedback(`Only ${product.available_stock} ${product.name} available.`, 'error');
            return;
        }
        if (existing) existing.quantity = Number(existing.quantity) + 1;
        else state.cart.push({ ...product, quantity: 1 });
        render();
        document.querySelectorAll('[data-pos-product]').forEach((button) => {
            if (Number(parse(button.dataset.posProduct, {})?.id) === Number(product.id)) {
                button.classList.add('is-added');
                window.setTimeout(() => button.classList.remove('is-added'), 450);
            }
        });
        showFeedback(`${product.name} added to the bill.`);
        focusScanner();
    };

    const changeQuantity = (id, amount) => {
        const item = state.cart.find((cartItem) => Number(cartItem.id) === Number(id));
        if (!item) return;
        if (amount > 0 && item.track_inventory && Number(item.available_stock) > 0 && Number(item.quantity) >= Number(item.available_stock)) {
            showFeedback(`Only ${item.available_stock} ${item.name} available.`, 'error');
            return;
        }
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
        scope.querySelectorAll('[data-pos-remove]').forEach((button) => {
            if (button.dataset.posBound) return;
            button.dataset.posBound = 'true';
            button.addEventListener('click', () => { state.cart = state.cart.filter((item) => Number(item.id) !== Number(button.dataset.posRemove)); render(); focusScanner(); });
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
                const mobile = [...document.querySelectorAll('[data-pos-customer-mobile]')].map((input) => input.value.trim()).find(Boolean);
                const name = button.closest('[data-pos-quick-customer]')?.querySelector('[data-pos-quick-name]')?.value.trim();
                if (!mobile) return;
                const response = await fetch(posApp.dataset.quickCustomerUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ mobile, name }) });
                if (!response.ok) { showFeedback('Customer could not be created. Check the number and try again.', 'error'); return; }
                const payload = await response.json();
                state.customer = payload.customer;
                state.suggestions = payload.suggestions || {};
                render();
                showFeedback(`${payload.customer.name} added as a customer.`);
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
        const cartMarkup = state.cart.map((item) => `<div class="rounded-xl border border-slate-100 bg-white p-3 shadow-sm"><div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-xs font-bold text-slate-600">${escapeHtml(item.name).slice(0, 1)}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">${escapeHtml(item.name)}</p><p class="mt-0.5 truncate text-xs text-slate-500">${escapeHtml(item.sku || '')} · ${money(item.price)} each</p></div><button type="button" data-pos-remove="${item.id}" class="grid size-7 place-items-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-700" aria-label="Remove ${escapeHtml(item.name)}">×</button></div><div class="mt-2 flex items-center justify-between"><div class="flex items-center gap-1"><button type="button" data-pos-quantity="-1" data-product-id="${item.id}" class="grid size-8 place-items-center rounded-md bg-slate-100 text-slate-600" aria-label="Reduce quantity">−</button><span class="w-8 text-center text-sm font-bold">${item.quantity}</span><button type="button" data-pos-quantity="1" data-product-id="${item.id}" class="grid size-8 place-items-center rounded-md bg-slate-100 text-slate-600" aria-label="Increase quantity">+</button></div><div class="text-right"><p class="text-xs text-slate-400">Tax 0.00</p><p class="text-sm font-bold text-slate-900">${money(Number(item.price) * Number(item.quantity))}</p></div></div></div>`).join('');
        document.querySelectorAll('[data-pos-cart-items]').forEach((node) => { node.innerHTML = cartMarkup; });
        document.querySelectorAll('[data-pos-empty-cart]').forEach((node) => node.classList.toggle('hidden', state.cart.length > 0));
        document.querySelectorAll('[data-pos-subtotal]').forEach((node) => { node.textContent = money(sub); });
        document.querySelectorAll('[data-pos-discount-total]').forEach((node) => { node.textContent = money(discount); });
        document.querySelectorAll('[data-pos-total]').forEach((node) => { node.textContent = money(finalTotal); });
        document.querySelectorAll('[data-pos-cart-count]').forEach((node) => { node.textContent = `${state.cart.reduce((count, item) => count + Number(item.quantity), 0)} items`; });
        document.querySelectorAll('[data-pos-customer-card]').forEach((node) => { node.innerHTML = customerMarkup(state.customer); });
        document.querySelectorAll('[data-pos-quick-customer]').forEach((node) => { node.classList.toggle('hidden', Boolean(state.customer)); node.innerHTML = state.customer ? '' : quickCustomerMarkup(); });
        document.querySelectorAll('[data-pos-suggestions]').forEach((node) => { node.innerHTML = suggestionMarkup(); });
        if (!state.paymentAmount || Number(state.paymentAmount) < finalTotal) state.paymentAmount = money(finalTotal);
        document.querySelectorAll('[data-pos-payment-amount]').forEach((node) => { if (document.activeElement !== node) node.value = state.paymentAmount; });
        const paid = Math.max(0, Number(state.paymentAmount || 0));
        document.querySelectorAll('[data-pos-paid]').forEach((node) => { node.textContent = money(paid); });
        document.querySelectorAll('[data-pos-change]').forEach((node) => { node.textContent = money(Math.max(0, paid - finalTotal)); });
        document.querySelectorAll('[data-payment-method]').forEach((node) => node.classList.toggle('is-selected', node.dataset.paymentMethod === state.paymentMethod));
        bindCartActions(); bindProducts(); bindQuickCustomer();
    };

    const filterProducts = () => {
        document.querySelectorAll('[data-pos-products] [data-pos-product], [data-pos-products-mobile] [data-pos-product]').forEach((node) => {
            const product = parse(node.dataset.posProduct, {});
            node.classList.toggle('hidden', Boolean(state.categoryId) && String(product.category_id) !== String(state.categoryId));
        });
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
        filterProducts();
    };

    const lookupCustomer = async () => {
        const mobile = [...document.querySelectorAll('[data-pos-customer-mobile]')].map((input) => input.value.trim()).find(Boolean);
        if (!mobile) return;
        document.querySelectorAll('[data-pos-customer-mobile]').forEach((input) => { input.value = mobile; });
        const url = new URL(posApp.dataset.customerUrl, window.location.origin); url.searchParams.set('mobile', mobile);
        document.querySelectorAll('[data-pos-customer-loading]').forEach((node) => node.classList.remove('hidden'));
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        document.querySelectorAll('[data-pos-customer-loading]').forEach((node) => node.classList.add('hidden'));
        if (!response.ok) { showFeedback('Customer lookup is unavailable. Try again.', 'error'); return; }
        const payload = await response.json();
        state.customer = payload.customer;
        state.suggestions = payload.suggestions || {};
        render();
        showFeedback(payload.customer ? `${payload.customer.name} selected.` : 'No customer found. Add them in one step.');
    };

    const submitSale = (action) => {
        if (!state.cart.length) return;
        const form = document.querySelector('[data-pos-submit]');
        form.action = action === 'hold' ? posApp.dataset.holdUrl : posApp.dataset.checkoutUrl;
        form.innerHTML = `<input type="hidden" name="_token" value="${csrf}">`;
        const append = (name, value) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value ?? ''; form.append(input); };
        append('customer_id', state.customer?.id || '');
        append('device_type', posApp.dataset.posMode === 'mobile' || window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop');
        append('manual_discount_amount', manualDiscount());
        append('coupon_code', document.querySelector('[data-pos-coupon]')?.value || '');
        state.cart.forEach((item, index) => { append(`items[${index}][product_id]`, item.id); append(`items[${index}][quantity]`, item.quantity); append(`items[${index}][unit_price]`, item.price); });
        if (action === 'checkout') { append('payments[0][method]', state.paymentMethod); append('payments[0][amount]', state.paymentAmount || total()); append('payments[0][reference]', [...document.querySelectorAll('[data-pos-payment-reference]')].map((input) => input.value.trim()).find(Boolean) || ''); }
        form.submit();
    };

    document.querySelectorAll('[data-pos-scanner]').forEach((input) => {
        input.addEventListener('input', () => searchProducts(input.value));
        input.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); const match = [...document.querySelectorAll('[data-pos-product]')].map((node) => parse(node.dataset.posProduct, null)).find((product) => product?.barcode === input.value || product?.sku === input.value); if (match) { addProduct(match); input.value = ''; } else { searchProducts(input.value); showFeedback('No matching barcode or SKU found.', 'error'); } } });
    });
    document.querySelectorAll('[data-pos-search]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-pos-scanner]')?.focus()));
    document.querySelectorAll('[data-pos-customer-search]').forEach((button) => button.addEventListener('click', lookupCustomer));
    document.querySelectorAll('[data-pos-clear]').forEach((button) => button.addEventListener('click', () => { state.cart = []; render(); }));
    document.querySelectorAll('[data-pos-discount]').forEach((input) => input.addEventListener('input', () => { state.manualDiscount = input.value; document.querySelectorAll('[data-pos-discount]').forEach((other) => { if (other !== input) other.value = input.value; }); render(); }));
    document.querySelectorAll('[data-pos-payment-amount]').forEach((input) => input.addEventListener('input', () => { state.paymentAmount = input.value; document.querySelectorAll('[data-pos-payment-amount]').forEach((other) => { if (other !== input) other.value = input.value; }); render(); }));
    document.querySelectorAll('[data-payment-method]').forEach((button) => button.addEventListener('click', () => { state.paymentMethod = button.dataset.paymentMethod; render(); }));
    document.querySelectorAll('[data-pos-hold]').forEach((button) => button.addEventListener('click', () => submitSale('hold')));
    document.querySelectorAll('[data-pos-checkout]').forEach((button) => button.addEventListener('click', () => submitSale('checkout')));
    document.querySelectorAll('[data-pos-open-cart]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-pos-cart-drawer]')?.classList.remove('hidden')));
    document.querySelectorAll('[data-pos-close-cart]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-pos-cart-drawer]')?.classList.add('hidden')));
    document.querySelectorAll('[data-pos-mobile-tab]').forEach((button) => button.addEventListener('click', () => { document.querySelectorAll('[data-pos-mobile-tab]').forEach((tab) => tab.classList.toggle('is-active', tab === button)); document.querySelectorAll('[data-pos-mobile-pane]').forEach((pane) => pane.classList.toggle('hidden', pane.dataset.posMobilePane !== button.dataset.posMobileTab)); }));
    document.querySelectorAll('[data-pos-category]').forEach((button) => button.addEventListener('click', () => { state.categoryId = button.dataset.posCategory; document.querySelectorAll('[data-pos-category]').forEach((category) => category.classList.toggle('is-active', category.dataset.posCategory === state.categoryId)); filterProducts(); }));
    document.querySelectorAll('[data-pos-payment-foundation], [data-pos-split-foundation]').forEach((button) => button.addEventListener('click', () => showFeedback(button.title || 'This payment option is prepared for a later POS payment workflow.')));
    document.querySelectorAll('[data-pos-fullscreen]').forEach((button) => button.addEventListener('click', async () => { try { if (document.fullscreenElement) await document.exitFullscreen(); else await document.documentElement.requestFullscreen?.(); } catch { showFeedback('Fullscreen is not available in this browser.', 'error'); } }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'F2' || (event.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName))) { event.preventDefault(); focusScanner(); }
        if (event.key === 'F4') { event.preventDefault(); [...document.querySelectorAll('[data-pos-customer-mobile]')].find((input) => input.offsetParent !== null)?.focus(); }
        if (event.key === 'F8') { event.preventDefault(); submitSale('hold'); }
        if (event.key === 'F9') { event.preventDefault(); submitSale('checkout'); }
        if (event.key === 'Escape') { document.querySelectorAll('[data-pos-mobile-pane]').forEach((pane) => pane.classList.toggle('hidden', pane.dataset.posMobilePane !== 'products')); document.querySelectorAll('[data-pos-mobile-tab]').forEach((tab) => tab.classList.toggle('is-active', tab.dataset.posMobileTab === 'products')); }
    });
    const clock = () => document.querySelectorAll('[data-pos-clock]').forEach((node) => { node.textContent = new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date()); });
    clock(); window.setInterval(clock, 30000);
    if ('serviceWorker' in navigator && window.location.pathname.startsWith('/pos')) navigator.serviceWorker.register('/pos-sw.js').catch(() => {});
    bindProducts();
    render();
    filterProducts();
    focusScanner();
});
