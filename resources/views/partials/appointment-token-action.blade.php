@if (session('appointment_to_token'))
    <section class="appointment-token-action">
        <strong>Appointment saved successfully.</strong>
        <span>Generate the queue token for this appointment.</span>
        <form method="POST" action="{{ route('receptionist.appointments.token.generate', session('appointment_to_token')) }}">
            @csrf
            <button type="submit"><i class="fa-solid fa-ticket"></i> Generate Token</button>
        </form>
    </section>
    <style>
        .appointment-token-action { display: grid; gap: 8px; margin-bottom: 16px; padding: 16px; border: 1px solid var(--gold, #edbd3f); border-radius: 9px; background: #2a2312; color: #fff; }
        .appointment-token-action span { color: var(--muted, #bdc8db); font-size: 13px; }
        .appointment-token-action button { width: fit-content; min-height: 40px; padding: 0 15px; border: 0; border-radius: 7px; background: var(--gold, #edbd3f); color: #10284b; font-weight: 800; cursor: pointer; }
    </style>
@endif
@include('partials.token-print-card')
