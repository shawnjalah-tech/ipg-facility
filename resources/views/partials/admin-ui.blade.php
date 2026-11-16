@push('styles')
<style>
    /* =========================================================
       Admin UI — shared components
       Scoped with .admin-* to avoid clashing with Bootstrap.
       ========================================================= */

    /* ---------- Stat pills ---------- */
    .admin-stat-pills {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .admin-stat-pill {
        flex: 1 1 140px;
        background: #fff;
        border: 1px solid #eef1f4;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        transition: box-shadow 0.15s ease, transform 0.15s ease;
    }
    .admin-stat-pill:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    }
    .admin-stat-pill .pill-icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .admin-stat-pill .pill-icon svg { display: block; }
    .admin-stat-pill .pill-content { min-width: 0; }
    .admin-stat-pill .pill-label {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6c757d;
        font-weight: 500;
        margin: 0 0 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .admin-stat-pill .pill-value {
        font-size: 1.25rem;
        font-weight: 600;
        color: #343a40;
        line-height: 1.1;
        margin: 0;
    }
    .admin-stat-pill .pill-value small {
        font-size: 0.75rem;
        font-weight: 400;
        color: #6c757d;
        margin-left: 3px;
    }

    /* Pill icon colour variants */
    .pill-icon.teal   { background: #e6f7f7; color: #2fa2a6; }
    .pill-icon.green  { background: #eafaf0; color: #37a35d; }
    .pill-icon.amber  { background: #fef6e2; color: #b57f11; }
    .pill-icon.red    { background: #fdecef; color: #c73d58; }
    .pill-icon.purple { background: #efeef8; color: #5e54a6; }
    .pill-icon.grey   { background: #f1f3f5; color: #5a6470; }

    /* ---------- Data card wrapper ---------- */
    .admin-card {
        background: #fff;
        border: 1px solid #eef1f4;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 24px;
    }
    .admin-card-body { padding: 0; }
    .admin-card-pad  { padding: 20px; }

    /* Toolbar inside a card (search, filter, bulk actions) */
    .admin-toolbar {
        padding: 16px 20px;
        border-bottom: 1px solid #eef1f4;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .admin-toolbar-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #343a40;
        margin: 0;
    }
    .admin-toolbar-spacer { flex: 1; }

    /* ---------- Table ---------- */
    .admin-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .admin-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        font-size: 0.8125rem;
    }
    .admin-table thead th {
        background: #fafbfc;
        color: #6c757d;
        font-weight: 600;
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 12px 20px;
        border-bottom: 1px solid #eef1f4;
        text-align: left;
        white-space: nowrap;
    }
    .admin-table thead th.text-center { text-align: center; }
    .admin-table thead th.text-end    { text-align: right; }

    .admin-table tbody td {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f3f5;
        color: #5b626b;
        vertical-align: middle;
    }
    .admin-table tbody tr:last-child td { border-bottom: 0; }
    .admin-table tbody tr { transition: background 0.12s ease; }
    .admin-table tbody tr:hover { background: #fafcfd; }

    /* Row content pattern: primary line + muted secondary line */
    .row-main {
        color: #343a40;
        font-weight: 500;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .row-sub {
        color: #8a93a0;
        font-size: 0.75rem;
        line-height: 1.4;
        margin-top: 2px;
    }

    /* Clamped purpose cell */
    .purpose-cell {
        max-width: 240px;
        color: #5b626b;
    }
    .purpose-cell .purpose-text {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    /* ---------- Soft badges ---------- */
    .soft-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        white-space: nowrap;
        line-height: 1.4;
    }
    .soft-badge::before {
        content: "";
        width: 6px; height: 6px;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.9;
    }
    .soft-badge-success {
        background: #eafaf0;
        color: #2e8b52;
    }
    .soft-badge-warning {
        background: #fef6e2;
        color: #a97812;
    }
    .soft-badge-danger {
        background: #fdecef;
        color: #c73d58;
    }
    .soft-badge-secondary {
        background: #eef0f3;
        color: #5a6470;
    }
    .soft-badge-primary {
        background: #e6f7f7;
        color: #2fa2a6;
    }
    .soft-badge-info {
        background: #efeef8;
        color: #5e54a6;
    }

    /* ---------- Time chip ---------- */
    .time-chip {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 6px;
        background: #f6f8fa;
        color: #5b626b;
        font-size: 0.75rem;
        font-variant-numeric: tabular-nums;
        font-weight: 500;
    }

    /* ---------- Buttons ---------- */
    .btn-icon {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        font-size: 0.75rem;
        font-weight: 500;
        border-radius: 8px;
        border: 1px solid transparent;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
        line-height: 1.4;
        white-space: nowrap;
    }
    .btn-icon svg { flex-shrink: 0; }
    .btn-icon:hover { text-decoration: none; transform: translateY(-1px); }
    .btn-icon:active { transform: translateY(0); }

    .btn-icon-primary {
        background: linear-gradient(135deg, #3eb7ba, #2fa2a6);
        color: #fff;
        box-shadow: 0 2px 6px rgba(62, 183, 186, 0.28);
    }
    .btn-icon-primary:hover { color: #fff; box-shadow: 0 4px 12px rgba(62, 183, 186, 0.4); }

    .btn-icon-success {
        background: linear-gradient(135deg, #58db83, #3fb96a);
        color: #fff;
        box-shadow: 0 2px 6px rgba(88, 219, 131, 0.28);
    }
    .btn-icon-success:hover { color: #fff; box-shadow: 0 4px 12px rgba(88, 219, 131, 0.4); }

    .btn-icon-danger {
        background: linear-gradient(135deg, #ec536c, #d63a56);
        color: #fff;
        box-shadow: 0 2px 6px rgba(236, 83, 108, 0.28);
    }
    .btn-icon-danger:hover { color: #fff; box-shadow: 0 4px 12px rgba(236, 83, 108, 0.4); }

    .btn-icon-warning {
        background: linear-gradient(135deg, #f5b225, #dd9c11);
        color: #fff;
        box-shadow: 0 2px 6px rgba(245, 178, 37, 0.28);
    }
    .btn-icon-warning:hover { color: #fff; box-shadow: 0 4px 12px rgba(245, 178, 37, 0.4); }

    .btn-icon-outline {
        background: #fff;
        color: #5b626b;
        border-color: #e3e6ea;
    }
    .btn-icon-outline:hover { color: #3eb7ba; border-color: #3eb7ba; background: #f1fbfb; }

    .btn-icon-outline-danger {
        background: #fff;
        color: #c73d58;
        border-color: #fbd6dc;
    }
    .btn-icon-outline-danger:hover { color: #fff; background: #ec536c; border-color: #ec536c; }

    .btn-icon-outline-warning {
        background: #fff;
        color: #a97812;
        border-color: #fce8b5;
    }
    .btn-icon-outline-warning:hover { color: #fff; background: #f5b225; border-color: #f5b225; }

    /* Small icon-only button */
    .btn-icon-square {
        width: 32px; height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid #e3e6ea;
        background: #fff;
        color: #6c757d;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-icon-square:hover {
        color: #3eb7ba;
        border-color: #3eb7ba;
        background: #f1fbfb;
    }

    /* Actions cell — stack on wrap */
    .actions-cell {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    /* ---------- Empty state ---------- */
    .admin-empty {
        padding: 56px 24px;
        text-align: center;
        color: #6c757d;
    }
    .admin-empty-icon {
        width: 56px; height: 56px;
        border-radius: 14px;
        background: #f1fbfb;
        color: #3eb7ba;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }
    .admin-empty h6 {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #343a40;
        margin: 0 0 6px;
    }
    .admin-empty p {
        font-size: 0.8125rem;
        margin: 0 0 20px;
        max-width: 380px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.55;
    }

    /* ---------- Filter chips ---------- */
    .admin-chips {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .admin-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 20px;
        border: 1px solid #e3e6ea;
        background: #fff;
        color: #5b626b;
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .admin-chip:hover {
        color: #3eb7ba;
        border-color: #3eb7ba;
        background: #f1fbfb;
        text-decoration: none;
    }
    .admin-chip.is-active {
        background: linear-gradient(135deg, #3eb7ba, #2fa2a6);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 2px 6px rgba(62, 183, 186, 0.28);
    }
    .admin-chip .chip-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 18px;
        padding: 0 6px;
        border-radius: 10px;
        background: rgba(0, 0, 0, 0.06);
        font-size: 0.6875rem;
        font-weight: 600;
    }
    .admin-chip.is-active .chip-count {
        background: rgba(255, 255, 255, 0.25);
    }

    /* ---------- Form styling ---------- */
    .admin-form { padding: 24px; }
    .admin-form-grid { display: grid; gap: 20px; }
    .admin-form-grid.two-col { grid-template-columns: 1fr 1fr; }
    @media (max-width: 640px) {
        .admin-form-grid.two-col { grid-template-columns: 1fr; }
    }

    .admin-field { display: flex; flex-direction: column; }
    .admin-field label {
        display: block;
        font-size: 0.75rem;
        font-weight: 500;
        color: #343a40;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 7px;
    }
    .admin-field label .req {
        color: #ec536c;
        margin-left: 2px;
    }
    .admin-field label .opt {
        text-transform: none;
        color: #adb5bd;
        font-weight: 400;
        letter-spacing: 0;
        font-size: 0.75rem;
        margin-left: 4px;
    }

    .admin-input,
    .admin-select,
    .admin-textarea {
        width: 100%;
        padding: 11px 14px;
        border: 1px solid #e3e6ea;
        border-radius: 10px;
        background: #fbfcfd;
        font-size: 0.875rem;
        font-family: inherit;
        color: #343a40;
        transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
        outline: none;
        line-height: 1.4;
    }
    .admin-input::placeholder,
    .admin-textarea::placeholder { color: #b1b7bd; }

    .admin-input:hover,
    .admin-select:hover,
    .admin-textarea:hover {
        border-color: #cfd5db;
        background: #fff;
    }
    .admin-input:focus,
    .admin-select:focus,
    .admin-textarea:focus {
        border-color: #3eb7ba;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(62, 183, 186, 0.15);
    }

    .admin-input.is-invalid,
    .admin-select.is-invalid,
    .admin-textarea.is-invalid {
        border-color: #ec536c;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(236, 83, 108, 0.12);
    }
    .admin-input.is-invalid:focus,
    .admin-select.is-invalid:focus,
    .admin-textarea.is-invalid:focus {
        box-shadow: 0 0 0 3px rgba(236, 83, 108, 0.18);
    }

    .admin-textarea { min-height: 90px; resize: vertical; }

    .admin-select {
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 38px;
    }

    .admin-error {
        font-size: 0.75rem;
        color: #c73d58;
        margin-top: 6px;
        display: flex;
        align-items: flex-start;
        gap: 4px;
        line-height: 1.5;
    }

    /* Custom checkbox */
    .admin-check {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        user-select: none;
        font-size: 0.8125rem;
        color: #5b626b;
    }
    .admin-check input {
        appearance: none;
        -webkit-appearance: none;
        width: 18px; height: 18px;
        border: 1.5px solid #cfd5db;
        border-radius: 5px;
        background: #fff;
        cursor: pointer;
        position: relative;
        transition: all 0.15s ease;
        flex-shrink: 0;
    }
    .admin-check input:checked {
        background: #3eb7ba;
        border-color: #3eb7ba;
    }
    .admin-check input:checked::after {
        content: "";
        position: absolute;
        left: 5px; top: 2px;
        width: 5px; height: 9px;
        border: solid #fff;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }
    .admin-check input:focus {
        box-shadow: 0 0 0 3px rgba(62, 183, 186, 0.18);
        outline: none;
    }

    /* Form footer */
    .admin-form-footer {
        display: flex;
        gap: 10px;
        padding-top: 8px;
        flex-wrap: wrap;
    }

    /* ---------- Back link ---------- */
    .admin-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #6c757d;
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 500;
        margin-bottom: 12px;
        transition: color 0.15s ease;
    }
    .admin-back:hover { color: #3eb7ba; text-decoration: none; }

    /* ---------- Responsive ---------- */
    @media (max-width: 767.98px) {
        .admin-table thead th,
        .admin-table tbody td {
            padding: 12px 14px;
        }
    }
</style>
@endpush
