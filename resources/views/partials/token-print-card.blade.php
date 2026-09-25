@if (!empty($printToken))
    <section class="token-print-card" id="tokenPrintCard">
        <div class="token-brand">
            <img src="{{ asset('hospital-logo.svg') }}" alt="Hospital logo">
            <div>
                <strong>{{ config('app.hospital_name') }}</strong>
                <small>Hospital No: {{ config('app.hospital_number') }}</small>
            </div>
        </div>
        <div class="token-heading">Patient Token</div>
        <div class="token-number">{{ $printToken->token_number }}</div>
        <div class="token-details">
            <div><small>Patient</small><strong>{{ $printToken->patient->name }}</strong></div>
            <div><small>Doctor</small><strong>{{ $printToken->doctor->name }}</strong></div>
            <div><small>Date</small><strong>{{ $printToken->token_date->format('d M Y') }}</strong></div>
            <div><small>Status</small><strong>{{ $printToken->status }}</strong></div>
        </div>
        <button class="print-token-button" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Token</button>
    </section>
    <style>
        .token-print-card { max-width: 440px; margin: 0 0 18px; padding: 22px; border: 2px solid var(--gold, #edbd3f); border-radius: 12px; background: #fff; color: #10284b; text-align: center; }
        .token-brand { display: flex; align-items: center; justify-content: center; gap: 12px; text-align: left; }
        .token-brand img { width: 58px; height: 58px; }
        .token-brand strong, .token-brand small { display: block; }
        .token-brand strong { font: 800 18px Manrope, sans-serif; }
        .token-brand small { margin-top: 4px; color: #52647c; font-size: 11px; }
        .token-heading { margin-top: 18px; color: #52647c; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .token-number { margin: 4px 0 17px; color: #10284b; font: 800 38px Manrope, sans-serif; letter-spacing: .04em; }
        .token-details { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 13px 0; border-top: 1px solid #d9e0e8; border-bottom: 1px solid #d9e0e8; text-align: left; }
        .token-details small, .token-details strong { display: block; }
        .token-details small { color: #65758a; font-size: 10px; }
        .token-details strong { margin-top: 3px; font-size: 13px; }
        .print-token-button { margin-top: 16px; min-height: 40px; padding: 0 17px; border: 0; border-radius: 7px; background: #edbd3f; color: #10284b; font-weight: 800; cursor: pointer; }
        @media print { body * { visibility: hidden !important; } #tokenPrintCard, #tokenPrintCard * { visibility: visible !important; } #tokenPrintCard { position: absolute; left: 0; top: 0; width: 80mm; margin: 0; border: 1px solid #111; } .print-token-button { display: none !important; } }
    </style>
@endif
