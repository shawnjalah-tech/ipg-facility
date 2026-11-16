<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Report — {{ $monthStart->format('F Y') }}</title>
    <style>
        /* ============ Page setup ============ */
        @page {
            margin: 32mm 15mm 22mm 15mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #343a40;
            line-height: 1.5;
            margin: 0;
        }

        /* ============ Fixed header on every page ============ */
        .pdf-header {
            position: fixed;
            top: -22mm;
            left: 0;
            right: 0;
            height: 16mm;
            border-bottom: 2px solid #3eb7ba;
            padding-bottom: 4mm;
        }
        .pdf-header table {
            width: 100%;
            border-collapse: collapse;
        }
        .pdf-header td {
            vertical-align: middle;
            padding: 0;
        }
        .pdf-header .brand {
            font-size: 11px;
            font-weight: bold;
            color: #3eb7ba;
            letter-spacing: 0.02em;
        }
        .pdf-header .brand-sub {
            font-size: 8px;
            color: #8a93a0;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-top: 1mm;
        }
        .pdf-header .page-ref {
            text-align: right;
            font-size: 8px;
            color: #8a93a0;
        }
        .pdf-header .page-ref strong {
            color: #343a40;
            font-size: 9px;
        }

        /* ============ Fixed footer ============ */
        .pdf-footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            height: 8mm;
            border-top: 1px solid #e9ecef;
            padding-top: 3mm;
            font-size: 8px;
            color: #8a93a0;
        }
        .pdf-footer table { width: 100%; }
        .pdf-footer td { padding: 0; vertical-align: middle; }
        .pdf-footer .pg { text-align: right; }
        .pdf-footer .pg::after {
            content: "Page " counter(page);
        }

        /* ============ Cover title ============ */
        .report-title {
            margin-bottom: 6mm;
            padding-bottom: 4mm;
            border-bottom: 1px solid #e9ecef;
        }
        .report-title h1 {
            font-size: 22px;
            font-weight: bold;
            color: #343a40;
            margin: 0 0 2mm;
            letter-spacing: -0.01em;
        }
        .report-title .subtitle {
            font-size: 13px;
            color: #3eb7ba;
            font-weight: bold;
            margin: 0 0 3mm;
        }
        .report-title .meta {
            font-size: 9px;
            color: #6c757d;
        }
        .report-title .meta strong {
            color: #343a40;
        }

        /* ============ Section headings ============ */
        .section-head {
            font-size: 11px;
            font-weight: bold;
            color: #343a40;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 8mm 0 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid #e9ecef;
            page-break-after: avoid;
        }

        /* ============ Summary cards (4-up, using table) ============ */
        table.summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3mm 0;
            margin-bottom: 2mm;
        }
        table.summary td {
            width: 25%;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-top: 3px solid #3eb7ba;
            padding: 4mm 4mm 3mm;
            vertical-align: top;
        }
        table.summary td.green  { border-top-color: #58db83; }
        table.summary td.amber  { border-top-color: #f5b225; }
        table.summary td.purple { border-top-color: #7a6fbe; }

        table.summary .sum-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #8a93a0;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        table.summary .sum-value {
            font-size: 20px;
            font-weight: bold;
            color: #343a40;
            line-height: 1;
        }
        table.summary .sum-sub {
            font-size: 8px;
            color: #6c757d;
            margin-top: 1mm;
        }

        /* ============ Two-column breakdown ============ */
        table.two-col {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3mm 0;
        }
        table.two-col > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 4mm;
        }
        .card-title-sm {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #343a40;
            margin: 0 0 3mm;
        }

        /* Status bars — using table + inline width */
        table.status-list { width: 100%; border-collapse: collapse; }
        table.status-list td {
            padding: 1.5mm 0;
            vertical-align: middle;
            font-size: 9px;
        }
        table.status-list td.sl-label {
            width: 22mm;
            color: #5b626b;
        }
        table.status-list td.sl-bar {
            padding-right: 3mm;
        }
        .bar-track {
            height: 2mm;
            background: #f1f3f5;
            border-radius: 1mm;
            overflow: hidden;
        }
        .bar-fill {
            height: 2mm;
            border-radius: 1mm;
        }
        .bar-fill.approved  { background: #58db83; }
        .bar-fill.pending   { background: #f5b225; }
        .bar-fill.rejected  { background: #ec536c; }
        .bar-fill.cancelled { background: #adb5bd; }
        .bar-fill.expired   { background: #6c757d; }
        table.status-list td.sl-val {
            width: 18mm;
            text-align: right;
            color: #343a40;
            font-weight: bold;
        }
        table.status-list td.sl-val span {
            color: #8a93a0;
            font-weight: normal;
            margin-left: 1mm;
        }

        /* Facility ranking */
        table.facility-list { width: 100%; border-collapse: collapse; }
        table.facility-list td {
            padding: 1.5mm 0;
            font-size: 9px;
            border-bottom: 1px solid #f8f9fa;
        }
        table.facility-list tr:last-child td { border-bottom: 0; }
        table.facility-list td.fr-rank {
            width: 6mm;
            color: #3eb7ba;
            font-weight: bold;
        }
        table.facility-list td.fr-name {
            color: #343a40;
            font-weight: bold;
        }
        table.facility-list td.fr-name .cat {
            display: block;
            font-weight: normal;
            color: #8a93a0;
            font-size: 8px;
        }
        table.facility-list td.fr-count {
            width: 14mm;
            text-align: right;
            color: #3eb7ba;
            font-weight: bold;
        }

        /* Day-of-week heatmap */
        table.dow { width: 100%; border-collapse: collapse; }
        table.dow td {
            padding: 1.2mm 0;
            vertical-align: middle;
            font-size: 9px;
        }
        table.dow td.dow-label {
            width: 24mm;
            color: #343a40;
            font-weight: bold;
        }
        table.dow td.dow-bar {
            padding-right: 3mm;
        }
        table.dow td.dow-count {
            width: 12mm;
            text-align: right;
            color: #343a40;
            font-weight: bold;
        }
        .dow-track {
            height: 2mm;
            background: #f1f3f5;
            border-radius: 1mm;
            overflow: hidden;
        }
        .dow-fill {
            height: 2mm;
            background: #3eb7ba;
            border-radius: 1mm;
        }

        /* ============ Bookings table ============ */
        table.bookings {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            margin-top: 2mm;
        }
        table.bookings thead th {
            background: #3eb7ba;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2.5mm 2mm;
            text-align: left;
            border-bottom: 1px solid #2fa2a6;
        }
        table.bookings tbody td {
            padding: 2mm;
            border-bottom: 1px solid #eef1f4;
            vertical-align: top;
            word-wrap: break-word;
            color: #343a40;
        }
        table.bookings tbody tr:nth-child(even) td {
            background: #fafbfc;
        }
        table.bookings .col-dates    { width: 22%; }
        table.bookings .col-facility { width: 22%; }
        table.bookings .col-booker   { width: 20%; }
        table.bookings .col-purpose  { width: 26%; }
        table.bookings .col-status   { width: 10%; text-align: center; }
        table.bookings .facility-cat {
            font-size: 7.5px;
            color: #8a93a0;
        }
        table.bookings .emp-id {
            font-size: 7.5px;
            color: #8a93a0;
        }

        /* Status pills in table */
        .status-pill {
            display: inline-block;
            padding: 1mm 2mm;
            border-radius: 2mm;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .status-pill.approved  { background: #eafaf0; color: #2e8b52; }
        .status-pill.pending   { background: #fef6e2; color: #a97812; }
        .status-pill.rejected  { background: #fdecef; color: #c73d58; }
        .status-pill.cancelled { background: #eef0f3; color: #5a6470; }
        .status-pill.expired   { background: #eef0f3; color: #343a40; }

        tr { page-break-inside: avoid; }

        .empty-note {
            text-align: center;
            color: #6c757d;
            padding: 8mm 4mm;
            font-size: 10px;
            background: #f8f9fa;
            border-radius: 2mm;
        }
    </style>
</head>
<body>

{{-- ============ Fixed header ============ --}}
<div class="pdf-header">
    <div class="brand">IPG Gaya Facility Booking System</div>
    <div class="brand-sub">Monthly Report</div>
    <div class="page-ref" style="position:absolute;right:0;top:0;">
        <div><strong>{{ $monthStart->format('F Y') }}</strong></div>
        <div>Generated {{ $generatedAt->format('d M Y, H:i') }}</div>
    </div>
</div>

<div class="pdf-footer">
    <span>Generated by {{ $generatedBy }} &middot; {{ config('app.name') }}</span>
    <span class="pg" style="position:absolute;right:0;top:3mm;"></span>
</div>

{{-- ============ Title block ============ --}}
<div class="report-title">
    <h1>{{ $summaryOnly ? 'Monthly Booking Summary' : ($pdfPart ? 'Booking Details' : 'Monthly Booking Report') }}</h1>
    <div class="subtitle">{{ $monthStart->format('F Y') }}@if($pdfPart) - Part {{ $pdfPart }} of {{ $pdfParts }}@endif</div>
    <div class="meta">
        Period: <strong>{{ $monthStart->format('d M Y') }}</strong> — <strong>{{ $monthEnd->format('d M Y') }}</strong>
        &nbsp;&middot;&nbsp;
        Generated on <strong>{{ $generatedAt->format('d M Y \a\t H:i') }}</strong>
        &nbsp;&middot;&nbsp;
        By <strong>{{ $generatedBy }}</strong>
    </div>
</div>

{{-- ============ Summary cards ============ --}}
<table class="summary">
    <tr>
        <td>
            <div class="sum-label">Total Requests</div>
            <div class="sum-value">{{ $totalBookings }}</div>
        </td>
        <td class="green">
            <div class="sum-label">Approved</div>
            <div class="sum-value">{{ $statusCounts['approved'] }}</div>
        </td>
        <td class="amber">
            <div class="sum-label">Pending</div>
            <div class="sum-value">{{ $statusCounts['pending'] }}</div>
        </td>
        <td class="purple">
            <div class="sum-label">Facilities Used</div>
            <div class="sum-value">{{ $uniqueFacilities }}</div>
        </td>
    </tr>
</table>

@if ($totalBookings === 0)

    <div class="empty-note">
        No bookings were recorded during {{ $monthStart->format('F Y') }}.
    </div>

@else

    {{-- ============ Two-column breakdown ============ --}}
    <div class="section-head">Breakdown</div>

    <table class="two-col">
        <tr>
            {{-- Status breakdown --}}
            <td>
                <div class="card-title-sm">Status Breakdown</div>
                <table class="status-list">
                    @foreach (['approved', 'pending', 'rejected', 'cancelled', 'expired'] as $status)
                        @php
                            $count = $statusCounts[$status];
                            $pct   = $totalBookings > 0 ? round(($count / $totalBookings) * 100) : 0;
                        @endphp
                        <tr>
                            <td class="sl-label">{{ ucfirst($status) }}</td>
                            <td class="sl-bar">
                                <div class="bar-track">
                                    <div class="bar-fill {{ $status }}" style="width: {{ $pct }}%;"></div>
                                </div>
                            </td>
                            <td class="sl-val">
                                {{ $count }}
                                <span>({{ $pct }}%)</span>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>

            {{-- Top facilities --}}
            <td>
                <div class="card-title-sm">Top Facilities</div>
                <table class="facility-list">
                    @foreach ($byFacility->take(5) as $idx => $fac)
                        <tr>
                            <td class="fr-rank">{{ $idx + 1 }}</td>
                            <td class="fr-name">
                                {{ $fac['name'] }}
                                <span class="cat">{{ $fac['category'] }}</span>
                            </td>
                            <td class="fr-count">{{ $fac['count'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    {{-- ============ Day of week ============ --}}
    <div class="section-head">Busiest Days of the Week</div>

    @php
        $maxDow = max($byDayOfWeek);
    @endphp
    <table class="dow">
        @foreach ($byDayOfWeek as $day => $count)
            <tr>
                <td class="dow-label">{{ $day }}</td>
                <td class="dow-bar">
                    <div class="dow-track">
                        <div class="dow-fill" style="width: {{ $maxDow > 0 ? round(($count / $maxDow) * 100) : 0 }}%;"></div>
                    </div>
                </td>
                <td class="dow-count">{{ $count }}</td>
            </tr>
        @endforeach
    </table>

    @if($summaryOnly)
        <div class="section-head">Booking details</div>
        <p>Monthly summary: {{ $totalBookings }} bookings. Detailed records are available in {{ $pdfParts }} PDF parts from the monthly report page, or in the complete monthly CSV.</p>
        <p><a href="{{ route('admin.reports.monthly', ['month' => $monthStart->format('Y-m')]) }}">Open monthly report</a></p>
    @else
    {{-- ============ Bounded booking details ============ --}}
    <div class="section-head">Bookings {{ $detailStart }}-{{ $detailEnd }} of {{ $totalBookings }}</div>

    <table class="bookings">
        <thead>
            <tr>
                <th class="col-dates">Dates</th>
                <th class="col-facility">Facility</th>
                <th class="col-booker">Booker</th>
                <th class="col-purpose">Purpose</th>
                <th class="col-status">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bookings as $b)
                <tr>
                    <td class="col-dates">{{ $b->date_range }}</td>
                    <td class="col-facility">
                        {{ $b->facility->name ?? '—' }}
                        @if ($b->facility?->category)
                            <div class="facility-cat">{{ $b->facility->category->name }}</div>
                        @endif
                    </td>
                    <td class="col-booker">
                        {{ $b->user->name ?? '—' }}
                        @if ($b->user?->employee_id)
                            <div class="emp-id">{{ $b->user->employee_id }}</div>
                        @endif
                    </td>
                    <td class="col-purpose">
                        {{ $b->purpose ?: '—' }}
                    </td>
                    <td class="col-status">
                        <span class="status-pill {{ $b->status }}">{{ $b->status }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

@endif

</body>
</html>