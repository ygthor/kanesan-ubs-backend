
<div class="form-group">
    <label for="start_date" class="form-label required-field">Start Date</label>
    <input type="date" class="form-control @error('start_date') is-invalid @enderror"
           id="start_date" name="start_date" value="{{ old('start_date', isset($period) ? ($period->start_date?->format('Y-m-d') ?? '') : '') }}" required>
    @error('start_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="help-text">Select the start date of the period</div>
</div>

<div class="form-group">
    <label for="end_date" class="form-label required-field">End Date</label>
    <input type="date" class="form-control @error('end_date') is-invalid @enderror"
           id="end_date" name="end_date" value="{{ old('end_date', isset($period) ? ($period->end_date?->format('Y-m-d') ?? '') : '') }}" required>
    @error('end_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="help-text">Select the end date of the period (must be after start date)</div>
</div>

<div class="form-group">
    <label for="month_count" class="form-label">Month Count</label>
    <input type="text" class="form-control" id="month_count" readonly
           placeholder="Will be calculated automatically">
    <div class="help-text">Number of months between start and end dates</div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const monthCountInput = document.getElementById('month_count');

    function calculateMonthCount() {
        if (!startDateInput.value || !endDateInput.value) {
            monthCountInput.value = '';
            return;
        }

        // Parse date string explicitly to avoid timezone issues
        const [startYear, startMonth, startDay] = startDateInput.value.split('-').map(Number);
        const [endYear, endMonth, endDay] = endDateInput.value.split('-').map(Number);
        
        const startDate = new Date(startYear, startMonth - 1, startDay);
        const endDate = new Date(endYear, endMonth - 1, endDay);

        if (startDate <= endDate) {
            // Calculate months difference
            const months = (endYear - startYear) * 12 + (endMonth - startMonth) + 1;
            monthCountInput.value = months + ' month' + (months !== 1 ? 's' : '');
        } else {
            monthCountInput.value = '';
        }
    }

    startDateInput.addEventListener('change', calculateMonthCount);
    endDateInput.addEventListener('change', calculateMonthCount);

    // Calculate on page load if dates are already set
    calculateMonthCount();
});
</script>
@endpush