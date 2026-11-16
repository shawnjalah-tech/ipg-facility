@push('scripts')
<script src="{{ asset('assets/libs/sweetalert2/sweetalert2.all.min.js') }}"></script>
<script>
(function () {
    function wire(selector, opts) {
        document.querySelectorAll(selector).forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var label = form.dataset.name || 'this item';
                Swal.fire({
                    titleText: opts.title(label),
                    text: opts.text,
                    icon: opts.icon || 'warning',
                    showCancelButton: true,
                    confirmButtonText: opts.confirm,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: opts.confirmColor || '#ec536c',
                    cancelButtonColor: '#6c757d',
                    reverseButtons: true,
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    }

    // Delete (red confirm)
    wire('form.delete-form', {
        titleText: function (n) { return 'Delete "' + n + '"?'; },
        text: 'This action cannot be undone.',
        confirm: 'Yes, delete it',
        confirmColor: '#ec536c',
        icon: 'warning',
    });

    // Cancel booking (amber confirm)
    wire('form.cancel-form', {
        titleText: function (n) { return 'Cancel booking "' + n + '"?'; },
        text: 'The slot will be released and other staff can book it.',
        confirm: 'Yes, cancel it',
        confirmColor: '#f5b225',
        icon: 'question',
    });
})();
</script>
@endpush