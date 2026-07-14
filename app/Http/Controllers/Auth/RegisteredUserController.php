<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $barangays = Barangay::active()
            ->with(['puroks' => fn ($query) => $query->active()->orderBy('purok_number')])
            ->orderBy('name')
            ->get();

        return view('auth.register', compact('barangays'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'requested_role' => ['required', 'in:bhw,bns'],
            'requested_barangay_id' => ['required', 'exists:barangays,id'],
            'terms' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->requested_role,
            'requested_role' => $request->requested_role,
            'requested_barangay_id' => $request->requested_barangay_id,
            'requested_purok_id' => null,
            'approval_status' => User::APPROVAL_PENDING,
            'registered_via' => 'self',
            'is_active' => false,
        ]);

        event(new Registered($user));

        return redirect()->route('login')
            ->with('status', 'Registration submitted successfully. Please wait for your barangay secretary to approve your account and finalize the assignment.');
    }
}
