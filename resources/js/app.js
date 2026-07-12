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
});
