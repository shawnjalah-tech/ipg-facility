@push('scripts')
<script src="{{ asset('assets/libs/sweetalert2/sweetalert2.all.min.js') }}"></script>
<script>
(function () {
    // ---------- Reject: prompt for reason, then submit ----------
    document.querySelectorAll('form.reject-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var label = form.dataset.name || 'this user';

            Swal.fire({
                titleText: 'Reject booking for ' + label + '?',
                input: 'textarea',
                inputLabel: 'Reason for rejection (required)',
                inputPlaceholder: 'e.g. Facility under maintenance, dates clash with an event...',
                inputAttributes: {
                    'maxlength': 1000,
                    'rows': 3,
                },
                showCancelButton: true,
                confirmButtonText: 'Reject Booking',
                cancelButtonText: 'Back',
                confirmButtonColor: '#ec536c',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                inputValidator: function (value) {
                    if (!value || value.trim().length < 5) {
                        return 'Please provide a reason (at least 5 characters).';
                    }
                    if (value.length > 1000) {
                        return 'Reason is too long (max 1000 characters).';
                    }
                    return null;
                },
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.querySelector('input[name="admin_remarks"]').value = result.value.trim();
                    form.submit();
                }
            });
        });
    });

    // ---------- Approve: quick confirm ----------
    document.querySelectorAll('form[action*="/approve"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                titleText: 'Approve this booking?',
                text: 'The staff member will be notified and the slot will be reserved.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, approve',
                cancelButtonText: 'Back',
                confirmButtonColor: '#58db83',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // ---------- Cancel (admin override) ----------
    document.querySelectorAll('form.cancel-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var label = form.dataset.name || 'this booking';
            Swal.fire({
                titleText: 'Cancel booking for "' + label + '"?',
                text: 'The slot will be released and the booker notified.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, cancel it',
                cancelButtonText: 'Back',
                confirmButtonColor: '#f5b225',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // ---------- View remarks on any booking ----------
    document.querySelectorAll('.remarks-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            Swal.fire({
                titleText: 'Admin Remarks',
                text: btn.dataset.remarks,
                icon: 'info',
                confirmButtonColor: '#3eb7ba',
                confirmButtonText: 'Close',
            });
        });
    });
})();
</script>
@endpush