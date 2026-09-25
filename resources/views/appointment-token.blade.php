<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Token | HMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #0b1d3a; --panel: #142b50; --line: #2c4266; --gold: #edbd3f; --muted: #bdc8db; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--navy); color: #fff; font: 15px 'DM Sans', sans-serif; }
        .wrap { width: min(100% - 32px, 720px); margin: 0 auto; padding: 34px 0 50px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; }
        h1, h2 { font-family: Manrope, sans-serif; }
        h1 { margin: 0; font-size: 25px; }
        .sub { margin: 5px 0 0; color: var(--muted); }
        .back { color: var(--gold); text-decoration: none; border: 1px solid var(--line); border-radius: 7px; padding: 9px 13px; }
        .banner { margin-bottom: 18px; border: 1px solid var(--gold); border-radius: 8px; padding: 12px 15px; color: var(--gold); background: #2a2312; }
        .panel { border: 1px solid var(--line); border-radius: 12px; padding: 25px; background: var(--panel); }
        .panel h2 { margin: 0 0 8px; font-size: 19px; }
        .panel p { color: var(--muted); }
        .appointment-details { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 22px 0; }
        .detail { border: 1px solid var(--line); border-radius: 8px; padding: 12px; }
        .detail small, .detail strong { display: block; }
        .detail small { color: var(--muted); font-size: 11px; }
        .detail strong { margin-top: 4px; }
        .generate, .print { display: inline-flex; align-items: center; justify-content: center; min-height: 43px; padding: 0 18px; border: 0; border-radius: 7px; background: var(--gold); color: #10284b; font-weight: 800; cursor: pointer; text-decoration: none; }
        .token-slip { width: min(100%, 560px); margin: 22px auto 0; padding: 22px; border: 2px solid var(--gold); border-radius: 12px; background: #fff; color: #10284b; text-align: center; }
        .brand { display: flex; align-items: center; justify-content: center; gap: 12px; text-align: left; }
        .brand img { width: 58px; height: 58px; }
        .brand strong, .brand small { display: block; }
        .brand strong { font: 800 18px Manrope, sans-serif; }
        .brand small { margin-top: 4px; color: #52647c; font-size: 11px; }
        .label { margin-top: 17px; color: #52647c; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .token-number { margin: 4px 0 17px; font: 800 38px Manrope, sans-serif; letter-spacing: .04em; }
        .token-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 13px 0; border-top: 1px solid #d9e0e8; border-bottom: 1px solid #d9e0e8; text-align: left; }
        .token-meta small, .token-meta strong { display: block; }
        .token-meta small { color: #65758a; font-size: 10px; }
        .token-meta strong { margin-top: 3px; font-size: 13px; }
        .print { margin-top: 16px; }
        @media (max-width: 560px) { .top { align-items: flex-start; flex-direction: column; } .appointment-details { grid-template-columns: 1fr; } }
        @media print { body * { visibility: hidden !important; } .token-slip, .token-slip * { visibility: visible !important; } .token-slip { position: absolute; left: 0; top: 0; width: 80mm; margin: 0; border: 1px solid #111; } .print { display: none !important; } }
    </style>
</head>
<body>
    <main class="wrap">
        <header class="top">
            <div><h1>Appointment Confirmed</h1><p class="sub">Generate the patient token when the appointment is ready.</p></div>
            <a class="back" href="{{ route('receptionist.appointments.index') }}">Appointments</a>
        </header>
        @if (session('status'))<div class="banner">{{ session('status') }}</div>@endif
        <section class="panel">
            <h2>Appointment details</h2>
            <p>The appointment is saved. Generate a token to issue the queue number.</p>
            <div class="appointment-details">
                <div class="detail"><small>Patient</small><strong>{{ $appointment->patient->name }}</strong></div>
                <div class="detail"><small>Doctor</small><strong>{{ $appointment->doctor->name }}</strong></div>
                <div class="detail"><small>Date</small><strong>{{ $appointment->appointment_date->format('d M Y') }}</strong></div>
                <div class="detail"><small>Time</small><strong>{{ \Illuminate\Support\Carbon::parse($appointment->appointment_time)->format('h:i A') }}</strong></div>
            </div>
            @if (!$appointment->token)
                <form method="POST" action="{{ route('receptionist.appointments.token.generate', $appointment) }}">
                    @csrf
                    <button class="generate" type="submit">Generate Token</button>
                </form>
            @else
                <section class="token-slip" id="tokenSlip">
                    <div class="brand"><img src="{{ asset('hospital-logo.svg') }}" alt="Hospital logo"><div><strong>{{ config('app.hospital_name') }}</strong><small>Hospital No: {{ config('app.hospital_number') }}</small></div></div>
                    <div class="label">Patient Token</div>
                    <div class="token-number">{{ $appointment->token->token_number }}</div>
                    <div class="token-meta"><div><small>Patient</small><strong>{{ $appointment->patient->name }}</strong></div><div><small>Doctor</small><strong>{{ $appointment->doctor->name }}</strong></div><div><small>Date</small><strong>{{ $appointment->token->token_date->format('d M Y') }}</strong></div><div><small>Status</small><strong>{{ $appointment->token->status }}</strong></div></div>
                    <button class="print" type="button" onclick="window.print()">Print Token</button>
                </section>
            @endif
        </section>
    </main>
</body>
</html>
