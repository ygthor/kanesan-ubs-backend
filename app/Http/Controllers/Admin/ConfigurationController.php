<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $stockRequestEmail = Configuration::getValue('STOCK_REQUEST_EMAIL', config('app.admin_email'));
        $eInvoiceEmail = Configuration::getValue('EINVOICE_EMAIL', config('app.admin_email'));

        return view('admin.configurations.index', compact('stockRequestEmail', 'eInvoiceEmail'));
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'stock_request_email' => 'nullable|string|max:1000',
            'einvoice_email' => 'nullable|string|max:1000',
        ]);

        Configuration::setValue('STOCK_REQUEST_EMAIL', trim((string) ($validated['stock_request_email'] ?? '')) ?: null);
        Configuration::setValue('EINVOICE_EMAIL', trim((string) ($validated['einvoice_email'] ?? '')) ?: null);

        return redirect()
            ->route('admin.configurations.index')
            ->with('success', 'Configuration updated successfully.');
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized.');
        }
    }
}

