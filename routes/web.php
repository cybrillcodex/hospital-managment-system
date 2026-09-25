<?php

use App\Http\Controllers\ReceptionistController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffController;
use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard', [
        'dashboardStats' => dashboardStats(),
        'patients' => Patient::orderBy('name')->get(),
        'doctors' => Doctor::orderBy('name')->get(),
    ]);
});

Route::get('/dashboard/stats', function () {
    return response()->json(dashboardStats());
})->name('dashboard.stats');

Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::post('/doctors', [DoctorController::class, 'store'])->name('doctors.store');

Route::get('/pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
Route::get('/prescriptions', [PharmacyController::class, 'prescriptionsPage'])->name('prescriptions.index');
Route::get('/prescriptions/live', [PharmacyController::class, 'prescriptionsLive'])->name('prescriptions.live');
Route::patch('/prescriptions/{prescription}/status', [PharmacyController::class, 'updatePrescriptionStatus'])->name('prescriptions.status');
Route::post('/pharmacy/medicines', [PharmacyController::class, 'storeMedicine'])->name('pharmacy.medicines.store');
Route::post('/pharmacy/suppliers', [PharmacyController::class, 'storeSupplier'])->name('pharmacy.suppliers.store');
Route::post('/pharmacy/purchases', [PharmacyController::class, 'storePurchase'])->name('pharmacy.purchases.store');
Route::post('/pharmacy/sales', [PharmacyController::class, 'storeSale'])->name('pharmacy.sales.store');
Route::post('/pharmacy/prescriptions', [PharmacyController::class, 'storePrescription'])->name('pharmacy.prescriptions.store');
Route::post('/pharmacy/returns', [PharmacyController::class, 'storeReturn'])->name('pharmacy.returns.store');
Route::post('/pharmacy/staff', [PharmacyController::class, 'storeStaff'])->name('pharmacy.staff.store');

Route::prefix('laboratory')->name('laboratory.')->group(function () {
    Route::get('/', [LaboratoryController::class, 'index'])->name('index');
    Route::post('/catalogue', [LaboratoryController::class, 'storeTest'])->name('catalogue.store');
    Route::patch('/catalogue/{test}', [LaboratoryController::class, 'updateTest'])->name('catalogue.update');
    Route::post('/requests', [LaboratoryController::class, 'storeRequest'])->name('requests.store');
    Route::patch('/requests/{labRequest}/assignment', [LaboratoryController::class, 'assignRequest'])->name('requests.assign');
    Route::post('/staff', [LaboratoryController::class, 'storeStaff'])->name('staff.store');
    Route::post('/requests/{labRequest}/report', [LaboratoryController::class, 'storeReport'])->name('reports.store');
    Route::patch('/reports/{report}/verify', [LaboratoryController::class, 'verifyReport'])->name('reports.verify');
    Route::post('/bills/{bill}/payments', [LaboratoryController::class, 'storePayment'])->name('payments.store');
});

function dashboardStats(): array
{
    $today = today();

    return [
        'patients' => Patient::count(),
        'new_patients' => Patient::whereDate('created_at', $today)->count(),
        'active_cases' => Patient::where('status', 'Active')->count(),
        'appointments' => Appointment::whereDate('appointment_date', $today)->where('status', '!=', 'Cancelled')->count(),
        'waiting_appointments' => Appointment::whereDate('appointment_date', $today)->where('status', 'Waiting')->count(),
        'completed_appointments' => Appointment::whereDate('appointment_date', $today)->where('status', 'Confirmed')->count(),
        'doctors' => Doctor::count(),
        'doctors_on_duty' => Doctor::whereIn('status', ['Available', 'In clinic'])->count(),
        'doctors_available' => Doctor::where('status', 'Available')->count(),
        'admitted' => Admission::whereNull('discharged_on')->where('status', '!=', 'Discharged')->count(),
        'admissions_today' => Admission::whereDate('admitted_on', $today)->count(),
        'admissions_waiting' => Admission::where('status', 'Waiting')->count(),
        'discharges_today' => Admission::whereDate('discharged_on', $today)->count(),
        'lab_requests' => DB::table('lab_requests')->whereDate('created_at', $today)->count(),
        'lab_pending' => DB::table('lab_requests')->whereIn('status', ['Collected', 'Processing'])->count(),
        'lab_ready' => DB::table('lab_requests')->where('status', 'Ready')->count(),
        'prescriptions' => DB::table('prescriptions')->whereDate('created_at', $today)->count(),
        'prescriptions_collected' => DB::table('prescriptions')->whereDate('created_at', $today)->where('status', 'Collected')->count(),
        'low_stock' => DB::table('medicines')->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
        'out_of_stock' => DB::table('medicines')->where('stock', 0)->count(),
        'revenue_today' => DB::table('invoices')->whereDate('created_at', $today)->sum('amount'),
        'invoices_today' => DB::table('invoices')->whereDate('created_at', $today)->count(),
        'beds' => DB::table('beds')->count(),
        'beds_available' => DB::table('beds')->where('status', 'Available')->count(),
        'inventory_items' => DB::table('inventory_items')->count(),
        'staff' => DB::table('staff')->count(),
        'staff_present' => DB::table('staff')->where('status', 'Present')->count(),
        'reports_month' => DB::table('invoices')->whereMonth('created_at', $today->month)->whereYear('created_at', $today->year)->count(),
        'notifications_unread' => 0,
    ];
}

Route::prefix('receptionist')->name('receptionist.')->group(function () {
    Route::get('/', [ReceptionistController::class, 'index'])->name('index');
    Route::get('/appointments', [ReceptionistController::class, 'appointmentsPage'])->name('appointments.index');
    Route::get('/appointments/live', [ReceptionistController::class, 'appointmentsLive'])->name('appointments.live');
    Route::post('/appointments', [ReceptionistController::class, 'storeAppointment'])->name('appointments.store');
    Route::get('/appointments/{appointment}/token', [ReceptionistController::class, 'appointmentToken'])->name('appointments.token');
    Route::post('/appointments/{appointment}/token', [ReceptionistController::class, 'generateAppointmentToken'])->name('appointments.token.generate');
    Route::patch('/appointments/{appointment}/status', [ReceptionistController::class, 'updateAppointmentStatus'])->name('appointments.status');
    Route::post('/tokens', [ReceptionistController::class, 'storeToken'])->name('tokens.store');
    Route::post('/cash', [ReceptionistController::class, 'storeCash'])->name('cash.store');
    Route::get('/patients/{patient}/history', [ReceptionistController::class, 'patientHistory'])->name('patients.history');
});
