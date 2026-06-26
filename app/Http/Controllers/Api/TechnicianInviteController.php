<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TechnicianInvite;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TechnicianInviteController extends Controller
{
    use ApiResponse;

    public function devices()
    {
        $devices = User::query()
            ->where('role', '!=', 'admin')
            ->latest()
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => ucfirst($user->role),
                'status' => $user->is_active ? 'Connected' : 'Offline',
                'paired_at' => $user->updated_at->format('M d, Y h:i A'),
            ]);

        return $this->success($devices);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $email = $data['email'] ?? null;

        if (empty($email)) {
            do {
                $email = 'tech-'.Str::lower(Str::random(12)).'@local.device';
            } while (
                TechnicianInvite::where('email', $email)->exists() ||
                User::where('email', $email)->exists()
            );
        }

        // Remove any existing active invite for this email
        TechnicianInvite::where('email', $email)
            ->whereNull('used_at')
            ->delete();

        $invite = TechnicianInvite::create([
            'name' => $data['name'],
            'email' => $email,
            'token' => Str::uuid()->toString(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return $this->success([
            'token' => $invite->token,
            'expires_at' => $invite->expires_at,
        ], 'Invite generated');
    }

    public function accept(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $invite = TechnicianInvite::where(
            'token',
            $data['token']
        )->first();

        if (! $invite) {
            return $this->error(
                'Invalid invite',
                404
            );
        }

        if ($invite->used_at) {
            return $this->error(
                'Invite already used',
                400
            );
        }

        if ($invite->expires_at->isPast()) {
            return $this->error(
                'Invite expired',
                400
            );
        }

        $user = User::firstOrCreate(
            [
                'email' => $invite->email,
            ],
            [
                'name' => $invite->name,
                'password' => Hash::make('Technician@123'),
                'role' => 'technician',
                'is_active' => true,
            ]
        );

        $invite->update([
            'used_at' => now(),
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 'Technician paired successfully');
    }
}
