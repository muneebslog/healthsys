<?php

namespace App\Livewire\Concerns;

use App\Enums\UserRole;
use App\Models\Doctor;
use Illuminate\Support\Facades\Auth;

trait GuardsDoctorAccess
{
    protected function doctorProfile(): Doctor
    {
        $user = Auth::user();
        if (! config('hms.skip_role_page_guards') && $user->role !== UserRole::Doctor) {
            abort(403);
        }

        $doctor = $user->doctor;
        if (! $doctor) {
            abort(403, __('Your account is not linked to a doctor profile.'));
        }

        return $doctor;
    }
}
