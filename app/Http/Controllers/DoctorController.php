<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function store(Request $request)
    {
        Doctor::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['required', 'string', 'max:255'],
            'schedule' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('status', 'Doctor registered successfully.');
    }
}