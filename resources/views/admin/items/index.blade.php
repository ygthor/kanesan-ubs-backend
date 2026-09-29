@extends('layouts.admin')

@section('title', 'Item Status Management - Kanesan Backend')

@section('page-title', 'Item Status Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Items</li>
@endsection

@section('card-title', 'Item Status Control')

@push('styles')
<style>
    .stat-card {
        border-radius: 8px;
        padding: 12px 18px;
        margin-bottom: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .stat-card .stat-icon {
        font-size: 2rem;
        opacity: 0.85;
    }
    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-card .stat-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-badge {
        font-size: 0.85rem;
        padding: 0.35rem 0.65rem;
        font-weight: 600;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .table td, .table th {
        vertical-align: middle !important;
    }
    .batch-bar {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 10px 15px;
        margin-bottom: 15px;
        display: none;
        align-items: center;
        justify-content: space-between;
    }
    .btn-toggle-status {
        min-width: 110px;
    }
    .info-callout {
        border-left: 4px solid #17a2b8;
        background-color: #f1f9fa;
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 0 4px 4px 0;
        font-size: 0.9rem;
    }
</style>
@endpush

@section('admin-content')
    {{-- Info banner --}}
    <div class="info-callout">
        <i class="fas fa-info-circle text-info mr-1"></i>
        <strong>Stock Management Visibility:</strong> Items marked as <span class="badge badge-secondary">Inactive</span> will <strong>not</strong> appear in 
        <a href="{{ route('inventory.stock-management') }}" target="_blank" class="text-primary font-weight-bold">Inventory / Stock Management</a> (including stock summary, opening balances, create transactions, and reports).
    </div>

    {{-- Stats Cards --}}
    <div class="row">
        <div class="col-md-4">
            <div class="stat-card bg-light border">
                <div>
                    <div class="stat-label text-muted">Total Items</div>
                    <div class="stat-number text-dark" id="totalCountDisplay">{{ number_format($totalCount) }}</div>
                </div>
                <div class="stat-icon text-muted">
                    <i class="fas fa-boxes"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-success text-white">
                <div>
                    <div class="stat-label">Active Items</div>
                    <div class="stat-number" id="activeCountDisplay">{{ number_format($activeCount) }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-secondary text-white">
                <div>
                    <div class="stat-label">Inactive Items</div>
                    <div class="stat-number" id="inactiveCountDisplay">{{ number_format($inactiveCount) }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card card-outline card-secondary mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.items.index') }}" id="filterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4 mb-2">
                        <label for="search" class="form-label small font-weight-bold">Search Item</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="search" name="search"
                                   placeholder="Search by code or description..." value="{{ $search }}">
                            @if(!empty($search))
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearField('search')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="group" class="form-label small font-weight-bold">Product Group</label>
                        <select class="form-control form-control-sm" id="group" name="group">
                            <option value="">All Groups ({{ count($groups) }})</option>
                            @foreach($groups as $grp)
                                <option value="{{ $grp }}" {{ $selectedGroup === $grp ? 'selected' : '' }}>
                                    {{ $grp }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="status" class="form-label small font-weight-bold">Status</label>
                        <select class="form-control form-control-sm" id="status" name="status">
                            <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All Status</option>
                            <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                            <option value="inactive" {{ $selectedStatus === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                        </select>
                    </div>
                    <div class="col-md-1 mb-2">
                        <label for="per_page" class="form-label small font-weight-bold">Per Page</label>
                        <select class="form-control form-control-sm" id="per_page" name="per_page">
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                            <option value="250" {{ $perPage == 250 ? 'selected' : '' }}>250</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary btn-sm mr-1">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="{{ route('admin.items.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Batch Actions Bar --}}
    <div class="batch-bar" id="batchActionBar">
        <div>
            <span class="font-weight-bold text-primary mr-2" id="selectedCountText">0 items selected</span>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-outline-success mr-1" onclick="batchSetStatus('active')">
                <i class="fas fa-check-circle"></i> Mark Selected as Active
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="batchSetStatus('inactive')">
                <i class="fas fa-ban"></i> Mark Selected as Inactive
            </button>
        </div>
    </div>

    {{-- Items Table --}}
    <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered" id="itemsTable">
            <thead class="thead-light">
                <tr>
                    <th style="width: 40px;" class="text-center">
                        <input type="checkbox" id="selectAllCheckbox" title="Select all on this page">
                    </th>
                    <th style="width: 140px;">Item Code</th>
                    <th>Description</th>
                    <th style="width: 160px;">Group</th>
                    <th style="width: 80px;" class="text-center">Unit</th>
                    <th style="width: 100px;" class="text-right">Price</th>
                    <th style="width: 110px;" class="text-center">Status</th>
                    <th style="width: 130px;" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr id="row-{{ $item->ITEMNO }}">
                        <td class="text-center">
                            <input type="checkbox" class="item-checkbox" value="{{ $item->ITEMNO }}">
                        </td>
                        <td>
                            <strong class="font-monospace text-primary">{{ $item->ITEMNO }}</strong>
                        </td>
                        <td>
                            {{ $item->DESP ?? 'N/A' }}
                        </td>
                        <td>
                            @if(!empty($item->GROUP))
                                <span class="badge badge-light border">{{ $item->GROUP }}</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            {{ $item->UNIT ?? '-' }}
                        </td>
                        <td class="text-right font-weight-bold">
                            {{ number_format((float)($item->PRICE ?? 0), 2) }}
                        </td>
                        <td class="text-center" id="status-cell-{{ $item->ITEMNO }}">
                            @if($item->is_active)
                                <span class="status-badge badge badge-success">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            @else
                                <span class="status-badge badge badge-secondary">
                                    <i class="fas fa-ban"></i> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="text-center" id="action-cell-{{ $item->ITEMNO }}">
                            @if($item->is_active)
                                <button type="button"
                                        class="btn btn-xs btn-outline-danger btn-toggle-status"
                                        onclick="toggleItemStatus('{{ $item->ITEMNO }}', 'inactive')"
                                        title="Click to deactivate item">
                                    <i class="fas fa-ban"></i> Deactivate
                                </button>
                            @else
                                <button type="button"
                                        class="btn btn-xs btn-outline-success btn-toggle-status"
                                        onclick="toggleItemStatus('{{ $item->ITEMNO }}', 'active')"
                                        title="Click to activate item">
                                    <i class="fas fa-check-circle"></i> Activate
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                            No items found matching the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination & Count Info --}}
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
        <div class="text-muted small mb-2">
            Showing {{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }} of {{ $items->total() }} items
        </div>
        <div>
            {{ $items->links() }}
        </div>
    </div>

    {{-- Hidden Batch Form for fallback submission --}}
    <form id="batchActionForm" method="POST" action="{{ route('admin.items.batch-update-status') }}" style="display: none;">
        @csrf
        <input type="hidden" name="status" id="batchFormStatus">
        <div id="batchFormInputs"></div>
    </form>
@endsection

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function clearField(id) {
        document.getElementById(id).value = '';
        document.getElementById('filterForm').submit();
    }

    // Toggle single item status via AJAX
    function toggleItemStatus(itemno, targetStatus) {
        const row = document.getElementById(`row-${itemno}`);
        const actionCell = document.getElementById(`action-cell-${itemno}`);
        const originalActionHtml = actionCell.innerHTML;

        // Show spinner
        actionCell.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';

        fetch(`{{ url('admin/items') }}/${encodeURIComponent(itemno)}/toggle-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status: targetStatus })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateRowStatus(itemno, data.is_active);
                updateCounts(data.active_count, data.inactive_count);
                showToast(data.message, 'success');
            } else {
                actionCell.innerHTML = originalActionHtml;
                showToast(data.message || 'Error updating status', 'error');
            }
        })
        .catch(err => {
            console.error('Error updating status:', err);
            actionCell.innerHTML = originalActionHtml;
            showToast('Failed to update status. Please try again.', 'error');
        });
    }

    // Update row DOM after status change
    function updateRowStatus(itemno, isActive) {
        const statusCell = document.getElementById(`status-cell-${itemno}`);
        const actionCell = document.getElementById(`action-cell-${itemno}`);

        if (isActive) {
            statusCell.innerHTML = `
                <span class="status-badge badge badge-success">
                    <i class="fas fa-check-circle"></i> Active
                </span>
            `;
            actionCell.innerHTML = `
                <button type="button"
                        class="btn btn-xs btn-outline-danger btn-toggle-status"
                        onclick="toggleItemStatus('${itemno}', 'inactive')"
                        title="Click to deactivate item">
                    <i class="fas fa-ban"></i> Deactivate
                </button>
            `;
        } else {
            statusCell.innerHTML = `
                <span class="status-badge badge badge-secondary">
                    <i class="fas fa-ban"></i> Inactive
                </span>
            `;
            actionCell.innerHTML = `
                <button type="button"
                        class="btn btn-xs btn-outline-success btn-toggle-status"
                        onclick="toggleItemStatus('${itemno}', 'active')"
                        title="Click to activate item">
                    <i class="fas fa-check-circle"></i> Activate
                </button>
            `;
        }
    }

    // Update header metric counts
    function updateCounts(activeCount, inactiveCount) {
        const activeElem = document.getElementById('activeCountDisplay');
        const inactiveElem = document.getElementById('inactiveCountDisplay');
        if (activeElem && activeCount !== undefined) activeElem.textContent = Number(activeCount).toLocaleString();
        if (inactiveElem && inactiveCount !== undefined) inactiveElem.textContent = Number(inactiveCount).toLocaleString();
    }

    // Checkbox selection & batch actions
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const batchActionBar = document.getElementById('batchActionBar');
    const selectedCountText = document.getElementById('selectedCountText');

    function updateBatchBar() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        const count = checked.length;
        if (count > 0) {
            batchActionBar.style.display = 'flex';
            selectedCountText.textContent = `${count} item(s) selected`;
        } else {
            batchActionBar.style.display = 'none';
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBatchBar();
        });
    }

    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = Array.from(itemCheckboxes).every(c => c.checked);
            if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
            updateBatchBar();
        });
    });

    // Execute batch status update
    function batchSetStatus(targetStatus) {
        const checked = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);
        if (checked.length === 0) {
            alert('Please select at least one item.');
            return;
        }

        const actionWord = targetStatus === 'inactive' ? 'deactivate' : 'activate';
        if (!confirm(`Are you sure you want to ${actionWord} the ${checked.length} selected item(s)?`)) {
            return;
        }

        fetch(`{{ route('admin.items.batch-update-status') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                item_nos: checked,
                status: targetStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                checked.forEach(itemno => {
                    updateRowStatus(itemno, targetStatus === 'active');
                });
                updateCounts(data.active_count, data.inactive_count);
                // Clear selection
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                itemCheckboxes.forEach(cb => cb.checked = false);
                updateBatchBar();
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Batch update failed.', 'error');
            }
        })
        .catch(err => {
            console.error('Batch error:', err);
            showToast('Batch update error. Please try again.', 'error');
        });
    }

    // Simple toast notification
    function showToast(message, type) {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.minWidth = '280px';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-1"></i>
            ${message}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => {
            $(toast).alert('close');
        }, 3500);
    }
</script>
@endpush
