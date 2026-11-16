@push('styles')
<style>
    /* ---------- Mini stat cards ---------- */
    .mini-stat {
        position: relative;
        border-radius: 0.25rem;
        overflow: hidden;
        color: #fff;
        margin-bottom: 24px;
    }
    .mini-stat-body {
        padding: 20px;
        position: relative;
    }
    .mini-stat-icon {
        position: absolute;
        top: 16px;
        right: 18px;
        font-size: 2rem;
        opacity: 0.35;
    }
    .mini-stat-label {
        text-transform: uppercase;
        font-size: 0.6875rem;
        letter-spacing: 0.06em;
        opacity: 0.9;
        margin-bottom: 10px;
        font-weight: 500;
    }
    .mini-stat-value {
        font-size: 2rem;
        font-weight: 600;
        line-height: 1;
        margin: 0;
    }

    /* Semantic colours from the palette */
    .mini-stat.teal   { background: #247e81; }
    .mini-stat.purple { background: #655ba4; }
    .mini-stat.green  { background: #267b47; }
    .mini-stat.amber  { background: #f5b225; color: #343a40; }
    .mini-stat.red    { background: #ec536c; }
    .mini-stat.grey   { background: #6c757d; }

    /* ---------- Booking preview rows ---------- */
    .preview-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f1f2f4;
    }
    .preview-row:last-child { border-bottom: 0; }
    .preview-main { min-width: 0; }
    .preview-title {
        font-weight: 500;
        color: #343a40;
        margin: 0 0 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .preview-meta {
        color: #6c757d;
        font-size: 0.75rem;
    }
    .preview-dates {
        color: #6c757d;
        font-size: 0.75rem;
        text-align: right;
        white-space: nowrap;
        padding-left: 12px;
    }

    /* ---------- Empty state ---------- */
    .dashboard-empty {
        text-align: center;
        padding: 24px 12px;
        color: #6c757d;
        font-size: 0.8125rem;
    }
    .dashboard-empty i {
        font-size: 2rem;
        display: block;
        margin-bottom: 8px;
        opacity: 0.4;
    }

    /* ---------- Quick actions ---------- */
    .quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
</style>
@endpush
