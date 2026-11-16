@props(['startTime' => null, 'endTime' => null])
@php
    // Display order only. All 24 hours are still valid, including midnight.
    $orderedHours = array_merge(range(6, 23), range(0, 5));
    $quickTimes = [];
    foreach ($orderedHours as $hour) {
        foreach ([0, 15, 30, 45] as $minute) {
            $value = sprintf('%02d:%02d', $hour, $minute);
            $quickTimes[$value] = \Carbon\Carbon::createFromTime($hour, $minute)->format('g:i A');
        }
    }
    $selectedTimes = [
        'start_time' => is_string($startTime) ? substr($startTime, 0, 5) : '',
        'end_time' => is_string($endTime) ? substr($endTime, 0, 5) : '',
    ];
@endphp
<fieldset class="schedule-fields" data-booking-time>
    <legend>Booking time · {{ config('app.timezone') }}</legend>
    <div class="schedule-options" data-time-option hidden>
        <input type="checkbox" id="all-day" data-all-day @checked(!$selectedTimes['start_time'] && !$selectedTimes['end_time'] && request()->routeIs('staff.bookings.reschedule.create'))>
        <label for="all-day">All day</label>
    </div>
    <div class="admin-form-grid two-col" data-time-inputs>
        @foreach (['start_time' => 'Start time', 'end_time' => 'End time'] as $field => $label)
            @php $selected = $selectedTimes[$field]; @endphp
            <div class="admin-field" data-time-field>
                <label for="{{ $field }}">{{ $label }}</label>
                <select class="admin-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" data-time-select>
                    <option value="" @selected($selected === '')>— Choose a time —</option>
                    @if($selected !== '' && !array_key_exists($selected, $quickTimes))
                        <option value="{{ $selected }}" selected>{{ $selected }} (exact minute)</option>
                    @endif
                    @foreach($quickTimes as $value => $caption)
                        <option value="{{ $value }}" @selected($selected === $value)>{{ $caption }} ({{ $value }})</option>
                    @endforeach
                </select>
                <div id="{{ $field }}_exact_fields" data-exact-time-container hidden style="margin-top:8px">
                    <label for="{{ $field }}_exact">Exact time (any minute)</label>
                    <input class="admin-input" type="time" id="{{ $field }}_exact" data-exact-time disabled value="{{ $selected }}" step="60">
                </div>
                <button type="button" class="btn-icon btn-icon-outline" data-exact-time-toggle hidden aria-expanded="false" aria-controls="{{ $field }}_exact_fields" style="margin-top:7px;font-size:.8rem">Enter exact minute</button>
                @error($field)<div class="admin-error">{{ $message }}</div>@enderror
            </div>
        @endforeach
    </div>
    <div class="admin-help" data-overnight-hint hidden>For an overnight booking, choose the next day as the end date.</div>
    <noscript><p class="admin-help mb-0">Leave both times blank for an all-day booking.</p></noscript>
</fieldset>
