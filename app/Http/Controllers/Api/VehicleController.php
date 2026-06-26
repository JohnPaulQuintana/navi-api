<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePart;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    use ApiResponse;

    public function dashboardStats()
    {
        $deviceInvited = User::query()
            ->where('role', 'technician')
            ->whereDate('created_at', today())
            ->get()
            ->map(fn ($user) => [
                'type' => 'device_invited',
                'label' => 'Device Invited',
                'title' => $user->name,
                'time' => $user->created_at->format('h:i A'),
                'created_at' => $user->created_at,
            ]);

        $vehiclesAdded = Vehicle::query()
            ->whereDate('created_at', today())
            ->get()
            ->map(fn ($vehicle) => [
                'type' => 'vehicle_added',
                'label' => 'Vehicle Added',
                'title' => "{$vehicle->plate_number} • {$vehicle->vehicle_name}",
                'time' => $vehicle->created_at->format('h:i A'),
                'created_at' => $vehicle->created_at,
            ]);

        $serviceRecords = VehiclePart::query()
            ->select(
                'vehicle_id',
                DB::raw('DATE(created_at) as service_date'),
                DB::raw('COUNT(*) as total_parts'),
                DB::raw('MAX(created_at) as created_at')
            )
            ->with('vehicle:id,plate_number')
            ->whereDate('created_at', today())
            ->groupBy('vehicle_id', DB::raw('DATE(created_at)'))
            ->get()
            ->map(fn ($record) => [
                'type' => 'service_record',
                'label' => 'Service Record Added',
                'title' => $record->vehicle?->plate_number,
                'total_parts' => $record->total_parts,
                'time' => Carbon::parse($record->created_at)
                    ->format('h:i A'),
                'created_at' => $record->created_at,
            ]);

        $activities = $deviceInvited
            ->concat($vehiclesAdded)
            ->concat($serviceRecords)
            ->sortByDesc('created_at')
            ->values();

        return $this->success([
            'total_vehicles' => Vehicle::count(),

            'service_records' => VehiclePart::query()
                ->select('vehicle_id')
                ->selectRaw('DATE(created_at) as service_date')
                ->distinct()
                ->count(),

            'total_technicians' => User::where(
                'role',
                'technician'
            )->count(),

            'today_activities' => $activities,
        ]);
    }

    public function index(Request $request)
    {
        $vehicles = Vehicle::query()
            ->when(
                $request->search,
                fn ($query, $search) => $query
                    ->where(
                        'plate_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'owner_name',
                        'like',
                        "%{$search}%"
                    )
            )
            ->latest()
            ->paginate(10);

        return $this->success(
            $vehicles
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ownerName' => ['required'],
            'address' => ['nullable'],
            'vehicle' => ['required'],
            'plateNumber' => ['required'],
            'date' => ['required', 'date'],

            'parts' => ['array'],
            'parts.*.name' => ['nullable'],
            'parts.*.price' => ['nullable'],
        ]);

        DB::transaction(function () use ($data) {

            $vehicle = Vehicle::create([
                'owner_name' => $data['ownerName'],
                'address' => $data['address'],
                'vehicle_name' => $data['vehicle'],
                'plate_number' => $data['plateNumber'],
                'service_date' => $data['date'],
            ]);

            foreach ($data['parts'] ?? [] as $part) {

                if (empty($part['name'])) {
                    continue;
                }

                DB::table('vehicle_parts')->insert([
                    'vehicle_id' => $vehicle->id,
                    'part_name' => $part['name'],
                    'price' => $part['price'] ?: null,
                    'created_at' => $data['date'].' 00:00:00',
                    'updated_at' => $data['date'].' 00:00:00',
                ]);
            }
        });

        return $this->success(
            null,
            'Vehicle created successfully'
        );
    }

    public function show($id)
    {
        $vehicle = Vehicle::with('parts')
            ->findOrFail($id);

        $useVehicleServiceDate = $vehicle->parts->every(function ($part) use ($vehicle) {
            return $part->created_at->toDateString() === $vehicle->created_at->toDateString();
        });

        $timeline = $vehicle->parts
            ->groupBy(fn ($part) => $part->created_at->format('Y-m-d'))
            ->map(function ($parts, $date) use ($vehicle, $useVehicleServiceDate) {

                $displayDate = $useVehicleServiceDate
                    ? $vehicle->service_date
                    : $date;

                return [
                    'date' => $displayDate,

                    'total_amount' => $parts->sum('price'),

                    'parts' => $parts->map(fn ($part) => [
                        'id' => $part->id,
                        'name' => $part->part_name,
                        'price' => $part->price,
                        'created_at' => $part->created_at,
                    ])->values(),
                ];
            })
            ->sortByDesc('date')
            ->values();

        return $this->success([
            'vehicle' => [
                'id' => $vehicle->id,
                'owner_name' => $vehicle->owner_name,
                'address' => $vehicle->address,
                'vehicle_name' => $vehicle->vehicle_name,
                'plate_number' => $vehicle->plate_number,
                'service_date' => $vehicle->service_date,
            ],

            'stats' => [
                'total_parts' => $vehicle->parts->count(),
                'total_amount' => $vehicle->parts->sum('price'),
            ],

            'timeline' => $timeline,
        ]);
    }

    public function searchByPlate(
        string $plateNumber
    ) {
        $vehicle = Vehicle::with('parts')
            ->where(
                'plate_number',
                $plateNumber
            )
            ->first();

        if (! $vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        $timeline = $vehicle->parts
            ->sortByDesc('created_at')
            ->groupBy(fn ($part) => $part->created_at->format('Y-m-d'))
            ->map(function ($parts, $date) {
                return [
                    'date' => $date,
                    'parts' => $parts->map(fn ($part) => [
                        'id' => $part->id,
                        'name' => $part->part_name,
                        'price' => $part->price,
                        'created_at' => $part->created_at,
                    ])->values(),
                    'total_amount' => $parts->sum('price'),
                ];
            })
            ->values();

        return $this->success([
            'vehicle' => $vehicle,
            'timeline' => $timeline,
        ]);
    }

    public function addNewHistory(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => ['required'],
            'serviceDate' => ['nullable'],
            'parts' => ['array'],
            'parts.*.name' => ['required'],
            'parts.*.price' => ['nullable'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['parts'] ?? [] as $part) {

                if (empty($part['name'])) {
                    continue;
                }

                VehiclePart::create([
                    'vehicle_id' => $data['vehicle_id'],
                    'part_name' => $part['name'],
                    'price' => $part['price'] ?: null,
                    'created_at' => ! empty($data['serviceDate'])
                        ? $data['serviceDate'].' 00:00:00'
                        : now(),
                    'updated_at' => ! empty($data['serviceDate'])
                        ? $data['serviceDate'].' 00:00:00'
                        : now(),
                ]);
            }
        });

        return $this->success(
            null,
            'Vehicle history created successfully'
        );
    }
}
