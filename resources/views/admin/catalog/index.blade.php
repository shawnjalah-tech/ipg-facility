@extends('layouts.app')

@section('title', 'Facilities & Categories')

@include('partials.admin-ui')

@push('styles')
<style>
    /* =========================================================
       Catalog accordion — combined categories + facilities view
       ========================================================= */

    .catalog-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .catalog-search {
        flex: 1;
        min-width: 220px;
        max-width: 380px;
        position: relative;
    }
    .catalog-search input {
        width: 100%;
        padding: 9px 14px 9px 38px;
        border: 1px solid #e3e6ea;
        border-radius: 10px;
        background: #fff;
        font-size: 0.8125rem;
        font-family: inherit;
        color: #343a40;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .catalog-search input:hover { border-color: #cfd5db; }
    .catalog-search input:focus {
        border-color: #3eb7ba;
        box-shadow: 0 0 0 3px rgba(62, 183, 186, 0.15);
    }
    .catalog-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
        pointer-events: none;
    }

    /* ---------- Accordion list ---------- */
    .catalog-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .catalog-group {
        background: #fff;
        border: 1px solid #eef1f4;
        border-radius: 12px;
        overflow: hidden;
        transition: box-shadow 0.15s ease;
    }
    .catalog-group:hover { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03); }
    .catalog-group.is-expanded { box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06); }
    .catalog-group.is-hidden { display: none; }

    /* ---------- Category header ---------- */
    .catalog-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 20px;
        cursor: pointer;
        user-select: none;
        transition: background 0.12s ease;
    }
    .catalog-header:hover { background: #fafcfd; }
    .catalog-header:focus { outline: none; }
    .catalog-header:focus-visible { outline: 2px solid #3eb7ba; outline-offset: -2px; }
    .catalog-group.is-expanded .catalog-header {
        background: #fafbfc;
        border-bottom: 1px solid #eef1f4;
    }

    .catalog-chevron {
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        border-radius: 6px;
        background: #f1f3f5;
        color: #6c757d;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s ease, background 0.15s ease, color 0.15s ease;
    }
    .catalog-group.is-expanded .catalog-chevron {
        transform: rotate(90deg);
        background: #e6f7f7;
        color: #3eb7ba;
    }

    .catalog-meta { flex: 1; min-width: 0; }
    .catalog-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #343a40;
        margin: 0 0 2px;
        line-height: 1.3;
    }
    .catalog-desc {
        font-size: 0.75rem;
        color: #8a93a0;
        margin: 0;
        line-height: 1.4;
    }
    .catalog-count {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 20px;
        background: #f1fbfb;
        color: #2fa2a6;
        font-size: 0.6875rem;
        font-weight: 600;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .catalog-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    /* ---------- Expanded body ---------- */
    .catalog-body { display: none; background: #fff; }
    .catalog-group.is-expanded .catalog-body { display: block; }

    .catalog-body-empty {
        padding: 24px 20px;
        text-align: center;
        color: #8a93a0;
        font-size: 0.8125rem;
    }

    /* ---------- Facility rows inside body ---------- */
    table.facility-rows {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    table.facility-rows tr { border-bottom: 1px solid #f1f3f5; }
    table.facility-rows tr:last-child { border-bottom: 0; }
    table.facility-rows tr:hover { background: #fafcfd; }

    table.facility-rows td {
        padding: 12px 20px;
        vertical-align: middle;
    }
    table.facility-rows td.row-name     { min-width: 160px; }
    table.facility-rows td.row-capacity { width: 100px; text-align: center; }
    table.facility-rows td.row-status   { width: 110px; text-align: center; }
    table.facility-rows td.row-actions  { text-align: right; white-space: nowrap; }

    .facility-name {
        font-weight: 500;
        color: #343a40;
        margin: 0 0 2px;
        line-height: 1.3;
    }
    .facility-desc {
        font-size: 0.75rem;
        color: #8a93a0;
        margin: 0;
        line-height: 1.4;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 767.98px) {
        .catalog-header {
            padding: 12px 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .catalog-actions {
            width: 100%;
            justify-content: flex-end;
        }
        .catalog-actions .btn-icon {
            padding: 5px 10px;
            font-size: 0.6875rem;
        }
        .catalog-count { order: 99; margin-left: auto; }

        table.facility-rows td { padding: 10px 14px; }
        table.facility-rows td.row-capacity,
        table.facility-rows td.row-status { display: none; }
        table.facility-rows td.row-actions { white-space: normal; }
    }
</style>
@endpush

@section('content')

    @php
        $totalFacilities = 0;
        $activeFacilities = 0;
        $inactiveFacilities = 0;
        foreach ($categories as $cat) {
            foreach ($cat->facilities as $f) {
                $totalFacilities++;
                if ($f->is_active) $activeFacilities++;
                else $inactiveFacilities++;
            }
        }
    @endphp

    {{-- Page header --}}
    <x-page-header>
        <x-slot:title>Facilities &amp; Categories</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('admin.categories.create') }}" class="btn-icon btn-icon-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Category
            </a>
            <a href="{{ route('admin.facilities.create') }}" class="btn-icon btn-icon-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Facility
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Stat pills --}}
    @if ($categories->isNotEmpty())
        <div class="admin-stat-pills">
            <div class="admin-stat-pill">
                <span class="pill-icon teal">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                </span>
                <div class="pill-content">
                    <p class="pill-label">Categories</p>
                    <p class="pill-value">{{ $categories->count() }}</p>
                </div>
            </div>

            <div class="admin-stat-pill">
                <span class="pill-icon green">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </span>
                <div class="pill-content">
                    <p class="pill-label">Total Facilities</p>
                    <p class="pill-value">{{ $totalFacilities }}</p>
                </div>
            </div>

            <div class="admin-stat-pill">
                <span class="pill-icon amber">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </span>
                <div class="pill-content">
                    <p class="pill-label">Active</p>
                    <p class="pill-value">{{ $activeFacilities }}</p>
                </div>
            </div>

            @if ($inactiveFacilities > 0)
                <div class="admin-stat-pill">
                    <span class="pill-icon grey">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                    </span>
                    <div class="pill-content">
                        <p class="pill-label">Inactive</p>
                        <p class="pill-value">{{ $inactiveFacilities }}</p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Toolbar --}}
    @if ($categories->isNotEmpty())
        <div class="catalog-toolbar">
            <div class="catalog-search">
                <svg class="catalog-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text"
                       id="catalog-search-input"
                       aria-label="Search categories or facilities"
                       autocomplete="off">
            </div>
            <button type="button" id="catalog-expand-all" class="btn-icon btn-icon-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                Expand All
            </button>
            <button type="button" id="catalog-collapse-all" class="btn-icon btn-icon-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
                Collapse All
            </button>
        </div>
    @endif

    {{-- Catalog list --}}
    @if ($categories->isEmpty())
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                </div>
                <h6>No categories yet</h6>
                <a href="{{ route('admin.categories.create') }}" class="btn-icon btn-icon-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Create Category
                </a>
            </div>
        </div>
    @else
        <div class="catalog-list" id="catalog-list">
            @foreach ($categories as $category)
                <div class="catalog-group"
                     data-category-id="{{ $category->id }}"
                     data-category-name="{{ strtolower($category->name) }}"
                     data-facility-names="{{ $category->facilities->pluck('name')->map(fn($n) => strtolower($n))->implode('|') }}">

                    <div class="catalog-header" role="button" tabindex="0" aria-expanded="false">
                        <span class="catalog-chevron">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>

                        <div class="catalog-meta">
                            <h3 class="catalog-title">{{ $category->name }}</h3>
                            @if ($category->description)
                                <p class="catalog-desc">{{ $category->description }}</p>
                            @endif
                        </div>

                        <span class="catalog-count">
                            {{ $category->facilities->count() }}
                            {{ \Illuminate\Support\Str::plural('facility', $category->facilities->count()) }}
                        </span>

                        <div class="catalog-actions" onclick="event.stopPropagation();">
                            <a href="{{ route('admin.categories.edit', $category) }}"
                               class="btn-icon btn-icon-outline" title="Edit category">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Edit
                            </a>
                            <form action="{{ route('admin.categories.destroy', $category) }}"
                                  method="POST"
                                  class="delete-form d-inline"
                                  data-name="{{ $category->name }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon btn-icon-outline-danger" title="Delete category">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                                    Delete
                                </button>
                            </form>
                            <a href="{{ route('admin.facilities.create', ['category_id' => $category->id]) }}"
                               class="btn-icon btn-icon-outline" title="Add facility to this category">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Facility
                            </a>
                        </div>
                    </div>

                    <div class="catalog-body">
                        @if ($category->facilities->isEmpty())
                            <div class="catalog-body-empty">
                                No facilities in this category yet.
                                <a href="{{ route('admin.facilities.create', ['category_id' => $category->id]) }}"
                                   style="color:#3eb7ba;font-weight:500;text-decoration:none;margin-left:4px;">
                                    Add the first one →
                                </a>
                            </div>
                        @else
                            <table class="facility-rows">
                                <tbody>
                                    @foreach ($category->facilities as $facility)
                                        <tr data-facility-name="{{ strtolower($facility->name) }}">
                                            <td class="row-name">
                                                <p class="facility-name">{{ $facility->name }}</p>
                                                @if ($facility->description)
                                                    <p class="facility-desc">{{ \Illuminate\Support\Str::limit($facility->description, 80) }}</p>
                                                @endif
                                            </td>
                                            <td class="row-capacity">
                                                @if ($facility->capacity)
                                                    <span class="time-chip">
                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                                        {{ $facility->capacity }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="row-status">
                                                @if ($facility->is_active)
                                                    <span class="soft-badge soft-badge-success">Active</span>
                                                @else
                                                    <span class="soft-badge soft-badge-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="row-actions">
                                                <div class="actions-cell">
                                                    <a href="{{ route('admin.facilities.edit', $facility) }}"
                                                       class="btn-icon btn-icon-outline" title="Edit facility">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                        Edit
                                                    </a>
                                                    <form action="{{ route('admin.facilities.destroy', $facility) }}"
                                                          method="POST"
                                                          class="delete-form d-inline"
                                                          data-name="{{ $facility->name }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-icon btn-icon-outline-danger" title="Delete facility">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @include('admin.partials.delete-confirm-script')
@endsection

@push('scripts')
<script>
(function () {
    var list = document.getElementById('catalog-list');
    if (!list) return;

    var searchInput = document.getElementById('catalog-search-input');
    var expandAllBtn = document.getElementById('catalog-expand-all');
    var collapseAllBtn = document.getElementById('catalog-collapse-all');

    function expand(group) {
        group.classList.add('is-expanded');
        var header = group.querySelector('.catalog-header');
        if (header) header.setAttribute('aria-expanded', 'true');
    }
    function collapse(group) {
        group.classList.remove('is-expanded');
        var header = group.querySelector('.catalog-header');
        if (header) header.setAttribute('aria-expanded', 'false');
    }
    function toggle(group) {
        group.classList.contains('is-expanded') ? collapse(group) : expand(group);
    }

    list.querySelectorAll('.catalog-group').forEach(function (group) {
        var header = group.querySelector('.catalog-header');
        if (!header) return;

        header.addEventListener('click', function (e) {
            if (e.target.closest('a, button, form')) return;
            toggle(group);
        });
        header.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle(group);
            }
        });
    });

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function () {
            list.querySelectorAll('.catalog-group').forEach(expand);
        });
    }
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function () {
            list.querySelectorAll('.catalog-group').forEach(collapse);
        });
    }

    // Deep-link: #category-3 auto-expands the matching group
    function applyHash() {
        var match = window.location.hash.match(/^#category-(\d+)$/);
        if (!match) return;
        var group = list.querySelector('.catalog-group[data-category-id="' + match[1] + '"]');
        if (!group) return;
        expand(group);
        setTimeout(function () {
            group.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 50);
    }
    applyHash();
    window.addEventListener('hashchange', applyHash);

    // Search
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var term = searchInput.value.trim().toLowerCase();
            var anyVisible = false;

            list.querySelectorAll('.catalog-group').forEach(function (group) {
                var categoryName = group.dataset.categoryName || '';
                var facilityNames = group.dataset.facilityNames || '';

                if (!term) {
                    group.classList.remove('is-hidden');
                    group.querySelectorAll('tr[data-facility-name]').forEach(function (row) {
                        row.style.display = '';
                    });
                    anyVisible = true;
                    return;
                }

                var matchesCategory = categoryName.indexOf(term) !== -1;
                var matchesFacility = facilityNames.indexOf(term) !== -1;

                if (matchesCategory) {
                    group.classList.remove('is-hidden');
                    group.querySelectorAll('tr[data-facility-name]').forEach(function (row) {
                        row.style.display = '';
                    });
                    anyVisible = true;
                } else if (matchesFacility) {
                    group.classList.remove('is-hidden');
                    group.querySelectorAll('tr[data-facility-name]').forEach(function (row) {
                        var name = row.dataset.facilityName || '';
                        row.style.display = name.indexOf(term) !== -1 ? '' : 'none';
                    });
                    expand(group);
                    anyVisible = true;
                } else {
                    group.classList.add('is-hidden');
                }
            });

            var banner = document.getElementById('catalog-no-results');
            if (!anyVisible && term) {
                if (!banner) {
                    banner = document.createElement('div');
                    banner.id = 'catalog-no-results';
                    banner.className = 'admin-card';
                    banner.innerHTML = '<div class="admin-empty">'
                        + '<div class="admin-empty-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>'
                        + '<h6>No matches found</h6></div>';
                    list.parentNode.insertBefore(banner, list.nextSibling);
                }
                banner.style.display = '';
            } else if (banner) {
                banner.style.display = 'none';
            }
        });
    }
})();
</script>
@endpush
