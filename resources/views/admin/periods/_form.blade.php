<div class="form-group">
    <label for="start_date" class="form-label required-field">Start Date</label>
    <input type="date" class="form-control @error('start_date') is-invalid @enderror"
           id="start_date" name="start_date" value="{{ old('start_date', isset($period) ? ($period->start_date?->format('Y-m-d') ?? '') : ($suggestedStartDate ?? date('Y-01-01'))) }}" required>
    @error('start_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="help-text">Start date is fixed as 01 Jan (e.g. {{ date('Y') }}-01-01)</div>
</div>

<div class="form-group">
    <label for="end_date" class="form-label required-field">End Date</label>
    <input type="date" class="form-control @error('end_date') is-invalid @enderror"
           id="end_date" name="end_date" value="{{ old('end_date', isset($period) ? ($period->end_date?->format('Y-m-d') ?? '') : ($suggestedEndDate ?? date('Y-m-d', strtotime('+17 months', strtotime(date('Y-01-01')))))) }}" required>
    @error('end_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="help-text">Default is +18 months (must be on or after start date)</div>
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

        const [startYear, startMonth, startDay] = startDateInput.value.split('-').map(Number);
        const [endYear, endMonth, endDay] = endDateInput.value.split('-').map(Number);
        
        const startDate = new Date(startYear, startMonth - 1, startDay);
        const endDate = new Date(endYear, endMonth - 1, endDay);

        if (startDate <= endDate) {
            const months = (endYear - startYear) * 12 + (endMonth - startMonth) + 1;
            monthCountInput.value = months + ' month' + (months !== 1 ? 's' : '');
        } else {
            monthCountInput.value = '';
        }
    }

    startDateInput.addEventListener('change', function() {
        if (this.value) {
            const year = parseInt(this.value.split('-')[0]);
            if (year) {
                this.value = `${year}-01-01`;
                if (!endDateInput.value || endDateInput.value < this.value) {
                    endDateInput.value = `${year + 1}-06-30`;
                }
            }
        }
        calculateMonthCount();
    });

    endDateInput.addEventListener('change', calculateMonthCount);

    calculateMonthCount();
});
</script>
@endpush