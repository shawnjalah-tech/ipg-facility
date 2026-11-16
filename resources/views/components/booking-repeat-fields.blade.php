<section class="admin-card p-3 my-3" aria-labelledby="repeat-heading">
    <h3 id="repeat-heading" class="h6 mb-2">Repeat booking</h3>
    <div class="admin-form-grid two-col" style="gap:16px;">
        <div class="admin-field">
            <label for="repeat">Repeat frequency</label>
            <select class="admin-select @error('repeat') is-invalid @enderror" name="repeat" id="repeat">
                <option value="none" @selected(old('repeat', 'none') === 'none')>Does not repeat</option>
                <option value="weekly" @selected(old('repeat') === 'weekly')>Weekly (up to 26)</option>
                <option value="monthly" @selected(old('repeat') === 'monthly')>Monthly (up to 12)</option>
            </select>
            @error('repeat')<div class="admin-error">{{ $message }}</div>@enderror
        </div>
        <div class="admin-field" id="repeat-count-field">
            <label for="repeat_count">Total occurrences (including first booking)</label>
            <input type="number" name="repeat_count" id="repeat_count" class="admin-input @error('repeat_count') is-invalid @enderror" min="2" max="26" value="{{ old('repeat_count', 12) }}">
            @error('repeat_count')<div class="admin-error">{{ $message }}</div>@enderror
        </div>
    </div>
</section>
@once
@push('scripts')
<script>
(function () {
    const frequency = document.getElementById('repeat');
    const countField = document.getElementById('repeat-count-field');
    const count = document.getElementById('repeat_count');
    if (!frequency || !count || !countField) return;
    const update = function () {
        const enabled = frequency.value !== 'none';
        countField.hidden = !enabled;
        countField.style.display = enabled ? '' : 'none';
        count.disabled = !enabled;
        count.max = frequency.value === 'monthly' ? '12' : '26';
        if (enabled && Number(count.value) > Number(count.max)) count.value = count.max;
    };
    frequency.addEventListener('change', update);
    update();
})();
</script>
@endpush
@endonce
