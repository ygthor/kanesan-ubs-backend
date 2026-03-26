@extends('layouts.app')

@section('title', 'Create Stock Requests (Test)')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Create Stock Requests (Test)</h3>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('test.stock-requests.store') }}">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="user_id">Submit As User</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">-- Select User --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                        #{{ $user->id }} - {{ $user->name ?: $user->username }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="mb-0">Items</label>
                            <button type="button" class="btn btn-sm btn-secondary" id="add-item-row">Add Item</button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="items-table">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Item No</th>
                                        <th style="width: 40%;">Description</th>
                                        <th style="width: 12%;">Unit</th>
                                        <th style="width: 18%;">Requested Qty</th>
                                        <th style="width: 10%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="items-body"></tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary mt-2">Submit Test Stock Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const body = document.getElementById('items-body');
            const addBtn = document.getElementById('add-item-row');

            function buildRow(index, defaults = {}) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" name="items[${index}][item_no]" class="form-control" value="${defaults.item_no || ''}" required></td>
                    <td><input type="text" name="items[${index}][description]" class="form-control" value="${defaults.description || ''}"></td>
                    <td><input type="text" name="items[${index}][unit]" class="form-control" value="${defaults.unit || ''}"></td>
                    <td><input type="number" step="0.0001" min="0.0001" name="items[${index}][requested_qty]" class="form-control" value="${defaults.requested_qty || ''}" required></td>
                    <td><button type="button" class="btn btn-sm btn-danger remove-row">Remove</button></td>
                `;
                return tr;
            }

            function addRow(defaults = {}) {
                const index = body.querySelectorAll('tr').length;
                body.appendChild(buildRow(index, defaults));
            }

            addBtn.addEventListener('click', function () {
                addRow();
            });

            body.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-row')) {
                    e.target.closest('tr').remove();
                }
            });

            @if(old('items') && is_array(old('items')))
                const oldItems = @json(array_values(old('items')));
                oldItems.forEach(item => addRow(item));
            @else
                addRow({ item_no: 'PBBT', description: 'Sample Item A', unit: 'PCS', requested_qty: '10' });
                addRow({ item_no: 'PBKT', description: 'Sample Item B', unit: 'PCS', requested_qty: '5' });
            @endif
        })();
    </script>
@endpush

