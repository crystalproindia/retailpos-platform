@push('scripts')
    <script>
        document.querySelectorAll('[data-purchase-item-repeater]').forEach((repeater) => {
            const list = repeater.querySelector('[data-purchase-items-list]');
            const template = repeater.querySelector('template[data-purchase-item-template]');
            let nextIndex = Number(repeater.dataset.nextIndex || list.children.length);

            repeater.querySelector('[data-add-purchase-item]')?.addEventListener('click', () => {
                list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', nextIndex++));
            });

            list.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-remove-purchase-item]');
                if (remove && list.querySelectorAll('[data-purchase-item]').length > 1) {
                    remove.closest('[data-purchase-item]')?.remove();
                }
            });
        });
    </script>
@endpush
