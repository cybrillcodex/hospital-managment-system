<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Receptionist | HMS</title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
	<style>
		:root { --ink: #ffffff; --muted: #bdc8db; --line: #2c4266; --panel: #142b50; --canvas: #0b1d3a; --gold: #edbd3f; --shadow: 0 10px 26px rgba(0, 0, 0, 0.2); }
		* { box-sizing: border-box; }
		body { margin: 0; background: var(--canvas); color: var(--ink); font: 15px 'DM Sans', sans-serif; }
		a { color: var(--gold); text-decoration: none; }
		.wrap { max-width: 1320px; margin: 0 auto; padding: 24px 26px 40px; }
		.topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
		.topbar h1 { margin: 0; font: 800 26px 'Manrope', sans-serif; }
		.topbar p { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
		.back-link { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); font-size: 13px; }
		.status-banner { margin-bottom: 16px; padding: 12px 16px; border: 1px solid var(--gold); border-radius: 8px; background: #2a2312; color: var(--gold); font-size: 13px; }
		.kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 18px; }
		.kpi { padding: 15px; border: 1px solid var(--line); border-radius: 10px; background: var(--panel); }
		.kpi small { display: block; color: var(--muted); font-size: 11px; }
		.kpi strong { display: block; margin-top: 6px; font: 800 24px 'Manrope', sans-serif; }
		.grid-2 { display: grid; grid-template-columns: 1.05fr .95fr; gap: 16px; margin-bottom: 16px; }
		.card { border: 1px solid var(--line); border-radius: 10px; background: var(--panel); overflow: hidden; }
		.card h2 { margin: 0; padding: 15px 16px; border-bottom: 1px solid var(--line); font: 700 15px 'Manrope', sans-serif; display: flex; align-items: center; gap: 9px; }
		.card h2 i { color: var(--gold); }
		.card-body { padding: 16px; }
		form label { display: grid; gap: 6px; margin-bottom: 12px; color: var(--muted); font-size: 12px; }
		form input, form select, form textarea { min-height: 40px; border: 1px solid #405a82; border-radius: 7px; padding: 0 10px; background: #10284b; color: #fff; font: inherit; }
		form textarea { min-height: 70px; padding: 8px 10px; resize: vertical; }
		.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
		.submit-btn { width: 100%; min-height: 42px; margin-top: 4px; border: 0; border-radius: 8px; background: var(--gold); color: #10284b; font-weight: 800; cursor: pointer; }
		table { width: 100%; border-collapse: collapse; font-size: 12px; }
		th, td { padding: 10px 12px; border-bottom: 1px solid var(--line); text-align: left; white-space: nowrap; }
		th { background: #1d3a64; color: var(--gold); font-size: 11px; }
		.badge { display: inline-flex; border-radius: 999px; padding: 4px 9px; background: var(--gold); color: #10284b; font-size: 10px; font-weight: 700; }
		.badge.muted { background: #54708f; color: #fff; }
		.table-wrap { overflow-x: auto; }
		.empty-row td { text-align: center; color: var(--muted); padding: 18px; }
		.patient-search { display: flex; gap: 8px; margin-bottom: 12px; }
		.patient-search input { flex: 1; }
		.patient-search button { min-height: 40px; padding: 0 16px; border: 0; border-radius: 7px; background: var(--gold); color: #10284b; font-weight: 700; cursor: pointer; }
		#historyResult { font-size: 12px; color: var(--muted); }
		#historyResult h3 { color: #fff; font-size: 13px; margin: 14px 0 6px; }
		#historyResult ul { margin: 0; padding-left: 18px; }
		@media (max-width: 1100px) { .kpis { grid-template-columns: repeat(2, 1fr); } .grid-2 { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<div class="wrap">
		<div class="topbar">
			<div>
				<h1>Receptionist Desk</h1>
				<p>Book appointments, issue tokens, record cash, and look up patient history.</p>
			</div>
			<a class="back-link" href="{{ url('/') }}"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
		</div>

		@if (session('status'))
			<div class="status-banner"><i class="fa-solid fa-circle-check"></i> {{ session('status') }}</div>
		@endif
		@if ($errors->any())
			<div class="status-banner" style="border-color:#e06b70;color:#f3b3b6;">
				<i class="fa-solid fa-triangle-exclamation"></i>
				{{ $errors->first() }}
			</div>
		@endif
		@include('partials.token-print-card')

		<div class="kpis">
			<div class="kpi"><small>Today's Appointments</small><strong>{{ $kpis['appointments_today'] }}</strong></div>
			<div class="kpi"><small>Tokens Issued Today</small><strong>{{ $kpis['tokens_today'] }}</strong></div>
			<div class="kpi"><small>Cash Collected Today</small><strong>Rs {{ number_format($kpis['cash_today'], 0) }}</strong></div>
			<div class="kpi"><small>Doctors Available</small><strong>{{ $kpis['doctors_available'] }}</strong></div>
		</div>

		<div class="grid-2">
			<div class="card">
				<h2><i class="fa-solid fa-calendar-plus"></i>Book Appointment</h2>
				<div class="card-body">
					<form method="POST" action="{{ route('receptionist.appointments.store') }}">
						@csrf
						<label>Existing patient (optional)
							<select name="patient_id">
								<option value="">— New patient —</option>
								@foreach ($patients as $patient)
									<option value="{{ $patient->id }}">{{ $patient->patient_code }} · {{ $patient->name }}</option>
								@endforeach
							</select>
						</label>
						<div class="form-row">
							<label>Patient name<input type="text" name="patient_name" placeholder="e.g. Ayesha Khan" /></label>
							<label>Phone<input type="tel" name="phone" placeholder="e.g. +92 300 1234567" /></label>
						</div>
						<div class="form-row">
							<label>Gender
								<select name="gender">
									<option value="">Select</option>
									<option>Male</option>
									<option>Female</option>
									<option>Other</option>
								</select>
							</label>
							<label>Age<input type="number" name="age" min="0" max="130" /></label>
						</div>
						<label>Doctor
							<select name="doctor_id" required>
								<option value="">Select doctor</option>
								@foreach ($doctors as $doctor)
									<option value="{{ $doctor->id }}">{{ $doctor->name }} · {{ $doctor->specialty }} @if($doctor->room) (Room {{ $doctor->room }}) @endif</option>
								@endforeach
							</select>
						</label>
						<div class="form-row">
							<label>Date<input type="date" name="appointment_date" required /></label>
							<label>Time<input type="time" name="appointment_time" required /></label>
						</div>
						<div class="form-row">
							<label>Department<input type="text" name="department" placeholder="e.g. Cardiology" /></label>
							<label>Type
								<select name="type">
									<option>Consultation</option>
									<option>Follow-up</option>
									<option>Emergency</option>
								</select>
							</label>
						</div>
						<label>Reason<input type="text" name="reason" placeholder="e.g. Routine checkup" /></label>
						<label>Notes<textarea name="notes"></textarea></label>
						<button class="submit-btn" type="submit">Book Appointment</button>
					</form>
				</div>
			</div>

			<div class="card">
				<h2><i class="fa-solid fa-user-doctor"></i>Doctor Availability &amp; Rooms</h2>
				<div class="table-wrap">
					<table>
						<thead><tr><th>Doctor</th><th>Specialty</th><th>Room</th><th>Status</th></tr></thead>
						<tbody>
							@forelse ($doctors as $doctor)
								<tr>
									<td>{{ $doctor->name }}</td>
									<td>{{ $doctor->specialty }}</td>
									<td>{{ $doctor->room ?? '—' }}</td>
									<td><span class="badge {{ $doctor->status === 'Available' ? '' : 'muted' }}">{{ $doctor->status }}</span></td>
								</tr>
							@empty
								<tr class="empty-row"><td colspan="4">No doctors added yet.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="grid-2">
			<div class="card">
				<h2><i class="fa-solid fa-ticket"></i>Generate Token</h2>
				<div class="card-body">
					<form method="POST" action="{{ route('receptionist.tokens.store') }}">
						@csrf
						<label>Existing patient (optional)
							<select name="patient_id">
								<option value="">— New patient —</option>
								@foreach ($patients as $patient)
									<option value="{{ $patient->id }}">{{ $patient->patient_code }} · {{ $patient->name }}</option>
								@endforeach
							</select>
						</label>
						<div class="form-row">
							<label>Patient name<input type="text" name="patient_name" placeholder="e.g. Bilal Ahmed" /></label>
							<label>Phone<input type="tel" name="phone" /></label>
						</div>
						<label>Doctor
							<select name="doctor_id" required>
								<option value="">Select doctor</option>
								@foreach ($doctors as $doctor)
									<option value="{{ $doctor->id }}">{{ $doctor->name }} · {{ $doctor->specialty }}</option>
								@endforeach
							</select>
						</label>
						<button class="submit-btn" type="submit">Generate Token</button>
					</form>
				</div>
				<div class="table-wrap">
					<table>
						<thead><tr><th>Token #</th><th>Patient</th><th>Doctor</th><th>Status</th></tr></thead>
						<tbody>
							@forelse ($todaysTokens as $token)
								<tr>
									<td>{{ $token->token_number }}</td>
									<td>{{ $token->patient->name ?? '—' }}</td>
									<td>{{ $token->doctor->name ?? '—' }}</td>
									<td><span class="badge {{ $token->status === 'Waiting' ? '' : 'muted' }}">{{ $token->status }}</span></td>
								</tr>
							@empty
								<tr class="empty-row"><td colspan="4">No tokens issued today.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>

			<div class="card">
				<h2><i class="fa-solid fa-money-bill-wave"></i>Record Cash</h2>
				<div class="card-body">
					<form method="POST" action="{{ route('receptionist.cash.store') }}">
						@csrf
						<label>Existing patient (optional)
							<select name="patient_id">
								<option value="">— New patient —</option>
								@foreach ($patients as $patient)
									<option value="{{ $patient->id }}">{{ $patient->patient_code }} · {{ $patient->name }}</option>
								@endforeach
							</select>
						</label>
						<div class="form-row">
							<label>Patient name<input type="text" name="patient_name" placeholder="e.g. Nida Khan" /></label>
							<label>Phone<input type="tel" name="phone" /></label>
						</div>
						<div class="form-row">
							<label>Amount (PKR)<input type="number" step="0.01" min="0" name="amount" required /></label>
							<label>Purpose
								<select name="purpose">
									<option>Consultation Fee</option>
									<option>Registration Fee</option>
									<option>Lab Fee</option>
									<option>Pharmacy</option>
									<option>Other</option>
								</select>
							</label>
						</div>
						<label>Received by<input type="text" name="received_by" placeholder="e.g. Ayesha Khan" /></label>
						<button class="submit-btn" type="submit">Record Cash</button>
					</form>
				</div>
				<div class="table-wrap">
					<table>
						<thead><tr><th>Receipt #</th><th>Patient</th><th>Amount</th><th>Purpose</th></tr></thead>
						<tbody>
							@forelse ($todaysCash as $receipt)
								<tr>
									<td>{{ $receipt->receipt_number }}</td>
									<td>{{ $receipt->patient->name ?? '—' }}</td>
									<td>Rs {{ number_format($receipt->amount, 0) }}</td>
									<td>{{ $receipt->purpose }}</td>
								</tr>
							@empty
								<tr class="empty-row"><td colspan="4">No cash recorded today.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="card">
			<h2><i class="fa-solid fa-clock-rotate-left"></i>Patient History Lookup</h2>
			<div class="card-body">
				<div class="patient-search">
					<select id="historyPatientSelect">
						<option value="">Select a patient…</option>
						@foreach ($patients as $patient)
							<option value="{{ $patient->id }}">{{ $patient->patient_code }} · {{ $patient->name }} · {{ $patient->phone }}</option>
						@endforeach
					</select>
					<button type="button" id="historyLookupBtn"><i class="fa-solid fa-magnifying-glass"></i> Lookup</button>
				</div>
				<div id="historyResult">Select a patient above to view their appointments, tokens, cash receipts, admissions, and invoices.</div>
			</div>
		</div>

		<div class="card" style="margin-top:16px;">
			<h2><i class="fa-solid fa-calendar-check"></i>Today's Appointments</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Department</th><th>Status</th></tr></thead>
					<tbody>
						@forelse ($todaysAppointments as $appointment)
							<tr>
								<td>{{ \Illuminate\Support\Carbon::parse($appointment->appointment_time)->format('h:i A') }}</td>
								<td>{{ $appointment->patient->name ?? '—' }}</td>
								<td>{{ $appointment->doctor->name ?? '—' }}</td>
								<td>{{ $appointment->department ?? '—' }}</td>
								<td><span class="badge">{{ $appointment->status }}</span></td>
							</tr>
						@empty
							<tr class="empty-row"><td colspan="5">No appointments booked today yet.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<script>
		document.getElementById('historyLookupBtn').addEventListener('click', async () => {
			const select = document.getElementById('historyPatientSelect');
			const result = document.getElementById('historyResult');
			if (!select.value) { result.textContent = 'Please select a patient first.'; return; }
			result.textContent = 'Loading…';
			try {
				const response = await fetch(`/receptionist/patients/${select.value}/history`, { headers: { Accept: 'application/json' } });
				const data = await response.json();
				const fmt = (list, render) => list.length ? `<ul>${list.map(render).join('')}</ul>` : '<p>None recorded.</p>';
				result.innerHTML = `
					<h3>Appointments</h3>${fmt(data.appointments, a => `<li>${a.appointment_date} ${a.appointment_time} — Dr. ${a.doctor ? a.doctor.name : 'N/A'} (${a.status})</li>`)}
					<h3>Tokens</h3>${fmt(data.tokens, t => `<li>${t.token_number} — Dr. ${t.doctor ? t.doctor.name : 'N/A'} (${t.status})</li>`)}
					<h3>Cash Receipts</h3>${fmt(data.cash_receipts, c => `<li>${c.receipt_number} — Rs ${Number(c.amount).toLocaleString()} (${c.purpose})</li>`)}
					<h3>Admissions</h3>${fmt(data.admissions, a => `<li>${a.admitted_on} — Dr. ${a.doctor ? a.doctor.name : 'N/A'} (${a.status})</li>`)}
					<h3>Invoices</h3>${fmt(data.invoices, i => `<li>${i.invoice_number} — Rs ${Number(i.amount).toLocaleString()} (${i.status})</li>`)}
				`;
			} catch (e) {
				result.textContent = 'Unable to load patient history.';
			}
		});
	</script>
</body>
</html>
