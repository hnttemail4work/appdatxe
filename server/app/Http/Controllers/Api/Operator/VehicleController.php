<?php

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Operator\VehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $operatorId = $request->user()->id;

        $vehicles = Vehicle::where('operator_id', $operatorId)
            ->with(['schedules'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $vehicles->items(),
            'pagination' => [
                'total' => $vehicles->total(),
                'per_page' => $vehicles->perPage(),
                'current_page' => $vehicles->currentPage(),
                'last_page' => $vehicles->lastPage(),
            ],
        ]);
    }

    public function store(VehicleRequest $request)
    {
        $operatorId = $request->user()->id;

        $vehicle = Vehicle::create([
            'operator_id' => $operatorId,
            'license_plate' => $request->input('license_plate'),
            'type' => $request->input('type'),
            'capacity' => $request->input('capacity'),
            'status' => $request->input('status', 'active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle created successfully',
            'data' => $vehicle,
        ], 201);
    }

    public function show(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->operator_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $vehicle->load(['schedules' => function ($query) {
            $query->where('departure_time', '>=', now());
        }]);

        return response()->json([
            'success' => true,
            'data' => $vehicle,
        ]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle)
    {
        if ($vehicle->operator_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $vehicle->update([
            'license_plate' => $request->input('license_plate', $vehicle->license_plate),
            'type' => $request->input('type', $vehicle->type),
            'capacity' => $request->input('capacity', $vehicle->capacity),
            'status' => $request->input('status', $vehicle->status),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle,
        ]);
    }

    public function destroy(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->operator_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($vehicle->schedules()->where('status', '!=', 'completed')->where('status', '!=', 'cancelled')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete vehicle with active schedules',
            ], 422);
        }

        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully',
        ]);
    }
}
