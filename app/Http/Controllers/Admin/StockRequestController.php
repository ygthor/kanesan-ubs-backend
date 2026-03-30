<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockRequestController extends Controller
{
    /**
     * List all stock requests for admin.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $perPage = (int) $request->input('per_page', 20);
        if (!in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }

        $query = StockRequest::with(['user', 'items'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        if ($request->filled('agent_id')) {
            $query->where('user_id', $request->integer('agent_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->paginate($perPage)->withQueryString();

        $agents = User::select('id', 'name', 'username', 'email')
            ->where(function ($query) {
                $query->where('username', '!=', 'KBS')
                    ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function ($user) {
                return !$user->hasRole('admin');
            })
            ->values();

        return view('admin.stock-requests.index', compact('requests', 'agents', 'perPage'));
    }

    /**
     * Show a single stock request for admin to approve/reject.
     */
    public function show($id)
    {
        $this->authorizeAdmin();

        [$stockRequest, $groupedItems] = $this->getStockRequestWithGroupedItems($id);

        return view('admin.stock-requests.show', compact('stockRequest', 'groupedItems'));
    }

    public function exportPdf($id)
    {
        $this->authorizeAdmin();

        [$stockRequest, $groupedItems] = $this->getStockRequestWithGroupedItems($id);

        $filename = 'stock_request_' . $stockRequest->id . '_' . now()->format('Ymd_His') . '.pdf';
        $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            public string $printedAt = '';

            public function Footer()
            {
                $this->SetY(-7);
                $this->SetFont('helvetica', '', 8);
                $this->Cell(0, 4, 'Printed at ' . $this->printedAt, 0, 0, 'L');
                $this->Cell(0, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        };
        $pdf->printedAt = now()->format('Y-m-d H:i:s');

        $pdf->SetCreator('KBS System');
        $pdf->SetAuthor(auth()->user()->name ?? 'System');
        $pdf->SetTitle('Stock Request #' . $stockRequest->id);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();

        $formatQty = function ($qty): string {
            $v = (float) $qty;
            if (abs($v - round($v)) < 0.00001) {
                return (string) (int) round($v);
            }
            return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        };

        $left = $pdf->getMargins()['left'];
        $right = $pdf->getMargins()['right'];
        $pageWidth = $pdf->getPageWidth() - $left - $right;
        $colCode = 24.0;
        $colDesc = 88.0;
        $colUnit = 18.0;
        $colReq = 25.0;
        $colApp = $pageWidth - ($colCode + $colDesc + $colUnit + $colReq);

        $drawTableHeader = function () use ($pdf, $pageWidth, $colCode, $colDesc, $colUnit, $colReq, $colApp) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(243, 244, 246);
            $pdf->Cell($colCode, 8, 'ITEM CODE', 1, 0, 'L', true);
            $pdf->Cell($colDesc, 8, 'DESCRIPTION', 1, 0, 'L', true);
            $pdf->Cell($colUnit, 8, 'UNIT', 1, 0, 'L', true);
            $pdf->Cell($colReq, 8, 'REQUESTED', 1, 0, 'R', true);
            $pdf->Cell($colApp, 8, 'APPROVED', 1, 1, 'R', true);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetX(($pdf->getPageWidth() - $pageWidth) / 2);
        };

        $ensureSpace = function (float $neededHeight) use ($pdf, $drawTableHeader) {
            $bottomLimit = $pdf->getPageHeight() - $pdf->getMargins()['bottom'];
            if (($pdf->GetY() + $neededHeight) > $bottomLimit) {
                $pdf->AddPage();
                $drawTableHeader();
            }
        };

        // Title + summary
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'STOCK REQUEST #' . $stockRequest->id, 0, 1, 'C');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(24, 6, 'Agent:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(66, 6, $stockRequest->user?->name ?? $stockRequest->user?->username ?? 'Unknown', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(24, 6, 'Status:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, strtoupper((string) $stockRequest->status), 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(24, 6, 'Submitted:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(66, 6, $stockRequest->created_at?->format('d M Y H:i') ?? 'N/A', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(24, 6, 'Handled By:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(
            0,
            6,
            $stockRequest->approvedBy?->name ?? $stockRequest->approvedBy?->username ?? '-',
            0,
            1,
            'L'
        );
        $pdf->Ln(2);

        $drawTableHeader();

        foreach ($groupedItems as $groupName => $items) {
            $ensureSpace(7);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(249, 250, 251);
            $pdf->Cell($pageWidth, 7, 'Group :' . $groupName, 1, 1, 'L', true);

            foreach ($items as $item) {
                $desc = (string) ($item->description ?? '-');
                $descHeight = $pdf->getStringHeight($colDesc, $desc);
                $rowHeight = max(6.5, $descHeight + 1);
                $ensureSpace($rowHeight);

                $x = $pdf->GetX();
                $y = $pdf->GetY();
                $pdf->SetFont('helvetica', '', 8.5);
                $pdf->Cell($colCode, $rowHeight, (string) $item->item_no, 1, 0, 'L');
                $pdf->MultiCell($colDesc, $rowHeight, $desc, 1, 'L', false, 0);
                $pdf->Cell($colUnit, $rowHeight, (string) ($item->unit ?? '-'), 1, 0, 'L');
                $pdf->Cell($colReq, $rowHeight, $formatQty($item->requested_qty), 1, 0, 'R');
                $approvedText = $item->approved_qty !== null ? $formatQty($item->approved_qty) : '-';
                $pdf->Cell($colApp, $rowHeight, $approvedText, 1, 0, 'R');
                $pdf->SetXY($x, $y + $rowHeight);
            }
        }

        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Admin approves a stock request, optionally adjusting quantities.
     */
    public function approve(Request $request, $id)
    {
        $this->authorizeAdmin();

        $stockRequest = StockRequest::with('items')->findOrFail($id);

        $validated = $request->validate([
            'admin_notes'             => 'nullable|string|max:500',
            'items'                   => 'required|array',
            'items.*.id'              => 'required|integer',
            'items.*.approved_qty'    => 'required|numeric|min:0',
        ]);

        // Update each item's approved qty
        $itemsKeyed = collect($validated['items'])->keyBy('id');
        foreach ($stockRequest->items as $item) {
            if (isset($itemsKeyed[$item->id])) {
                $item->update(['approved_qty' => $itemsKeyed[$item->id]['approved_qty']]);
            }
        }

        $stockRequest->update([
            'status'      => 'approved',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('admin.stock-requests.index')
            ->with('success', 'Stock request #' . $id . ' has been approved.');
    }

    /**
     * Admin rejects a stock request.
     */
    public function reject(Request $request, $id)
    {
        $this->authorizeAdmin();

        $stockRequest = StockRequest::findOrFail($id);

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $stockRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('admin.stock-requests.index')
            ->with('success', 'Stock request #' . $id . ' has been rejected.');
    }

    private function authorizeAdmin()
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized.');
        }
    }

    private function getStockRequestWithGroupedItems($id): array
    {
        $stockRequest = StockRequest::with(['user', 'items', 'approvedBy'])->findOrFail($id);
        $itemNos = $stockRequest->items
            ->pluck('item_no')
            ->filter()
            ->unique()
            ->values();

        $itemGroupByNo = $itemNos->isNotEmpty()
            ? DB::table('icitem')
                ->whereIn('ITEMNO', $itemNos)
                ->pluck('GROUP', 'ITEMNO')
            : collect();

        $items = $stockRequest->items
            ->map(function ($item) use ($itemGroupByNo) {
                $group = trim((string) ($itemGroupByNo[$item->item_no] ?? ''));
                $item->item_group = $group !== '' ? $group : 'N/A';
                return $item;
            })
            ->sortBy(fn ($item) => ($item->item_group ?? 'N/A') . '|' . (string) $item->item_no)
            ->values();

        $stockRequest->setRelation('items', $items);
        $groupedItems = $items->groupBy(fn ($item) => $item->item_group ?? 'N/A');

        return [$stockRequest, $groupedItems];
    }
}
