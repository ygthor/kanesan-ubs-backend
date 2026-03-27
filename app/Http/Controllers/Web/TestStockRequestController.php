<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Configuration;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestStockRequestController extends Controller
{
    public function create()
    {
        $users = User::query()
            ->select('id', 'name', 'username', 'email')
            ->orderBy('name')
            ->orderBy('username')
            ->get();

        return view('test.create-stock-requests', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'                  => 'required|exists:users,id',
            'notes'                    => 'nullable|string|max:500',
            'items'                    => 'required|array|min:1',
            'items.*.item_no'          => 'required|string|max:50',
            'items.*.description'      => 'nullable|string|max:255',
            'items.*.unit'             => 'nullable|string|max:20',
            'items.*.requested_qty'    => 'required|numeric|min:0.0001',
        ]);

        $submittedBy = User::findOrFail($validated['user_id']);

        $stockRequest = DB::transaction(function () use ($validated, $submittedBy) {
            $createdRequest = StockRequest::create([
                'user_id' => $submittedBy->id,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                StockRequestItem::create([
                    'stock_request_id' => $createdRequest->id,
                    'item_no' => $item['item_no'],
                    'description' => $item['description'] ?? null,
                    'unit' => $item['unit'] ?? null,
                    'requested_qty' => $item['requested_qty'],
                ]);
            }

            return $createdRequest;
        });

        $stockRequest->load(['items', 'user']);

        $recipients = $this->resolveStockRequestNotificationRecipients();
        $this->createStockRequestNotifications($recipients, $stockRequest, $submittedBy);
        $this->sendStockRequestSubmittedEmails($recipients, $stockRequest, $submittedBy);

        return redirect()
            ->route('test.stock-requests.create')
            ->with('success', 'Stock request #' . $stockRequest->id . ' created successfully.');
    }

    private function resolveStockRequestNotificationRecipients()
    {
        return User::query()
            ->select('id', 'name', 'username', 'email')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($query) {
                $query->where(function ($kbsQuery) {
                    $kbsQuery->where('username', 'KBS')
                        ->orWhere('email', 'KBS@kanesan.my');
                })->orWhereHas('roles', function ($roleQuery) {
                    $roleQuery->where('roles.role_id', 'admin');
                });
            })
            ->get();
    }

    private function createStockRequestNotifications($recipients, StockRequest $stockRequest, User $submittedBy): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $submitterName = $submittedBy->name ?: ($submittedBy->username ?: ('User #' . $submittedBy->id));
        $title = 'New Stock Request Submitted';
        $message = 'Stock request #' . $stockRequest->id . ' submitted by ' . $submitterName . '.';

        foreach ($recipients as $recipient) {
            AppNotification::create([
                'user_id' => $recipient->id,
                'stock_request_id' => $stockRequest->id,
                'type' => 'stock_request_submitted',
                'title' => $title,
                'message' => $message,
                'data' => [
                    'stock_request_id' => $stockRequest->id,
                    'submitted_by_id' => $submittedBy->id,
                    'submitted_by_name' => $submitterName,
                    'status' => $stockRequest->status,
                    'items_count' => $stockRequest->items->count(),
                ],
                'is_read' => false,
            ]);
        }
    }

    private function sendStockRequestSubmittedEmails($recipients, StockRequest $stockRequest, User $submittedBy): void
    {
        $configuredEmails = Configuration::getEmailList('STOCK_REQUEST_EMAIL');

        if ($configuredEmails->isNotEmpty()) {
            $emails = $configuredEmails;
        } else {
            $emails = $recipients->pluck('email')->filter()->unique()->values();
            $fallbackAdminEmail = config('app.admin_email');
            if (!empty($fallbackAdminEmail)) {
                $emails->push($fallbackAdminEmail);
                $emails = $emails->unique()->values();
            }
        }

        if ($emails->isEmpty()) {
            return;
        }

        foreach ($emails as $email) {
            try {
                Mail::send('emails.stock-request-submitted', [
                    'stockRequest' => $stockRequest,
                    'submittedBy' => $submittedBy,
                ], function ($message) use ($email, $stockRequest) {
                    $message
                        ->to($email)
                        ->subject('New Stock Request #' . $stockRequest->id . ' Submitted');
                });
            } catch (\Throwable $e) {
                Log::error('Failed to send stock request submission email.', [
                    'stock_request_id' => $stockRequest->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
