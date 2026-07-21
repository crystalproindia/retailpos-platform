import { createPosOfflineStore } from './pos-offline';

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

    const sidebarOpenButtons = [...document.querySelectorAll('[data-sidebar-open]')];
    const sidebarCloseButtons = [...document.querySelectorAll('[data-sidebar-close], [data-sidebar-overlay]')];
    const sidebar = document.querySelector('[data-sidebar]');
    const mobileNavigation = window.matchMedia('(max-width: 1023px)');
    let sidebarTrigger = null;

    const setSidebarExpanded = (expanded) => {
        sidebarOpenButtons.forEach((button) => button.setAttribute('aria-expanded', String(expanded)));
    };

    const closeSidebar = ({ restoreFocus = true } = {}) => {
        const wasOpen = body.classList.contains('sidebar-mobile-open');

        body.classList.remove('sidebar-mobile-open');
        setSidebarExpanded(false);

        if (wasOpen && restoreFocus && sidebarTrigger instanceof HTMLElement) {
            sidebarTrigger.focus();
        }

        sidebarTrigger = null;
    };

    const openSidebar = (trigger) => {
        if (!mobileNavigation.matches) {
            return;
        }

        sidebarTrigger = trigger;
        body.classList.add('sidebar-mobile-open');
        setSidebarExpanded(true);

        window.requestAnimationFrame(() => document.querySelector('[data-sidebar-close]')?.focus());
    };

    sidebarOpenButtons.forEach((button) => {
        button.addEventListener('click', () => openSidebar(button));
    });

    sidebarCloseButtons.forEach((button) => {
        button.addEventListener('click', () => closeSidebar());
    });

    sidebar?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileNavigation.matches) {
                closeSidebar({ restoreFocus: false });
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileNavigation.matches) {
            closeSidebar();
        }
    });

    mobileNavigation.addEventListener('change', (event) => {
        if (!event.matches) {
            closeSidebar({ restoreFocus: false });
        }
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

    const updateContentItemFields = (container) => {
        const type = container.querySelector('[data-content-section-type]')?.value;
        const group = type === 'faq' ? 'faq' : type === 'testimonials' ? 'testimonials' : type === 'stats' ? 'stats' : 'standard';

        container.querySelectorAll('[data-content-item]').forEach((item) => {
            item.querySelectorAll('[data-item-fields]').forEach((fields) => {
                fields.classList.toggle('hidden', fields.dataset.itemFields !== group);
            });
        });
    };

    document.querySelectorAll('[data-repeatable-items]').forEach((container) => {
        const list = container.querySelector('[data-items-list]');
        const template = container.querySelector('[data-item-template]');
        const form = container.closest('form');

        if (!list || !template || !form) return;

        container.querySelector('[data-add-item]')?.addEventListener('click', () => {
            const index = `${Date.now()}${list.children.length}`;
            list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
            updateContentItemFields(form);
        });

        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-item]');
            if (button) button.closest('[data-content-item]')?.remove();
        });

        form.querySelector('[data-content-section-type]')?.addEventListener('change', () => updateContentItemFields(form));
        updateContentItemFields(form);
    });

    document.querySelectorAll('[data-content-preview]').forEach((preview) => {
        const form = preview.closest('form');
        if (!form) return;

        const update = () => {
            const value = (name, fallback) => form.querySelector(`[name="${name}"]`)?.value.trim() || fallback;
            preview.querySelector('[data-preview-eyebrow]').textContent = value('eyebrow', 'Optional small heading');
            preview.querySelector('[data-preview-title]').textContent = value('title', 'Your section title');
            preview.querySelector('[data-preview-subtitle]').textContent = value('subtitle', 'Supporting text will appear here.');
            preview.querySelector('[data-preview-button]').textContent = value('primary_cta_label', 'Primary button');
        };

        form.querySelectorAll('[name="eyebrow"], [name="title"], [name="subtitle"], [name="primary_cta_label"]').forEach((input) => input.addEventListener('input', update));
        update();
    });

    document.querySelectorAll('[data-demo-schedule-form]').forEach((form) => {
        const meetingMode = form.querySelector('[data-demo-meeting-mode]');
        const googleMeet = form.querySelector('[data-google-meet-checkbox]');

        if (!meetingMode || !googleMeet) {
            return;
        }

        const updateGoogleMeetAvailability = () => {
            const enabled = meetingMode.value === 'google_meet_later';

            googleMeet.disabled = !enabled;
            if (!enabled) {
                googleMeet.checked = false;
            }
        };

        meetingMode.addEventListener('change', updateGoogleMeetAvailability);
        updateGoogleMeetAvailability();
    });

    const pipelineBoard = document.querySelector('[data-pipeline-board]');

    if (pipelineBoard) {
        const feedback = pipelineBoard.querySelector('[data-pipeline-feedback]');
        let draggedCard = null;
        let originCards = null;
        let originNextSibling = null;

        const showPipelineFeedback = (message, isError = false) => {
            if (!feedback) return;

            feedback.textContent = message;
            feedback.classList.remove('hidden', 'border-emerald-200', 'text-emerald-800', 'dark:border-emerald-950', 'dark:text-emerald-200', 'border-rose-200', 'text-rose-800', 'dark:border-rose-950', 'dark:text-rose-200');
            feedback.classList.add(...(isError
                ? ['border-rose-200', 'text-rose-800', 'dark:border-rose-950', 'dark:text-rose-200']
                : ['border-emerald-200', 'text-emerald-800', 'dark:border-emerald-950', 'dark:text-emerald-200']));

            window.clearTimeout(Number(feedback.dataset.timeout));
            feedback.dataset.timeout = String(window.setTimeout(() => feedback.classList.add('hidden'), 3200));
        };

        const refreshColumn = (cards) => {
            const column = cards.closest('[data-pipeline-dropzone]');
            const count = column?.querySelector('[data-pipeline-count]');
            const cardCount = cards.querySelectorAll('[data-pipeline-card]').length;

            if (count) count.textContent = String(cardCount);

            let empty = cards.querySelector('[data-pipeline-empty]');
            if (cardCount === 0 && !empty) {
                empty = document.createElement('p');
                empty.dataset.pipelineEmpty = 'true';
                empty.className = 'rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400';
                empty.textContent = 'Drop a deal here or use Move stage.';
                cards.appendChild(empty);
            }
            if (cardCount > 0 && empty) empty.remove();
        };

        const restoreCard = () => {
            if (!draggedCard || !originCards) return;

            if (originNextSibling?.parentElement === originCards) {
                originCards.insertBefore(draggedCard, originNextSibling);
            } else {
                originCards.appendChild(draggedCard);
            }
        };

        pipelineBoard.querySelectorAll('[data-pipeline-card]').forEach((card) => {
            card.addEventListener('dragstart', (event) => {
                draggedCard = card;
                originCards = card.closest('[data-pipeline-cards]');
                originNextSibling = card.nextElementSibling;
                card.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', card.dataset.leadId || '');
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('is-dragging');
                pipelineBoard.querySelectorAll('[data-pipeline-dropzone]').forEach((zone) => zone.classList.remove('is-drag-over'));
            });
        });

        pipelineBoard.querySelectorAll('[data-pipeline-dropzone]').forEach((zone) => {
            zone.addEventListener('dragover', (event) => {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                zone.classList.add('is-drag-over');
            });

            zone.addEventListener('dragleave', (event) => {
                if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-drag-over');
            });

            zone.addEventListener('drop', async (event) => {
                event.preventDefault();
                zone.classList.remove('is-drag-over');
                if (!draggedCard || !originCards) return;

                const targetStage = zone.dataset.stage;
                const targetCards = zone.querySelector('[data-pipeline-cards]');
                if (!targetStage || !targetCards || targetStage === draggedCard.dataset.stage) return;

                targetCards.appendChild(draggedCard);
                draggedCard.classList.add('is-moving');
                refreshColumn(originCards);
                refreshColumn(targetCards);

                try {
                    const response = await fetch(pipelineBoard.dataset.moveUrl.replace('__lead__', draggedCard.dataset.leadId), {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': pipelineBoard.dataset.csrf,
                        },
                        body: JSON.stringify({ target_stage: targetStage }),
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'The pipeline move could not be saved.');

                    draggedCard.dataset.stage = targetStage;
                    showPipelineFeedback(result.message || 'Pipeline updated.');
                    window.setTimeout(() => window.location.reload(), 650);
                } catch (error) {
                    restoreCard();
                    refreshColumn(originCards);
                    refreshColumn(targetCards);
                    showPipelineFeedback(error.message || 'The pipeline move could not be saved. Your card was restored.', true);
                } finally {
                    draggedCard?.classList.remove('is-moving');
                }
            });
        });
    }

    document.querySelectorAll('[data-quotation-form]').forEach((form) => {
        const items = form.querySelector('[data-quotation-items]');
        const template = form.querySelector('[data-quotation-item-template]');
        const addButton = form.querySelector('[data-quotation-add-item]');
        if (!items || !template || !addButton) return;
        const number = (value) => Number.parseFloat(value || '0') || 0;
        const renderTotals = () => {
            let subtotal = 0; let discount = 0; let tax = 0;
            items.querySelectorAll('[data-quotation-item]').forEach((item) => {
                const gross = number(item.querySelector('[data-quotation-quantity]')?.value) * number(item.querySelector('[data-quotation-unit-price]')?.value);
                const itemDiscount = Math.min(number(item.querySelector('[data-quotation-discount]')?.value), gross);
                subtotal += gross; discount += itemDiscount; tax += (gross - itemDiscount) * number(item.querySelector('[data-quotation-tax-rate]')?.value) / 100;
            });
            const put = (selector, value) => form.querySelectorAll(selector).forEach((node) => { node.textContent = value.toFixed(2); });
            put('[data-quotation-subtotal]', subtotal); put('[data-quotation-discount-total]', discount); put('[data-quotation-tax-total]', tax); put('[data-quotation-grand-total]', subtotal - discount + tax);
        };
        const bindItem = (item) => {
            item.querySelectorAll('input').forEach((input) => input.addEventListener('input', renderTotals));
            item.querySelector('[data-quotation-remove-item]')?.addEventListener('click', () => { if (items.querySelectorAll('[data-quotation-item]').length > 1) { item.remove(); renderTotals(); } });
        };
        items.querySelectorAll('[data-quotation-item]').forEach(bindItem);
        addButton.addEventListener('click', () => { const index = items.querySelectorAll('[data-quotation-item]').length; const fragment = template.content.cloneNode(true); fragment.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replaceAll('__INDEX__', index); }); items.appendChild(fragment); bindItem(items.lastElementChild); renderTotals(); });
        renderTotals();
    });

    document.addEventListener('click', () => closeDropdowns());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
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
    const offlineStore = createPosOfflineStore({ bootstrapUrl: posApp.dataset.offlineBootstrapUrl, syncUrl: posApp.dataset.offlineSyncUrl, csrf });
    const parse = (value, fallback) => {
        try { return JSON.parse(value || ''); } catch { return fallback; }
    };
    const state = {
        cart: parse(posApp.dataset.initialCart, []),
        customer: parse(posApp.dataset.initialCustomer, null),
        suggestions: {},
        paymentMethod: 'cash',
        paymentMode: 'cash',
        manualDiscount: 0,
        paymentAmount: 0,
        splitPayments: [],
        categoryId: 'all',
        recentlyAdded: [],
        popularProductIds: parse(posApp.dataset.popularProducts, []),
        isSubmitting: false,
        online: navigator.onLine,
        offlineSettings: {},
    };
    const money = (value) => Number(value || 0).toFixed(2);
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const manualDiscount = () => Math.max(0, Number(state.manualDiscount || 0));
    const subtotal = () => state.cart.reduce((total, item) => total + Number(item.price) * Number(item.quantity), 0);
    const total = () => Math.max(0, subtotal() - manualDiscount());
    const splitPaid = () => state.splitPayments.reduce((sum, payment) => sum + Math.max(0, Number(payment.amount || 0)), 0);
    const paymentEntries = () => state.paymentMode === 'split'
        ? state.splitPayments.map((payment) => ({ method: payment.method, amount: Number(payment.amount || 0), reference: payment.reference || '' }))
        : [{ method: state.paymentMethod, amount: Number(state.paymentAmount || 0), reference: [...document.querySelectorAll('[data-pos-payment-reference]')].map((input) => input.value.trim()).find(Boolean) || '' }];

    const productMarkup = (product) => `<button type="button" data-pos-product='${escapeHtml(JSON.stringify(product))}' class="pos-product-card pos-compact-product-card text-left"><span class="pos-compact-visual">${product.image ? `<img src="${escapeHtml(product.image)}" alt="" class="size-full object-cover">` : escapeHtml(product.name).slice(0, 1)}</span><span class="mt-2 block truncate text-sm font-semibold text-slate-900">${escapeHtml(product.name)}</span><span class="mt-0.5 block truncate text-xs text-slate-500">${escapeHtml(product.sku || '')}</span><span class="mt-2 flex items-center justify-between gap-2"><span class="text-sm font-bold text-teal-700">${money(product.price)}</span>${product.track_inventory ? `<span class="pos-stock-badge ${product.low_stock ? 'is-low' : ''}">${Number(product.available_stock || 0)}</span>` : ''}</span></button>`;

    const showFeedback = (message, type = 'success') => {
        document.querySelectorAll('[data-pos-feedback]').forEach((node) => {
            node.textContent = message;
            node.classList.toggle('is-error', type === 'error');
            node.classList.remove('hidden');
            window.clearTimeout(Number(node.dataset.timeout));
            node.dataset.timeout = String(window.setTimeout(() => node.classList.add('hidden'), 2200));
        });
    };

    const updateConnectivity = async (message = null) => {
        const pending = await offlineStore.pendingCount();
        document.querySelectorAll('[data-pos-connectivity]').forEach((node) => { node.textContent = state.online ? 'Online' : 'Offline mode'; node.closest('[data-pos-sync]')?.classList.toggle('is-offline', !state.online); });
        document.querySelectorAll('[data-pos-pending-sync]').forEach((node) => { node.textContent = pending; node.classList.toggle('hidden', !pending); });
        const modalHeader = paymentModal?.querySelector('header');
        if (modalHeader && !modalHeader.querySelector('[data-pos-offline-label]')) modalHeader.querySelector('div')?.insertAdjacentHTML('beforeend', ' <span data-pos-offline-label class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Offline mode</span>');
        document.querySelectorAll('[data-pos-offline-label]').forEach((node) => node.classList.toggle('hidden', state.online));
        if (message) showFeedback(message, state.online ? 'success' : 'error');
    };

    const refreshOfflineBootstrap = async () => {
        try {
            const snapshot = await offlineStore.bootstrap();
            state.offlineSettings = snapshot?.settings || {};
            await updateConnectivity();
        } catch { await updateConnectivity('Offline cache is not ready yet. Connect once to prepare POS offline mode.'); }
    };

    const syncOfflineBills = async () => {
        if (!state.online) { await updateConnectivity('Offline bills will sync automatically when internet returns.'); return; }
        try {
            const result = await offlineStore.sync();
            await updateConnectivity(result.results?.length ? 'Offline bills synchronized.' : null);
        } catch { await updateConnectivity('Offline bill sync failed. Pending bills remain safely queued.'); }
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
        state.recentlyAdded = [Number(product.id), ...state.recentlyAdded.filter((id) => id !== Number(product.id))].slice(0, 12);
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
                if (!state.online) {
                    state.customer = { id: null, name: name || 'Offline customer', mobile, group: 'Pending sync', loyalty_points: 0, wallet_balance: 0, last_purchase_at: null, retention_note: 'Customer will be created or merged during sync.' };
                    state.suggestions = {};
                    render();
                    showFeedback('Customer saved with this offline bill and will be synchronized later.');
                    return;
                }
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
        const sections = Object.entries(state.suggestions || {}).filter(([, products]) => products?.length).map(([key, products]) => `<section class="mb-4"><h3 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">${labels[key]}</h3><div class="flex gap-2 overflow-x-auto pb-1">${products.map((product) => `<button type="button" data-pos-product='${escapeHtml(JSON.stringify(product))}' class="min-w-36 rounded-xl border border-teal-100 bg-white p-2.5 text-left text-xs shadow-sm"><span class="block truncate font-semibold">${escapeHtml(product.name)}</span><span class="mt-2 flex items-center justify-between"><span class="font-bold text-teal-700">${money(product.price)}</span><span class="rounded-md bg-teal-50 px-1.5 py-0.5 font-bold text-teal-700">Add</span></span></button>`).join('')}</div></section>`).join('');
        return sections || '<p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-500">Suggestions will improve as purchase history grows.</p>';
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
        if (!state.paymentAmount) state.paymentAmount = money(finalTotal);
        document.querySelectorAll('[data-pos-payment-amount]').forEach((node) => { if (document.activeElement !== node) node.value = state.paymentAmount; });
        const isSplit = state.paymentMode === 'split';
        const paid = isSplit ? splitPaid() : Math.max(0, Number(state.paymentAmount || 0));
        document.querySelectorAll('[data-pos-paid]').forEach((node) => { node.textContent = money(paid); });
        document.querySelectorAll('[data-pos-change]').forEach((node) => { node.textContent = money(isSplit ? 0 : Math.max(0, paid - finalTotal)); });
        document.querySelectorAll('[data-pos-remaining]').forEach((node) => { node.textContent = money(Math.max(0, finalTotal - paid)); });
        document.querySelectorAll('[data-payment-method]').forEach((node) => node.classList.toggle('is-selected', node.dataset.paymentMethod === state.paymentMethod));
        document.querySelectorAll('[data-pos-split-payment]').forEach((button) => button.classList.toggle('is-selected', isSplit));
        document.querySelectorAll('[data-pos-wallet-foundation], [data-pos-credit-foundation]').forEach((button) => { button.disabled = !state.customer; });
        document.querySelectorAll('[data-pos-standard-payment], [data-pos-standard-summary]').forEach((node) => node.classList.toggle('hidden', isSplit));
        document.querySelectorAll('[data-pos-split-payment-panel]').forEach((node) => node.classList.toggle('hidden', !isSplit));
        document.querySelectorAll('[data-pos-split-rows]').forEach((node) => { node.innerHTML = isSplit ? splitRowsMarkup() : ''; });
        const modeNotes = { cash: 'Cash is ready for this bill. Enter the cash given to calculate change.', upi: 'Manual UPI reference only. Gateway integration is not connected yet.', card: 'Manual card reference only. Card terminal integration is not connected yet.', split: 'Use Cash, Card, or UPI rows. Their total must equal the bill total.' };
        document.querySelectorAll('[data-pos-payment-mode-note]').forEach((node) => { node.textContent = modeNotes[state.paymentMode] || modeNotes.cash; });
        document.querySelectorAll('[data-pos-wallet-context]').forEach((node) => { node.classList.remove('hidden'); node.textContent = state.customer ? `Wallet balance: ${money(state.customer.wallet_balance)}. Wallet settlement and credit due are not enabled in this phase.` : 'Select a customer to use wallet or credit payment foundations.'; });
        document.querySelectorAll('[data-pos-checkout]').forEach((button) => { button.textContent = state.isSubmitting ? 'Saving payment…' : (isSplit ? 'Accept split payment' : `Accept ${state.paymentMethod}`); button.disabled = state.isSubmitting; });
        bindCartActions(); bindProducts(); bindQuickCustomer(); bindSplitActions();
    };

    const splitRowsMarkup = () => state.splitPayments.map((payment, index) => `<div class="pos-split-row"><select data-pos-split-method="${index}" aria-label="Payment method"><option value="cash" ${payment.method === 'cash' ? 'selected' : ''}>Cash</option><option value="card" ${payment.method === 'card' ? 'selected' : ''}>Card</option><option value="upi" ${payment.method === 'upi' ? 'selected' : ''}>UPI</option></select><input data-pos-split-amount="${index}" type="number" min="0" step="0.01" value="${escapeHtml(payment.amount)}" aria-label="Payment amount"><input data-pos-split-reference="${index}" value="${escapeHtml(payment.reference || '')}" placeholder="Reference" aria-label="Payment reference"><button type="button" data-pos-remove-split="${index}" class="pos-split-remove" aria-label="Remove payment">×</button></div>`).join('');

    const bindSplitActions = () => {
        document.querySelectorAll('[data-pos-split-method]').forEach((input) => input.addEventListener('change', () => { state.splitPayments[Number(input.dataset.posSplitMethod)].method = input.value; clearPaymentError(); render(); }));
        document.querySelectorAll('[data-pos-split-amount]').forEach((input) => input.addEventListener('input', () => { const index = Number(input.dataset.posSplitAmount); state.splitPayments[index].amount = input.value; clearPaymentError(); render(); window.setTimeout(() => document.querySelector(`[data-pos-split-amount="${index}"]`)?.focus(), 0); }));
        document.querySelectorAll('[data-pos-split-reference]').forEach((input) => input.addEventListener('input', () => { state.splitPayments[Number(input.dataset.posSplitReference)].reference = input.value; }));
        document.querySelectorAll('[data-pos-remove-split]').forEach((button) => button.addEventListener('click', () => { if (state.splitPayments.length > 1) { state.splitPayments.splice(Number(button.dataset.posRemoveSplit), 1); render(); } }));
    };

    const filterProducts = () => {
        document.querySelectorAll('[data-pos-products] [data-pos-product], [data-pos-products-mobile] [data-pos-product]').forEach((node) => {
            const product = parse(node.dataset.posProduct, {});
            const visible = state.categoryId === 'all'
                || (state.categoryId === 'low-stock' && product.low_stock)
                || (state.categoryId === 'recent' && state.recentlyAdded.includes(Number(product.id)))
                || (state.categoryId === 'popular' && state.popularProductIds.includes(Number(product.id)))
                || (state.categoryId === 'offers' && false)
                || String(product.category_id) === String(state.categoryId);
            node.classList.toggle('hidden', !visible);
        });
        document.querySelectorAll('[data-pos-empty-products]').forEach((empty) => {
            const grid = empty.previousElementSibling;
            const visibleProducts = grid?.querySelectorAll?.('[data-pos-product]:not(.hidden)').length || 0;
            empty.classList.toggle('hidden', visibleProducts > 0);
        });
    };

    const searchProducts = async (value) => {
        if (!state.online) {
            const products = await offlineStore.products(value);
            const markup = products.map(productMarkup).join('');
            document.querySelectorAll('[data-pos-products], [data-pos-products-mobile]').forEach((node) => { node.innerHTML = markup; });
            document.querySelectorAll('[data-pos-product-count]').forEach((node) => { node.textContent = `${products.length} cached`; });
            bindProducts(); filterProducts();
            return;
        }
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
        if (!state.online) {
            state.customer = await offlineStore.customer(mobile);
            state.suggestions = {};
            render();
            showFeedback(state.customer ? `${state.customer.name} selected from offline cache.` : 'No cached customer found. A new customer can be synced later.', state.customer ? 'success' : 'error');
            return;
        }
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

    const submitSale = async (action) => {
        if (!state.cart.length) { showFeedback('Add a product before checkout.', 'error'); return; }
        if (action === 'checkout' && !state.online) {
            const allowed = { cash: state.offlineSettings.allow_offline_cash !== false, card: state.offlineSettings.allow_offline_manual_card === true, upi: state.offlineSettings.allow_offline_manual_upi === true };
            const payments = paymentEntries();
            if (payments.some((payment) => !allowed[payment.method])) { showPaymentError('This payment method requires internet in the current offline POS settings.'); return; }
            const sequence = (await offlineStore.pendingCount()) + 1;
            const offlineUuid = crypto.randomUUID();
            const offlineReference = `OFF-${offlineStore.deviceId.slice(-6).toUpperCase()}-${new Date().toISOString().slice(0, 10).replaceAll('-', '')}-${String(sequence).padStart(4, '0')}`;
            await offlineStore.queueBill({ offline_uuid: offlineUuid, offline_reference: offlineReference, offline_created_at: new Date().toISOString(), items: state.cart.map((item) => ({ product_id: item.id, quantity: item.quantity, unit_price: item.price })), payments, customer: state.customer ? { mobile: state.customer.mobile, name: state.customer.name } : null, coupon_code: document.querySelector('[data-pos-coupon]')?.value || null, notes: [...document.querySelectorAll('[data-pos-notes]')].map((input) => input.value.trim()).find(Boolean) || null });
            let offlineSuccess = paymentModal?.querySelector('[data-pos-offline-success]');
            if (!offlineSuccess && paymentModal) { paymentModal.querySelector('header')?.insertAdjacentHTML('afterend', '<p data-pos-offline-success class="mx-1 mt-4 rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"></p>'); offlineSuccess = paymentModal.querySelector('[data-pos-offline-success]'); }
            if (offlineSuccess) { offlineSuccess.textContent = `Bill ${offlineReference} saved offline. It is pending sync and will receive an official bill number after sync.`; offlineSuccess.classList.remove('hidden'); }
            state.cart = []; state.customer = null; state.suggestions = {}; state.paymentAmount = 0; state.splitPayments = []; state.isSubmitting = false;
            render(); await updateConnectivity();
            return;
        }
        const form = document.querySelector('[data-pos-submit]');
        form.action = action === 'hold' ? posApp.dataset.holdUrl : posApp.dataset.checkoutUrl;
        form.innerHTML = `<input type="hidden" name="_token" value="${csrf}">`;
        const append = (name, value) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value ?? ''; form.append(input); };
        append('customer_id', state.customer?.id || '');
        append('register_id', [...document.querySelectorAll('[data-pos-register]')].map((input) => input.value).find(Boolean) || '');
        append('device_type', posApp.dataset.posMode === 'mobile' || window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop');
        append('manual_discount_amount', manualDiscount());
        append('coupon_code', document.querySelector('[data-pos-coupon]')?.value || '');
        append('notes', [...document.querySelectorAll('[data-pos-notes]')].map((input) => input.value.trim()).find(Boolean) || '');
        state.cart.forEach((item, index) => { append(`items[${index}][product_id]`, item.id); append(`items[${index}][quantity]`, item.quantity); append(`items[${index}][unit_price]`, item.price); });
        if (action === 'checkout') { state.isSubmitting = true; render(); paymentEntries().forEach((payment, index) => { append(`payments[${index}][method]`, payment.method); append(`payments[${index}][amount]`, payment.amount); append(`payments[${index}][reference]`, payment.reference); }); }
        form.submit();
    };

    const paymentModal = document.querySelector('[data-pos-payment-modal]');
    const showPaymentError = (message) => document.querySelectorAll('[data-pos-payment-error]').forEach((node) => { node.textContent = message; node.classList.remove('hidden'); });
    const clearPaymentError = () => document.querySelectorAll('[data-pos-payment-error]').forEach((node) => node.classList.add('hidden'));
    const openPayment = () => {
        if (!state.cart.length) { showFeedback('Add a product before checkout.', 'error'); return; }
        paymentModal?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        clearPaymentError();
        render();
        window.setTimeout(() => [...document.querySelectorAll('[data-pos-payment-amount]')].find((input) => input.offsetParent !== null)?.focus(), 50);
    };
    const closePayment = () => { paymentModal?.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };

    document.querySelectorAll('[data-pos-scanner]').forEach((input) => {
        input.addEventListener('input', () => searchProducts(input.value));
        input.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); const match = [...document.querySelectorAll('[data-pos-product]')].map((node) => parse(node.dataset.posProduct, null)).find((product) => product?.barcode === input.value || product?.sku === input.value); if (match) { addProduct(match); input.value = ''; } else { searchProducts(input.value); showFeedback('No matching barcode or SKU found.', 'error'); } } });
    });
    document.querySelectorAll('[data-pos-search]').forEach((button) => button.addEventListener('click', focusScanner));
    document.querySelectorAll('[data-pos-customer-search]').forEach((button) => button.addEventListener('click', lookupCustomer));
    document.querySelectorAll('[data-pos-clear]').forEach((button) => button.addEventListener('click', () => { state.cart = []; render(); }));
    document.querySelectorAll('[data-pos-discount]').forEach((input) => input.addEventListener('input', () => { state.manualDiscount = input.value; document.querySelectorAll('[data-pos-discount]').forEach((other) => { if (other !== input) other.value = input.value; }); render(); }));
    document.querySelectorAll('[data-pos-payment-amount]').forEach((input) => input.addEventListener('input', () => { state.paymentAmount = input.value; document.querySelectorAll('[data-pos-payment-amount]').forEach((other) => { if (other !== input) other.value = input.value; }); render(); }));
    document.querySelectorAll('[data-payment-method]').forEach((button) => button.addEventListener('click', () => { state.paymentMethod = button.dataset.paymentMethod; state.paymentMode = state.paymentMethod; if (state.paymentMethod === 'cash') state.paymentAmount = money(total()); clearPaymentError(); render(); }));
    document.querySelectorAll('[data-pos-hold]').forEach((button) => button.addEventListener('click', () => submitSale('hold')));
    document.querySelectorAll('[data-pos-checkout]').forEach((button) => button.addEventListener('click', () => { if (state.isSubmitting) return; const due = total(); const paid = state.paymentMode === 'split' ? splitPaid() : Number(state.paymentAmount || 0); if (state.paymentMode === 'split' && Math.abs(paid - due) > 0.009) { showPaymentError(`Split payments must equal ${money(due)}.`); return; } if (state.paymentMode !== 'split' && paid < due) { showPaymentError('Amount received must cover the total due.'); return; } if (['upi', 'card'].includes(state.paymentMode) && Math.abs(paid - due) > 0.009) { showPaymentError(`${state.paymentMode.toUpperCase()} amount must match the total due.`); return; } submitSale('checkout'); }));
    document.querySelectorAll('[data-pos-open-payment]').forEach((button) => button.addEventListener('click', openPayment));
    document.querySelectorAll('[data-pos-close-payment]').forEach((button) => button.addEventListener('click', closePayment));
    document.querySelectorAll('[data-pos-open-cart]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-pos-cart-drawer]')?.classList.remove('hidden')));
    document.querySelectorAll('[data-pos-close-cart]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-pos-cart-drawer]')?.classList.add('hidden')));
    document.querySelectorAll('[data-pos-mobile-tab]').forEach((button) => button.addEventListener('click', () => { document.querySelectorAll('[data-pos-mobile-tab]').forEach((tab) => tab.classList.toggle('is-active', tab === button)); document.querySelectorAll('[data-pos-mobile-pane]').forEach((pane) => pane.classList.toggle('hidden', pane.dataset.posMobilePane !== button.dataset.posMobileTab)); }));
    document.querySelectorAll('[data-pos-category]').forEach((button) => button.addEventListener('click', () => { state.categoryId = button.dataset.posCategory; document.querySelectorAll('[data-pos-category]').forEach((category) => category.classList.toggle('is-active', category.dataset.posCategory === state.categoryId)); filterProducts(); }));
    const foundationNotice = (message, type = 'success') => {
        if (paymentModal && !paymentModal.classList.contains('hidden')) { showPaymentError(message); return; }
        showFeedback(message, type);
    };
    document.querySelectorAll('[data-pos-payment-foundation], [data-pos-split-foundation]').forEach((button) => button.addEventListener('click', () => foundationNotice(button.title || 'This payment option is prepared for a later POS payment workflow.')));
    document.querySelectorAll('[data-pos-wallet-foundation], [data-pos-credit-foundation]').forEach((button) => button.addEventListener('click', () => foundationNotice(state.customer ? 'This settlement option is a future POS foundation.' : 'Select a customer before using this payment foundation.', 'error')));
    document.querySelectorAll('[data-pos-split-payment]').forEach((button) => button.addEventListener('click', () => { state.paymentMode = 'split'; if (!state.splitPayments.length) state.splitPayments = [{ method: 'cash', amount: money(total()), reference: '' }]; clearPaymentError(); render(); }));
    document.querySelectorAll('[data-pos-add-split]').forEach((button) => button.addEventListener('click', () => { if (state.splitPayments.length < 4) { state.splitPayments.push({ method: 'upi', amount: 0, reference: '' }); render(); } }));
    document.querySelectorAll('[data-pos-quick-amount]').forEach((button) => button.addEventListener('click', () => { const value = button.dataset.posQuickAmount; state.paymentAmount = value === 'exact' ? money(total()) : value === 'round' ? money(Math.ceil(total() / 100) * 100) : value; render(); }));
    document.querySelectorAll('[data-pos-payment-amount], [data-pos-payment-reference]').forEach((input) => input.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); document.querySelector('[data-pos-checkout]')?.click(); } }));
    document.querySelectorAll('[data-pos-fullscreen]').forEach((button) => button.addEventListener('click', async () => { try { if (document.fullscreenElement) await document.exitFullscreen(); else await document.documentElement.requestFullscreen?.(); } catch { showFeedback('Fullscreen is not available in this browser.', 'error'); } }));
    document.querySelectorAll('[data-pos-sync]').forEach((button) => button.addEventListener('click', syncOfflineBills));
    window.addEventListener('online', async () => { state.online = true; await refreshOfflineBootstrap(); await syncOfflineBills(); await updateConnectivity('Online restored. Checking pending offline bills…'); });
    window.addEventListener('offline', async () => { state.online = false; await updateConnectivity('Offline mode active. Bills will be saved locally and synced later.'); });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'F2' || (event.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName))) { event.preventDefault(); focusScanner(); }
        if (event.key === 'F4') { event.preventDefault(); [...document.querySelectorAll('[data-pos-customer-mobile]')].find((input) => input.offsetParent !== null)?.focus(); }
        if (event.key === 'F8') { event.preventDefault(); submitSale('hold'); }
        if (event.key === 'F9') { event.preventDefault(); openPayment(); }
        if (event.key === 'Escape') { if (paymentModal && !paymentModal.classList.contains('hidden')) closePayment(); else { document.querySelectorAll('[data-pos-mobile-pane]').forEach((pane) => pane.classList.toggle('hidden', pane.dataset.posMobilePane !== 'products')); document.querySelectorAll('[data-pos-mobile-tab]').forEach((tab) => tab.classList.toggle('is-active', tab.dataset.posMobileTab === 'products')); } }
    });
    const clock = () => document.querySelectorAll('[data-pos-clock]').forEach((node) => { node.textContent = new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date()); });
    clock(); window.setInterval(clock, 30000);
    if ('serviceWorker' in navigator && window.location.pathname.startsWith('/pos')) navigator.serviceWorker.register('/pos-sw.js').catch(() => {});
    bindProducts();
    render();
    filterProducts();
    focusScanner();
    refreshOfflineBootstrap();
});
