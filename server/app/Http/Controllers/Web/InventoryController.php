<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index()
    {
        $items = InventoryItem::query()
            ->where('operator_id', Auth::id())
            ->with('vehicle')
            ->latest('transaction_date')
            ->latest()
            ->get();

        $vehicles = Vehicle::query()
            ->where('operator_id', Auth::id())
            ->where('status', 'active')
            ->get();

        $summary = [
            'total_import' => $items->where('type', 'import')->sum(fn ($i) => $i->quantity * $i->unit_price),
            'total_export' => $items->where('type', 'export')->sum(fn ($i) => $i->quantity * $i->unit_price),
            'by_category'  => $items->groupBy('category')->map(fn ($g) => [
                'import' => $g->where('type', 'import')->sum('quantity'),
                'export' => $g->where('type', 'export')->sum('quantity'),
            ]),
        ];

        return view('operator.inventory', compact('items', 'vehicles', 'summary'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'unit'             => ['required', 'string', 'max:50'],
            'quantity'         => ['required', 'numeric', 'min:0.01'],
            'unit_price'       => ['required', 'numeric', 'min:0'],
            'type'             => ['required', Rule::in(['import', 'export'])],
            'category'         => ['required', 'string', 'max:50'],
            'vehicle_id'       => ['nullable', 'exists:vehicles,id'],
            'note'             => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
        ]);

        // Đảm bảo vehicle thuộc operator hiện tại
        if (! empty($validated['vehicle_id'])) {
            $vehicle = Vehicle::query()
                ->where('operator_id', Auth::id())
                ->find($validated['vehicle_id']);
            if (! $vehicle) {
                return back()->withErrors(['vehicle_id' => 'Xe không hợp lệ.']);
            }
        }

        InventoryItem::query()->create(array_merge($validated, [
            'operator_id' => Auth::id(),
        ]));

        return redirect()->route('operator.inventory')->with('success', 'Đã ghi nhận giao dịch vật tư.');
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        if ($inventoryItem->operator_id !== Auth::id()) {
            abort(403);
        }

        $inventoryItem->delete();

        return redirect()->route('operator.inventory')->with('success', 'Đã xóa bản ghi.');
    }
}
