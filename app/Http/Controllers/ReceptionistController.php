<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\CashReceipt;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceptionistController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $doctors = Doctor::orderBy('name')->get();
        $patients = Patient::orderBy('name')->get();

        $todaysAppointments = Appointment::with(['patient', 'doctor'])
            ->whereDate('appointment_date', $today)
            ->orderBy('appointment_time')
            ->get();

        $todaysTokens = Token::with(['patient', 'doctor'])
            ->whereDate('token_date', $today)
            ->orderBy('id')
            ->get();

        $todaysCash = CashReceipt::with('patient')
            ->whereDate('created_at', $today)
            ->orderByDesc('id')
            ->get();

        $kpis = [
            'appointments_today' => $todaysAppointments->count(),
            'tokens_today' => $todaysTokens->count(),
            'cash_today' => $todaysCash->sum('amount'),
            'doctors_available' => $doctors->where('status', 'Available')->count(),
        ];

        $printToken = session('print_token_id') ? Token::with(['patient', 'doctor'])->find(session('print_token_id')) : null;

        return view('receptionist', compact('doctors', 'patients', 'todaysAppointments', 'todaysTokens', 'todaysCash', 'kpis', 'printToken'));
    }

    public function appointmentsPage()
    {
        $appointments = Appointment::with(['patient', 'doctor', 'token'])->orderByDesc('appointment_date')->orderBy('appointment_time')->limit(100)->get();
        $patients = Patient::orderBy('name')->get();
        $doctors = Doctor::orderBy('name')->get();

        $printToken = session('print_token_id') ? Token::with(['patient', 'doctor'])->find(session('print_token_id')) : null;

        return view('appointments', compact('appointments', 'patients', 'doctors', 'printToken'));
    }

    public function appointmentsLive()
    {
        return response()->json(Appointment::with(['patient', 'doctor', 'token'])->orderByDesc('appointment_date')->orderBy('appointment_time')->limit(100)->get());
    }

    public function updateAppointmentStatus(Request $request, Appointment $appointment)
    {
        $status = $request->validate(['status' => ['required', 'in:Confirmed,Waiting,Cancelled']]);

        DB::transaction(function () use ($appointment, $status) {
            $appointment->update($status);
        });

        $appointment->load('patient', 'token');

        return redirect()->route('receptionist.appointments.token', $appointment)
            ->with('status', "Appointment saved for {$appointment->patient->name}.");
    }

    public function storeAppointment(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'doctor_id' => ['required', 'exists:doctors,id'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required'],
            'department' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($data['patient_id']) && empty($data['patient_name'])) {
            return back()->withErrors(['patient_name' => 'Select an existing patient or enter a new patient name.'])->withInput();
        }

        $appointment = DB::transaction(function () use ($data) {
            $patient = $this->resolvePatient($data);
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $data['doctor_id'],
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'department' => $data['department'] ?? null,
                'type' => $data['type'] ?? null,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'Confirmed',
            ]);

            return $appointment->load('patient', 'token');
        });

        return redirect()->route('receptionist.appointments.token', $appointment)
            ->with('status', "Appointment booked for {$appointment->patient->name}. Generate a token when the patient is ready.");
    }

    public function appointmentToken(Appointment $appointment)
    {
        $appointment->load(['patient', 'doctor', 'token']);

        return view('appointment-token', compact('appointment'));
    }

    public function generateAppointmentToken(Appointment $appointment)
    {
        $token = DB::transaction(fn () => $this->ensureAppointmentToken($appointment->load('patient', 'doctor')));

        return redirect()->route('receptionist.appointments.index')
            ->with('status', "Token {$token->token_number} generated for {$appointment->patient->name}.")
            ->with('print_token_id', $token->id);
    }

    private function ensureAppointmentToken(Appointment $appointment): Token
    {
        if ($appointment->token) {
            return $appointment->token;
        }

        $tokenDate = $appointment->appointment_date->toDateString();
        $sequence = Token::whereDate('token_date', $tokenDate)->lockForUpdate()->count() + 1;

        return Token::create([
            'token_number' => 'T-' . date('Ymd', strtotime($tokenDate)) . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'token_date' => $tokenDate,
            'status' => 'Waiting',
        ]);
    }

    public function storeToken(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'patient_name' => ['required_without:patient_id', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'doctor_id' => ['required', 'exists:doctors,id'],
        ]);

        $patient = $this->resolvePatient($data);

        $today = now()->toDateString();
        $sequence = Token::whereDate('token_date', $today)->count() + 1;
        $tokenNumber = 'T-' . now()->format('Ymd') . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        $token = Token::create([
            'token_number' => $tokenNumber,
            'patient_id' => $patient->id,
            'doctor_id' => $data['doctor_id'],
            'token_date' => $today,
            'status' => 'Waiting',
        ]);

        return back()->with('status', "Token {$token->token_number} generated for {$patient->name}.")
            ->with('print_token_id', $token->id);
    }

    public function storeCash(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'patient_name' => ['required_without:patient_id', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:255'],
        ]);

        $patient = $this->resolvePatient($data);

        $receipt = CashReceipt::create([
            'receipt_number' => 'CR-' . now()->format('Ymd') . '-' . str_pad((string) (CashReceipt::whereDate('created_at', now()->toDateString())->count() + 1), 3, '0', STR_PAD_LEFT),
            'patient_id' => $patient->id,
            'amount' => $data['amount'],
            'purpose' => $data['purpose'] ?? 'Consultation Fee',
            'received_by' => $data['received_by'] ?? 'Front Desk',
        ]);

        return back()->with('status', "Cash receipt {$receipt->receipt_number} recorded for {$patient->name}.");
    }

    public function patientHistory(Patient $patient)
    {
        $patient->load([
            'doctor',
            'appointments' => fn ($query) => $query->with('doctor')->orderByDesc('appointment_date'),
            'tokens' => fn ($query) => $query->with('doctor')->orderByDesc('token_date'),
            'cashReceipts' => fn ($query) => $query->orderByDesc('created_at'),
            'admissions' => fn ($query) => $query->with('doctor')->orderByDesc('admitted_on'),
            'invoices' => fn ($query) => $query->orderByDesc('created_at'),
        ]);

        return response()->json([
            'patient' => $patient,
            'appointments' => $patient->appointments,
            'tokens' => $patient->tokens,
            'cash_receipts' => $patient->cashReceipts,
            'admissions' => $patient->admissions,
            'invoices' => $patient->invoices,
        ]);
    }

    private function resolvePatient(array $data): Patient
    {
        if (! empty($data['patient_id'])) {
            return Patient::findOrFail($data['patient_id']);
        }

        return DB::transaction(function () use ($data) {
            $sequence = Patient::count() + 1;

            return Patient::create([
                'patient_code' => 'PT-' . str_pad((string) (10000 + $sequence), 5, '0', STR_PAD_LEFT),
                'name' => $data['patient_name'],
                'gender' => $data['gender'] ?? 'Other',
                'age' => $data['age'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => 'Active',
                'last_visit' => now()->toDateString(),
            ]);
        });
    }
}
