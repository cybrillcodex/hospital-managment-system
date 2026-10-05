@extends('layouts.app')

@section('title', 'Booking #'.$booking->id)
@section('subtitle', 'Bookings · Booking #'.$booking->id)
@section('actions')
    <div class="booking-page-tools">
        <div class="booking-search" aria-hidden="true">
            <x-icon name="search" size="sm" />
            <span>Search bookings, guests, rooms...</span>
        </div>
        <span class="booking-notification" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
            </svg>
            <span></span>
        </span>
        <span class="booking-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span>
        <span class="booking-user-name">{{ auth()->user()->name }}</span>
        <x-icon name="chevron" size="sm" class="booking-user-chevron" />
    </div>
@endsection

@section('content')
    @php
        $scheduledCheckOutAt = $booking->check_out->copy()->setTime(12, 0);
        $lateSeconds = now()->greaterThan($scheduledCheckOutAt) ? $scheduledCheckOutAt->diffInSeconds(now()) : 0;
        $lateDays = $lateSeconds > 0 ? (int) ceil($lateSeconds / 86400) : 0;
        $lateCharge = round($lateDays * (float) $booking->price_per_night, 2);
        $baseNights = max(1, $booking->plannedNights());
        $baseRoomAmount = round($baseNights * (float) $booking->price_per_night, 2);
        $extrasAmount = (float) $booking->extras_amount;
        $paidAmount = (float) $booking->amountPaid();
        $baseTotalAmount = round($baseRoomAmount + $extrasAmount, 2);
        $summary = $booking->checkoutSummary();
        $totalBilled = max(0, (float) ($summary['total']['billed'] ?? 0));
        $totalPaid = max(0, (float) ($summary['total']['paid'] ?? 0));
        $totalDue = max(0, (float) ($summary['total']['due'] ?? 0));
        $payableLines = $booking->payableLines();
        $roomLines = collect($payableLines)->where('type', 'room')->values();
        $extraLines = collect($payableLines)->where('type', 'extras')->values();
        $guestCount = max(0, (int) $booking->guestCount());
        $billedTotal = max(0.01, (float) ($summary['total']['billed'] ?? 0.01));
        $paidPct = $billedTotal > 0 ? (int) min(100, round(($totalPaid / $billedTotal) * 100)) : 0;
        $nightsCompleted = $booking->status === 'checked_out'
            ? $baseNights
            : max(0, min($baseNights, (int) $booking->check_in->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay())));
        $stayPct = $baseNights > 0 ? (int) round(($nightsCompleted / $baseNights) * 100) : 0;
        $ringRadius = 50;
        $ringCirc = 2 * M_PI * $ringRadius;
        $ringOffset = $ringCirc * (1 - ($paidPct / 100));
        $floorRaw = trim((string) ($booking->room->floor ?? ''));
        $floorLabel = $floorRaw === '' || in_array(strtolower($floorRaw), ['0', 'g', 'ground'], true)
            ? 'Ground floor'
            : 'Floor '.$floorRaw;
        $bedLabel = ucfirst((string) $booking->room->bed_type).' Bed';
        $initials = collect(preg_split('/\s+/', trim((string) $booking->customer->name)))
            ->filter()
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');
        $guestDocuments = [
            ['label' => 'ID card front', 'url' => $booking->frontUrl()],
            ['label' => 'ID card back', 'url' => $booking->backUrl()],
        ];
        $companionDocuments = collect();
        foreach ($booking->companions as $companion) {
            if ($companion->frontUrl()) {
                $companionDocuments->push([
                    'label' => $companion->name.' — ID card front',
                    'url' => $companion->frontUrl(),
                ]);
            }
            if ($companion->backUrl()) {
                $companionDocuments->push([
                    'label' => $companion->name.' — ID card back',
                    'url' => $companion->backUrl(),
                ]);
            }
        }
        $documentUrls = collect($guestDocuments)->pluck('url')->filter()
            ->merge($companionDocuments->pluck('url'))->values();
        $paymentMethodLabel = function (?string $method): string {
            return match ($method) {
                'cash' => 'Cash',
                'online' => 'Online',
                'mixed' => 'Mixed',
                default => '—',
            };
        };
    @endphp

    <style>
        :root {
            --brand-50: #fff1f3;
            --brand-100: #ffe0e8;
            --brand-200: #f9c4d4;
            --brand-500: #b42352;
            --brand-600: #9f1239;
            --brand-700: #7f1d1d;
            --ink: #1f2937;
            --muted: #6b7280;
            --soft: #f8fafc;
            --line: #f1f5f9;
            --panel: #ffffff;
            --success: #059669;
            --success-soft: #ecfdf5;
            --warning: #d97706;
            --warning-soft: #fff7ed;
            --danger: #dc2626;
            --danger-soft: #fef2f2;
            --shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
        }

        .bk-page {
            display: grid;
            gap: 1.2rem;
            animation: fadeUp 0.45s ease-out both;
        }

        .bk-card {
            background: linear-gradient(180deg, rgba(255,255,255,1) 0%, rgba(248,250,252,0.62) 100%);
            border: 1px solid var(--line);
            border-radius: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            overflow: hidden;
        }

        .bk-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.10);
            border-color: #f9d5df;
        }

        .bk-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #f7efeef 0%, #f3e8e4 100%);
            border: 1px solid #e9d8d3;
            border-radius: 1.45rem;
            padding: 0.8rem 1rem 0.9rem;
            box-shadow: none;
        }

        .bk-hero::after {
            content: "";
            position: absolute;
            inset: -20% auto auto -8%;
            width: 18rem;
            height: 18rem;
            border-radius: 50%;
            background: rgba(190, 24, 93, 0.025);
            filter: blur(18px);
        }

        .bk-hero-mark {
            width: 2.8rem;
            height: 2.8rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #f4dfe3 0%, #e9c7d1 100%);
            color: #b85a63;
            box-shadow: inset 0 0 0 1px rgba(184, 90, 99, 0.06);
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .bk-kicker {
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
            color: #c06f5a;
            margin: 0;
            line-height: 1.2;
        }

        .bk-guest-name {
            margin-top: 0.25rem;
            font-size: clamp(2.2rem, 2.8vw, 5.2rem);
            line-height: 0.92;
            font-weight: 500;
            letter-spacing: -0.065em;
            color: #191919;
            position: relative;
            z-index: 1;
            font-family: Georgia, "Times New Roman", serif;
        }

        .bk-hero-meta {
            margin-top: 0.7rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.52rem 1.05rem;
            color: rgba(33, 30, 28, 0.9);
            font-size: 0.82rem;
            position: relative;
            z-index: 1;
        }

        .bk-hero-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.42rem;
            font-weight: 500;
            line-height: 1.3;
        }

        .bk-hero-meta svg {
            color: rgba(23, 23, 23, 0.8);
            width: 1.02rem;
            height: 1.02rem;
        }

        .bk-hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: flex-end;
            gap: 0.55rem;
            position: relative;
            z-index: 1;
            margin-top: 0.25rem;
        }

        .bk-action-stack {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .bk-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.44rem;
            min-height: 2.7rem;
            padding: 0.7rem 1.05rem;
            border-radius: 0.9rem;
            border: 1px solid rgba(103, 96, 91, 0.2);
            background: rgba(255,255,255,0.72);
            color: #2b2b2b;
            font-size: 0.96rem;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.2s ease;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.12);
        }

        .bk-btn svg {
            width: 1.08rem;
            height: 1.08rem;
            flex-shrink: 0;
        }

        .bk-btn-primary {
            border-color: #b95f70;
            background: linear-gradient(180deg, #c46072 0%, #b44254 100%);
            color: #fff;
            box-shadow: 0 8px 16px rgba(180, 66, 84, 0.18);
        }

        .bk-btn-primary:hover {
            background: linear-gradient(180deg, #cd7080 0%, #b44254 100%);
        }

        .bk-btn-cancel {
            border-color: rgba(165, 81, 95, 0.2);
            background: rgba(255,255,255,0.72);
            color: #d1485a;
            font-weight: 700;
        }

        .bk-btn-cancel:hover {
            background: rgba(255,247,249,0.95);
        }

        .bk-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.62rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            white-space: nowrap;
            line-height: 1.2;
        }

        .bk-kpi-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 1rem;
        }

        .bk-kpi {
            background: linear-gradient(180deg, rgba(255,255,255,1) 0%, rgba(248,250,252,0.9) 100%);
            border: 1px solid var(--line);
            border-radius: 1.2rem;
            padding: 1rem;
            min-width: 0;
            transition: transform 0.2s ease, border-color 0.2s ease;
            animation: fadeUp 0.5s ease-out both;
        }

        .bk-kpi:hover {
            transform: translateY(-1px);
            border-color: #f7d8e0;
        }

        .bk-kpi.is-overdue {
            background: linear-gradient(180deg, #fff7ed 0%, #fffaf5 100%);
            border-color: #fed7aa;
        }

        .bk-kpi.is-total {
            background: linear-gradient(180deg, #fff1f3 0%, #fff7f9 100%);
            border-color: #f9d5df;
        }

        .bk-kpi-title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--ink);
        }

        .bk-kpi .stat-num {
            margin-top: 0.8rem;
            font-size: clamp(1.2rem, 1.4vw, 1.8rem);
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--ink);
        }

        .bk-kpi .text-crimson,
        .text-crimson {
            color: var(--brand-600) !important;
        }

        .bk-icon {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.8rem;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.08);
        }

        .bk-progress-row {
            margin-top: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .bk-progress {
            height: 0.48rem;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
            flex: 1;
        }

        .bk-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #34d399 0%, #10b981 100%);
        }

        .bk-split {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.95fr);
            gap: 1.1rem;
            align-items: start;
        }

        .bk-card-wrap {
            padding: 1.25rem;
        }

        .bk-timeline {
            position: relative;
            padding-left: 1.35rem;
        }

        .bk-timeline::before {
            content: "";
            position: absolute;
            left: 0.42rem;
            top: 0.55rem;
            bottom: 0.55rem;
            width: 2px;
            background: linear-gradient(180deg, #d1fae5 0%, #fecdd3 100%);
        }

        .bk-dot {
            position: absolute;
            left: -1.14rem;
            top: 0.35rem;
            width: 0.72rem;
            height: 0.72rem;
            border-radius: 50%;
            box-shadow: 0 0 0 4px #fff;
        }

        .bk-nights {
            width: 5.8rem;
            height: 5.8rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffe4eb 0%, #fbcfe8 100%);
            color: var(--brand-600);
            display: grid;
            place-items: center;
            text-align: center;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(159, 18, 57, 0.06);
        }

        .bk-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .bk-table th {
            text-align: left;
            font-size: 0.7rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
            padding: 0.7rem 0.7rem;
            border-bottom: 1px solid var(--line);
        }

        .bk-table td {
            padding: 0.9rem 0.7rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .bk-table tr:last-child td {
            border-bottom: 0;
        }

        .bk-meta {
            color: var(--muted);
        }

        .bk-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.62rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            white-space: nowrap;
            line-height: 1.2;
        }

        .bk-badge-success {
            background: var(--success-soft);
            color: var(--success);
        }

        .bk-badge-warning {
            background: var(--warning-soft);
            color: var(--warning);
        }

        .bk-badge-danger {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .bk-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            border-radius: 999px;
            padding: 0.32rem 0.7rem;
            border: 1px solid rgba(15, 23, 42, 0.06);
            background: rgba(255,255,255,0.8);
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.52);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            z-index: 50;
        }

        .modal-overlay.is-open {
            display: flex;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-panel {
            width: min(42rem, 100%);
            border-radius: 1.4rem;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: 0 26px 46px rgba(15, 23, 42, 0.18);
            animation: slideUp 0.25s ease-out;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.99);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 1280px) {
            .bk-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        @media (max-width: 900px) {
            .bk-split { grid-template-columns: 1fr; }
            .bk-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .bk-page { gap: 1rem; }
            .bk-hero { padding: 1rem; }
            .bk-kpi-grid { grid-template-columns: 1fr; }
            .bk-hero-actions { width: 100%; justify-content: stretch; }
            .bk-action-stack { width: 100%; }
            .bk-btn { width: 100%; }
            .bk-card-wrap { padding: 1rem; }
        }

        body.booking-detail-body {
            background: #f4f5f7;
            color: #252a34;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        }

        body.booking-detail-body .app-bottom-nav {
            display: none;
        }

        body.booking-detail-body .app-header {
            min-height: 3.5rem;
            padding: 0.45rem 1rem;
            background: #fff;
            border-color: #e8ebf0;
            box-shadow: 0 1px 2px rgba(20, 30, 50, 0.03);
        }

        body.booking-detail-body .app-header > div:first-child {
            gap: 0.55rem;
        }

        body.booking-detail-body .app-header p {
            color: #78808c;
            font-size: 0.72rem;
        }

        body.booking-detail-body .app-header-actions {
            max-width: none;
            gap: 0.7rem;
        }

        body.booking-detail-body .app-main {
            min-height: calc(100vh - 3.5rem);
            padding: 0.75rem 0.85rem 1rem;
            background: #f4f5f7;
        }

        @media (min-width: 768px) {
            body.booking-detail-body .app-sidebar {
                translate: 0 0 !important;
            }

            body.booking-detail-body .app-shell {
                padding-left: 17.5rem !important;
            }
        }

        .booking-page-tools {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: #69717d;
            font-size: 0.67rem;
        }

        .booking-search {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            width: clamp(8rem, 22vw, 15rem);
            padding: 0.4rem 0.55rem;
            border: 1px solid #e7e9ef;
            border-radius: 0.42rem;
            background: #f8f9fb;
            color: #969daa;
            white-space: nowrap;
        }

        .booking-search svg {
            width: 0.8rem;
            height: 0.8rem;
        }

        .booking-notification {
            position: relative;
            display: grid;
            width: 1.7rem;
            height: 1.7rem;
            place-items: center;
            color: #59616d;
        }

        .booking-notification svg {
            width: 0.95rem;
            height: 0.95rem;
        }

        .booking-notification span {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            width: 0.32rem;
            height: 0.32rem;
            border: 1px solid #fff;
            border-radius: 50%;
            background: #d91f53;
        }

        .booking-user-avatar {
            display: grid;
            width: 1.45rem;
            height: 1.45rem;
            place-items: center;
            border-radius: 50%;
            background: #ffe4eb;
            color: #b42352;
            font-size: 0.58rem;
            font-weight: 700;
        }

        .booking-user-name {
            color: #4b5563;
            font-size: 0.66rem;
            white-space: nowrap;
        }

        .booking-user-chevron {
            width: 0.75rem;
            height: 0.75rem;
        }

        .bk-page {
            gap: 0.55rem;
            animation: none;
        }

        .bk-card,
        .bk-hero,
        .bk-kpi {
            border: 1px solid #edf0f4;
            border-radius: 0.6rem;
            background: #fff;
            box-shadow: 0 2px 7px rgba(30, 41, 59, 0.035);
        }

        .bk-card:hover,
        .bk-kpi:hover {
            transform: none;
            border-color: #edf0f4;
            box-shadow: 0 2px 7px rgba(30, 41, 59, 0.035);
        }

        .bk-hero {
            padding: 0.65rem 0.75rem;
            border-color: #f2e6ea;
            background: linear-gradient(105deg, #fff 0%, #fff7f9 100%);
        }

        .bk-hero::after {
            display: none;
        }

        .bk-hero-mark {
            width: 1.55rem;
            height: 1.55rem;
            background: #ffe5eb;
            color: #c02f56;
            box-shadow: none;
        }

        .bk-kicker {
            color: #313743;
            font-size: 0.57rem;
            letter-spacing: 0.03em;
        }

        .bk-guest-name {
            margin-top: 0.18rem;
            color: #252a34;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(1rem, 1.55vw, 1.35rem);
            font-weight: 700;
            letter-spacing: -0.035em;
            line-height: 1.1;
        }

        .bk-hero-meta {
            margin-top: 0.35rem;
            gap: 0.3rem 0.75rem;
            color: #6b7280;
            font-size: 0.59rem;
        }

        .bk-hero-meta span {
            gap: 0.22rem;
        }

        .bk-hero-meta svg {
            width: 0.72rem;
            height: 0.72rem;
            color: #69717d;
        }

        .bk-hero-actions {
            align-items: center;
            gap: 0.35rem;
            margin: 0;
        }

        .bk-action-stack {
            flex-direction: row;
            gap: 0.35rem;
        }

        .bk-btn {
            min-height: 1.75rem;
            padding: 0.35rem 0.55rem;
            gap: 0.25rem;
            border: 1px solid #e4e7ec;
            border-radius: 0.38rem;
            background: #fff;
            color: #353b46;
            font-size: 0.61rem;
            font-weight: 600;
            box-shadow: none;
        }

        .bk-btn svg {
            width: 0.75rem;
            height: 0.75rem;
        }

        .bk-btn-primary {
            border-color: #b21d4b;
            background: #b21d4b;
            color: #fff;
        }

        .bk-btn-primary:hover {
            background: #9f1239;
        }

        .booking-checked-out-button:disabled {
            cursor: default;
            opacity: 1;
        }

        .bk-btn-cancel {
            border-color: #f2c8d2;
            color: #c02f56;
        }

        .bk-badge {
            padding: 0.18rem 0.4rem;
            font-size: 0.5rem;
            font-weight: 700;
            letter-spacing: 0;
        }

        .bk-kpi-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.45rem;
        }

        .bk-kpi {
            min-height: 4.6rem;
            padding: 0.5rem 0.55rem;
            animation: none;
        }

        .bk-kpi.is-overdue {
            border-color: #f8e7c3;
            background: #fffaf0;
        }

        .bk-kpi.is-total {
            border-color: #f7e0e6;
            background: #fff7f9;
        }

        .bk-kpi-title {
            gap: 0.3rem;
            color: #57606d;
            font-size: clamp(0.48rem, 0.65vw, 0.63rem);
            font-weight: 600;
            line-height: 1.2;
        }

        .bk-icon {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.35rem;
            box-shadow: none;
        }

        .bk-icon svg {
            width: 0.7rem;
            height: 0.7rem;
        }

        .bk-kpi .stat-num {
            margin-top: 0.35rem;
            color: #252a34;
            font-size: clamp(0.67rem, 1vw, 0.95rem);
            letter-spacing: -0.025em;
            line-height: 1.15;
        }

        .bk-kpi p {
            margin-top: 0.15rem;
            font-size: 0.53rem;
            line-height: 1.25;
        }

        .bk-progress-row {
            margin-top: 0.32rem;
            gap: 0.3rem;
        }

        .bk-progress {
            height: 0.24rem;
        }

        .bk-split {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 0.5rem;
        }

        .bk-card-wrap {
            padding: 0.65rem;
        }

        .bk-page h3 {
            color: #303641;
            font-size: 0.68rem;
            line-height: 1.25;
        }

        .bk-page .text-xs {
            font-size: 0.55rem;
        }

        .bk-page .text-sm {
            font-size: 0.62rem;
        }

        .bk-page .bk-meta {
            color: #7b8390;
        }

        .bk-page .mt-5 {
            margin-top: 0.55rem;
        }

        .bk-page .mt-4 {
            margin-top: 0.45rem;
        }

        .bk-page .mt-3 {
            margin-top: 0.35rem;
        }

        .bk-page .gap-3 {
            gap: 0.45rem;
        }

        .bk-page .gap-4 {
            gap: 0.55rem;
        }

        body.booking-detail-body .bk-page [class~="sm:grid-cols-2"] {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        body.booking-detail-body .bk-page img:not(.booking-room-photo) {
            height: 4.25rem;
            border-radius: 0.35rem;
        }

        body.booking-detail-body .bk-page .h-28.w-36 {
            width: 36%;
            height: 4.5rem;
            border-radius: 0.35rem;
        }

        body.booking-detail-body .bk-page .booking-room-photo {
            min-height: 100%;
        }

        body.booking-detail-body .bk-page .bk-badge-success {
            background: #eaf8ef;
            color: #169653;
        }

        .bk-timeline {
            padding-left: 1rem;
        }

        .bk-timeline::before {
            left: 0.3rem;
        }

        .bk-dot {
            left: -0.85rem;
            width: 0.55rem;
            height: 0.55rem;
        }

        .bk-nights {
            width: 3.7rem;
            height: 3.7rem;
            background: #f3f4f6;
            color: #59616d;
        }

        .bk-table th {
            padding: 0.35rem;
            border-color: #edf0f4;
            font-size: 0.48rem;
            letter-spacing: 0.04em;
        }

        .bk-table td {
            padding: 0.42rem 0.35rem;
            border-color: #f1f2f5;
            font-size: 0.55rem;
        }

        body.booking-detail-body .pay-ring {
            width: 5.2rem;
            height: 5.2rem;
        }

        .booking-room-photo {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (max-width: 900px) {
            .bk-kpi-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }

            .bk-split {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            }
        }

        @media (max-width: 640px) {
            body.booking-detail-body .app-header {
                padding-inline: 0.55rem;
            }

            body.booking-detail-body .app-main {
                padding: 0.5rem 0.45rem 0.75rem;
            }

            .booking-search {
                width: 1.8rem;
                padding: 0.35rem;
                overflow: hidden;
            }

            .booking-search span,
            .booking-user-name,
            .booking-user-chevron {
                display: none;
            }

            .bk-kpi-grid {
                gap: 0.25rem;
            }

            .bk-kpi {
                min-height: 3.7rem;
                padding: 0.35rem;
            }

            .bk-kpi-title {
                gap: 0.2rem;
            }

            .bk-icon {
                width: 1rem;
                height: 1rem;
            }

            .bk-split {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: 0.35rem;
            }

            .bk-card-wrap {
                padding: 0.45rem;
            }

            .bk-hero {
                padding: 0.5rem;
            }

            .bk-hero-actions {
                gap: 0.2rem;
            }

            .bk-btn {
                min-height: 1.55rem;
                padding: 0.25rem 0.35rem;
                font-size: 0.53rem;
            }
        }

        @media (max-width: 420px) {
            .bk-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .bk-split {
                grid-template-columns: 1fr;
            }
        }

        body.booking-detail-body .app-header {
            height: 3.25rem;
            min-height: 3.25rem;
            padding: 0.4rem 0.85rem;
        }

        body.booking-detail-body .app-header > div:first-child {
            flex: 1;
            min-width: 0;
        }

        body.booking-detail-body .app-header > div:first-child > div {
            min-width: 0;
        }

        body.booking-detail-body .app-header p {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        body.booking-detail-body .app-header-actions {
            flex: 0 0 auto;
        }

        body.booking-detail-body .app-main {
            min-height: calc(100vh - 3.25rem);
            padding: 0.65rem 0.65rem 0.8rem;
        }

        .booking-page-tools {
            gap: 0.45rem;
            font-size: 0.7rem;
        }

        .booking-search {
            width: clamp(8rem, 25vw, 14rem);
            height: 1.85rem;
        }

        .booking-notification {
            width: 1.5rem;
            height: 1.5rem;
        }

        .bk-page {
            gap: 0.48rem;
        }

        .bk-card,
        .bk-hero,
        .bk-kpi {
            border-radius: 0.52rem;
        }

        .bk-hero {
            min-height: 4.15rem;
            padding: 0.5rem 0.62rem;
        }

        .bk-hero > div:first-child {
            align-items: center;
            gap: 0.5rem;
        }

        .bk-hero > div:first-child > div {
            min-width: 0;
        }

        .bk-hero-mark {
            width: 1.4rem;
            height: 1.4rem;
        }

        .bk-kicker {
            font-size: 0.58rem;
        }

        .bk-guest-name {
            margin-top: 0.12rem;
            font-size: clamp(1rem, 2.35vw, 1.3rem);
            line-height: 1.12;
        }

        .bk-hero-meta {
            margin-top: 0.28rem;
            gap: 0.2rem 0.62rem;
            font-size: 0.61rem;
            line-height: 1.25;
        }

        .bk-hero-actions {
            max-width: 48%;
            gap: 0.28rem;
        }

        .bk-action-stack {
            gap: 0.28rem;
        }

        .bk-hero-actions form {
            margin: 0;
        }

        .bk-btn,
        .bk-hero-actions .bk-btn {
            width: auto;
            min-height: 1.7rem;
            padding: 0.3rem 0.48rem;
            font-size: 0.62rem;
            line-height: 1.15;
        }

        .bk-badge {
            font-size: 0.54rem;
            line-height: 1.25;
        }

        .bk-kpi-grid {
            gap: 0.38rem;
        }

        .bk-kpi {
            display: flex;
            min-height: 4.7rem;
            flex-direction: column;
            align-items: flex-start;
            padding: 0.44rem 0.48rem;
        }

        .bk-kpi-title {
            min-height: 1.2rem;
            gap: 0.28rem;
            font-size: clamp(0.57rem, 1.05vw, 0.66rem);
        }

        .bk-icon {
            width: 1.22rem;
            height: 1.22rem;
            border-radius: 0.35rem;
        }

        .bk-icon svg {
            width: 0.73rem;
            height: 0.73rem;
        }

        .bk-kpi .stat-num {
            margin-top: 0.32rem;
            font-size: clamp(0.76rem, 1.45vw, 0.95rem);
            line-height: 1.15;
        }

        .bk-kpi p {
            margin-top: 0.12rem;
            font-size: clamp(0.52rem, 0.92vw, 0.61rem);
            line-height: 1.2;
        }

        .bk-kpi .bk-progress-row {
            width: 100%;
            margin-top: auto;
            padding-top: 0.28rem;
        }

        .bk-progress {
            height: 0.23rem;
        }

        .bk-split {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 0.45rem;
            align-items: stretch;
        }

        .bk-card-wrap {
            min-width: 0;
            padding: 0.58rem;
        }

        .bk-card-wrap > .flex.items-start.gap-3,
        .bk-card-wrap > .flex.items-start.justify-between.gap-3 {
            min-height: 1.65rem;
            align-items: center;
            gap: 0.38rem;
        }

        .bk-page h3 {
            color: #303641;
            font-size: 0.74rem;
            font-weight: 650;
            line-height: 1.2;
        }

        .bk-page .text-xs {
            font-size: 0.59rem;
            line-height: 1.3;
        }

        .bk-page .text-sm {
            font-size: 0.68rem;
            line-height: 1.35;
        }

        .bk-page .bk-meta {
            color: #77808c;
        }

        .bk-page .mt-5 {
            margin-top: 0.55rem;
        }

        .bk-page .mt-4 {
            margin-top: 0.45rem;
        }

        .bk-page .mt-3 {
            margin-top: 0.35rem;
        }

        .bk-page .mt-2 {
            margin-top: 0.25rem;
        }

        .bk-page .gap-3 {
            gap: 0.42rem;
        }

        .bk-page .gap-4 {
            gap: 0.5rem;
        }

        .bk-page .space-y-4 > :not(:first-child) {
            margin-top: 0.45rem;
        }

        .bk-page .space-y-6 > :not(:first-child) {
            margin-top: 0.65rem;
        }

        .bk-page .bk-timeline .font-semibold {
            font-size: 0.68rem;
            line-height: 1.25;
        }

        .bk-page .bk-timeline [class~="text-[11px]"] {
            font-size: 0.56rem;
            letter-spacing: 0.08em;
        }

        .bk-nights {
            width: 3.75rem;
            height: 3.75rem;
        }

        .bk-page .bk-nights .stat-num {
            font-size: 1.15rem;
        }

        .bk-page .bk-nights [class~="text-[10px]"] {
            font-size: 0.52rem;
            letter-spacing: 0.08em;
        }

        body.booking-detail-body .bk-page > .bk-stay-payment > .bk-card {
            min-height: 8.15rem;
        }

        body.booking-detail-body .bk-page > .bk-stay-payment > .bk-card:nth-child(2) > .mt-5 {
            gap: 0.55rem;
        }

        body.booking-detail-body .bk-page .pay-ring {
            width: 5.05rem;
            height: 5.05rem;
        }

        body.booking-detail-body .bk-page svg[viewBox="0 0 120 120"] {
            width: 5.05rem;
            height: 5.05rem;
        }

        body.booking-detail-body .bk-page #paid-pct-label {
            font-size: 1.05rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article {
            min-height: 15.7rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-5 {
            gap: 0.5rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-5 .h-12.w-12 {
            width: 2rem;
            height: 2rem;
            font-size: 0.62rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-5 .text-lg {
            font-size: 0.73rem;
            line-height: 1.2;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid {
            gap: 0.22rem;
            font-size: 0.59rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid > div {
            gap: 0.3rem;
            overflow-wrap: anywhere;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid svg {
            width: 0.72rem;
            height: 0.72rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid-cols-3 {
            gap: 0.3rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid-cols-3 > div {
            padding: 0.3rem 0.2rem;
            border-radius: 0.4rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid-cols-3 .h-8.w-8 {
            width: 1.1rem;
            height: 1.1rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid-cols-3 .stat-num {
            font-size: 0.77rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child [class~="sm:grid-cols-2"] {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.35rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child img:not(.booking-room-photo) {
            width: 100%;
            height: 3.2rem;
            border-radius: 0.35rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > .space-y-4 {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 0.45rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > .space-y-4 > article {
            padding: 0.58rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > .space-y-4 > article:first-child {
            flex: 0 0 auto;
        }

        .booking-checkout-complete {
            flex: 1 1 auto;
        }

        .booking-checkout-facts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.35rem;
            margin-top: 0.75rem;
        }

        .booking-checkout-facts > div {
            display: flex;
            min-width: 0;
            flex-direction: column;
            justify-content: center;
            gap: 0.2rem;
            border-radius: 0.45rem;
            background: #f3fbf6;
            padding: 0.48rem;
        }

        .booking-checkout-facts span {
            color: #77808c;
            font-size: 0.6rem;
        }

        .booking-checkout-facts strong {
            color: #303641;
            font-size: 0.69rem;
            font-weight: 600;
        }

        .booking-checkout-note {
            margin-top: auto;
            padding-top: 0.6rem;
            color: #77808c;
            font-size: 0.65rem;
        }

        body.booking-detail-body .bk-page .booking-room-photo {
            object-position: center;
        }

        body.booking-detail-body .bk-page .h-28.w-36 {
            width: 5.55rem;
            height: 4.2rem;
            flex: 0 0 5.55rem;
        }

        body.booking-detail-body .bk-page .text-xl {
            font-size: 0.82rem;
            line-height: 1.2;
        }

        body.booking-detail-body .bk-page .input {
            min-height: 1.85rem;
            padding: 0.35rem 0.48rem;
            border-radius: 0.38rem;
            font-size: 0.62rem;
        }

        body.booking-detail-body .bk-page .btn {
            min-height: 1.8rem;
            padding: 0.32rem 0.55rem;
            border-radius: 0.38rem;
            font-size: 0.62rem;
        }

        body.booking-detail-body .bk-page .btn svg {
            width: 0.78rem;
            height: 0.78rem;
        }

        body.booking-detail-body .bk-page #checkout-timer .grid-cols-4 {
            gap: 0.24rem;
        }

        body.booking-detail-body .bk-page #checkout-timer .grid-cols-4 > div {
            padding: 0.32rem 0.12rem;
            border-radius: 0.42rem;
        }

        body.booking-detail-body .bk-page #checkout-timer .grid-cols-4 .stat-num {
            font-size: 0.85rem;
        }

        body.booking-detail-body .bk-page #checkout-timer [class~="text-[10px]"] {
            font-size: 0.49rem;
            letter-spacing: 0.04em;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras > article {
            min-height: 6rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras .bk-table {
            table-layout: fixed;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras .bk-table th {
            padding: 0.28rem 0.22rem;
            font-size: 0.52rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras .bk-table td {
            padding: 0.34rem 0.22rem;
            font-size: 0.59rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras .bk-table .btn {
            min-height: 1.5rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras .min-h-40 {
            min-height: 4.7rem;
            border-radius: 0.45rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras [class~="bg-slate-50/60"] {
            padding: 0.48rem;
            border-radius: 0.42rem;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid {
            grid-template-columns: minmax(0, 1fr) minmax(10rem, 35%);
        }

        body.booking-detail-body .bk-page > .bk-summary .bk-card-wrap {
            padding: 0.62rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .space-y-3 > :not(:first-child) {
            margin-top: 0.34rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .space-y-3 > div {
            font-size: 0.62rem;
            line-height: 1.2;
        }

        body.booking-detail-body .bk-page > .bk-summary .border-t.pt-3 {
            padding-top: 0.35rem;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > div:last-child {
            justify-content: center;
            gap: 0.5rem;
            padding: 0.68rem;
            border-color: #f3dfe5;
            background: #fff5f7;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > div:last-child .text-4xl {
            margin-top: 0.16rem;
            font-size: 1.25rem;
            line-height: 1.1;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > div:last-child .btn {
            margin-top: 0.4rem;
            width: 100%;
            min-height: 1.85rem;
            font-size: 0.63rem;
        }

        @media (max-width: 640px) {
            .bk-hero > div:first-child {
                align-items: flex-start;
            }

            .bk-hero-actions {
                max-width: 55%;
            }

            .bk-hero-actions .bk-btn {
                width: auto;
            }

            .bk-page > .bk-guest-room > .space-y-4 {
                gap: 0.35rem;
            }

            body.booking-detail-body .bk-page > .bk-guest-room > article {
                min-height: 14rem;
            }
        }

        @media (max-width: 420px) {
            body.booking-detail-body .bk-page > .bk-guest-room > article {
                min-height: auto;
            }

            body.booking-detail-body .bk-page > .bk-summary > .grid {
                grid-template-columns: minmax(0, 1fr) 9rem;
            }
        }

        body.booking-detail-body .app-header {
            height: 3.45rem;
            min-height: 3.45rem;
            padding-inline: 1rem;
        }

        body.booking-detail-body .app-main {
            min-height: calc(100vh - 3.45rem);
            padding: 0.78rem 0.8rem 1rem;
        }

        body.booking-detail-body .app-header p {
            font-size: 0.78rem;
        }

        .booking-page-tools {
            font-size: 0.75rem;
        }

        .booking-search {
            height: 2rem;
        }

        .bk-page {
            gap: 0.58rem;
        }

        .bk-hero {
            min-height: 4.65rem;
            padding: 0.58rem 0.72rem;
        }

        .bk-hero-mark {
            width: 1.55rem;
            height: 1.55rem;
        }

        .bk-kicker {
            font-size: 0.63rem;
        }

        .bk-guest-name {
            font-size: clamp(1.12rem, 2.55vw, 1.42rem);
        }

        .bk-hero-meta {
            gap: 0.24rem 0.72rem;
            font-size: 0.68rem;
        }

        .bk-hero-meta svg {
            width: 0.78rem;
            height: 0.78rem;
        }

        .bk-hero-actions {
            gap: 0.34rem;
        }

        .bk-action-stack {
            gap: 0.34rem;
        }

        .bk-btn,
        .bk-hero-actions .bk-btn {
            min-height: 1.95rem;
            padding: 0.36rem 0.56rem;
            font-size: 0.69rem;
        }

        .bk-btn svg {
            width: 0.82rem;
            height: 0.82rem;
        }

        .bk-badge {
            padding: 0.2rem 0.46rem;
            font-size: 0.6rem;
        }

        .bk-page .bk-icon {
            width: 1.38rem;
            height: 1.38rem;
        }

        .bk-page .bk-icon svg {
            width: 0.82rem;
            height: 0.82rem;
        }

        .bk-kpi-grid {
            gap: 0.46rem;
        }

        .bk-kpi {
            min-height: 5.2rem;
            padding: 0.52rem 0.58rem;
        }

        .bk-kpi-title {
            min-height: 1.35rem;
            gap: 0.34rem;
            font-size: clamp(0.63rem, 1.15vw, 0.73rem);
        }

        .bk-kpi .stat-num {
            margin-top: 0.4rem;
            font-size: clamp(0.86rem, 1.55vw, 1.05rem);
        }

        .bk-kpi p {
            margin-top: 0.16rem;
            font-size: clamp(0.58rem, 0.98vw, 0.68rem);
        }

        .bk-kpi .bk-progress-row {
            padding-top: 0.34rem;
        }

        .bk-progress {
            height: 0.28rem;
        }

        .bk-split {
            gap: 0.55rem;
        }

        .bk-card-wrap {
            padding: 0.68rem;
        }

        .bk-card-wrap > .flex.items-start.gap-3,
        .bk-card-wrap > .flex.items-start.justify-between.gap-3 {
            min-height: 1.85rem;
            gap: 0.46rem;
        }

        .bk-page h3 {
            font-size: 0.81rem;
        }

        .bk-page .text-xs {
            font-size: 0.65rem;
        }

        .bk-page .text-sm {
            font-size: 0.75rem;
        }

        .bk-page .mt-5 {
            margin-top: 0.65rem;
        }

        .bk-page .mt-4 {
            margin-top: 0.55rem;
        }

        .bk-page .mt-3 {
            margin-top: 0.42rem;
        }

        .bk-page .mt-2 {
            margin-top: 0.32rem;
        }

        .bk-page .gap-3 {
            gap: 0.5rem;
        }

        .bk-page .gap-4 {
            gap: 0.6rem;
        }

        .bk-page .space-y-4 > :not(:first-child) {
            margin-top: 0.54rem;
        }

        .bk-page .space-y-6 > :not(:first-child) {
            margin-top: 0.78rem;
        }

        .bk-page .bk-timeline .font-semibold {
            font-size: 0.76rem;
        }

        .bk-page .bk-timeline [class~="text-[11px]"] {
            font-size: 0.62rem;
        }

        .bk-nights {
            width: 4.25rem;
            height: 4.25rem;
        }

        .bk-page .bk-nights .stat-num {
            font-size: 1.28rem;
        }

        .bk-page .bk-nights [class~="text-[10px]"] {
            font-size: 0.58rem;
        }

        body.booking-detail-body .bk-page > .bk-stay-payment > .bk-card {
            min-height: 9rem;
        }

        body.booking-detail-body .bk-page .pay-ring,
        body.booking-detail-body .bk-page svg[viewBox="0 0 120 120"] {
            width: 5.75rem;
            height: 5.75rem;
        }

        body.booking-detail-body .bk-page #paid-pct-label {
            font-size: 1.2rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article {
            min-height: 17.2rem;
        }

        .booking-checkout-facts > div {
            padding: 0.55rem;
        }

        .booking-checkout-facts span {
            font-size: 0.65rem;
        }

        .booking-checkout-facts strong {
            font-size: 0.75rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-5 .h-12.w-12 {
            width: 2.3rem;
            height: 2.3rem;
            font-size: 0.7rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-5 .text-lg {
            font-size: 0.82rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid {
            gap: 0.3rem;
            font-size: 0.66rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid-cols-3 > div {
            padding: 0.38rem 0.24rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid-cols-3 .h-8.w-8 {
            width: 1.25rem;
            height: 1.25rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child > .mt-4.grid-cols-3 .stat-num {
            font-size: 0.88rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article:first-child img:not(.booking-room-photo) {
            height: 3.7rem;
        }

        body.booking-detail-body .bk-page .h-28.w-36 {
            width: 6.3rem;
            height: 4.8rem;
            flex-basis: 6.3rem;
        }

        body.booking-detail-body .bk-page .text-xl {
            font-size: 0.92rem;
        }

        body.booking-detail-body .bk-page .input {
            min-height: 2.05rem;
            font-size: 0.7rem;
        }

        body.booking-detail-body .bk-page .btn {
            min-height: 2rem;
            font-size: 0.7rem;
        }

        body.booking-detail-body .bk-page #checkout-timer .grid-cols-4 > div {
            padding: 0.4rem 0.16rem;
        }

        body.booking-detail-body .bk-page #checkout-timer .grid-cols-4 .stat-num {
            font-size: 0.98rem;
        }

        body.booking-detail-body .bk-page #checkout-timer [class~="text-[10px]"] {
            font-size: 0.56rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras > article {
            min-height: 6.8rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras .bk-table th {
            padding: 0.34rem 0.27rem;
            font-size: 0.59rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras .bk-table td {
            padding: 0.42rem 0.27rem;
            font-size: 0.67rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras .min-h-40 {
            min-height: 5.4rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .bk-card-wrap {
            padding: 0.74rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .space-y-3 > :not(:first-child) {
            margin-top: 0.42rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .space-y-3 > div {
            font-size: 0.69rem;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > div:last-child {
            gap: 0.62rem;
            padding: 0.8rem;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > div:last-child .text-4xl {
            font-size: 1.42rem;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > div:last-child .btn {
            min-height: 2.05rem;
            font-size: 0.7rem;
        }

        @media (max-width: 640px) {
            body.booking-detail-body .app-main {
                padding: 0.65rem 0.55rem 0.85rem;
            }

            .bk-page {
                gap: 0.5rem;
            }

            .bk-kpi {
                min-height: 4.45rem;
                padding: 0.4rem;
            }

            .bk-kpi-title {
                font-size: 0.61rem;
            }

            .bk-kpi .stat-num {
                font-size: 0.88rem;
            }

            .bk-page h3 {
                font-size: 0.76rem;
            }

            .bk-page .text-xs {
                font-size: 0.61rem;
            }

            .bk-page .text-sm {
                font-size: 0.7rem;
            }

            .bk-card-wrap {
                padding: 0.55rem;
            }

            .bk-hero {
                min-height: auto;
                padding: 0.55rem;
            }

            .bk-hero-meta {
                font-size: 0.62rem;
            }

            .bk-hero-actions .bk-btn {
                min-height: 1.8rem;
                padding-inline: 0.42rem;
                font-size: 0.62rem;
            }

            body.booking-detail-body .bk-page > .bk-guest-room > article {
                min-height: 15.5rem;
            }

            body.booking-detail-body .bk-page .h-28.w-36 {
                width: 5.55rem;
                height: 4.35rem;
                flex-basis: 5.55rem;
            }
        }

        @media (max-width: 420px) {
            .bk-kpi {
                min-height: 4.6rem;
            }

            .bk-kpi-title {
                font-size: 0.68rem;
            }

            .bk-kpi p {
                font-size: 0.62rem;
            }

            .bk-page h3 {
                font-size: 0.82rem;
            }

            body.booking-detail-body .bk-page > .bk-guest-room > article {
                min-height: auto;
            }

            body.booking-detail-body .bk-page > .bk-summary > .grid {
                grid-template-columns: minmax(0, 1fr) 9.5rem;
            }
        }

        .bk-page {
            gap: 0.68rem;
        }

        .bk-hero {
            min-height: 4.9rem;
            padding: 0.66rem 0.8rem;
        }

        .bk-guest-name {
            font-size: clamp(1.18rem, 2.7vw, 1.5rem);
        }

        .bk-hero-meta {
            font-size: 0.72rem;
        }

        .bk-hero-actions .bk-btn:disabled {
            cursor: not-allowed;
            opacity: 0.68;
        }

        .bk-action-stack {
            min-width: 10.5rem;
            flex-direction: column;
            align-items: stretch;
            gap: 0.52rem;
            padding: 0.42rem;
            border: 1px solid #edf0f4;
            border-radius: 0.48rem;
            background: #fff;
        }

        .booking-services-button,
        .bk-action-stack > .booking-checked-out-button {
            width: 9.5rem;
            min-height: 2.1rem;
            justify-content: center;
            padding: 0.42rem 0.62rem;
            font-size: 0.72rem;
        }

        .bk-action-stack form {
            display: flex;
            width: 100%;
        }

        .bk-action-stack .bk-btn {
            width: 100%;
            min-height: 2.1rem;
            justify-content: center;
            padding: 0.42rem 0.62rem;
            font-size: 0.72rem;
        }

        .bk-cancel-action {
            display: flex;
            flex: 0 0 100%;
            justify-content: flex-end;
        }

        .bk-cancel-action form {
            display: flex;
            width: 10.5rem;
        }

        .bk-cancel-action .bk-btn {
            width: 100%;
            min-height: 2.1rem;
            justify-content: center;
            padding: 0.42rem 0.62rem;
            font-size: 0.72rem;
        }

        .booking-checked-out-button:disabled {
            cursor: default;
            opacity: 1;
        }

        .booking-cancel-disabled:disabled {
            border: 1px solid #ef9aaa;
            background: #fff;
            color: #bd2746;
            cursor: not-allowed;
            opacity: 1;
        }

        .bk-kpi {
            min-height: 5.55rem;
            padding: 0.6rem 0.66rem;
        }

        .bk-kpi-title {
            font-size: clamp(0.67rem, 1.2vw, 0.78rem);
        }

        .bk-kpi .stat-num {
            font-size: clamp(0.92rem, 1.65vw, 1.12rem);
        }

        .bk-kpi p {
            font-size: clamp(0.62rem, 1vw, 0.72rem);
        }

        .bk-card-wrap {
            padding: 0.78rem;
        }

        .bk-page h3 {
            font-size: 0.86rem;
        }

        .bk-page .text-xs {
            font-size: 0.68rem;
        }

        .bk-page .text-sm {
            font-size: 0.78rem;
        }

        body.booking-detail-body .bk-page > .bk-stay-payment > .bk-card {
            min-height: 9.6rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > article {
            min-height: 18.2rem;
        }

        body.booking-detail-body .bk-page > .bk-rent-extras > article {
            min-height: 7.4rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .bk-card-wrap {
            padding: 0.86rem;
        }

        @media (max-width: 640px) {
            .bk-hero-actions {
                width: 100%;
                max-width: 100%;
                justify-content: flex-start;
            }

            .bk-action-stack {
                min-width: 0;
                width: auto;
                padding: 0.34rem;
            }

            .bk-cancel-action form,
            .bk-cancel-action > .bk-btn {
                width: 10.5rem;
            }

            .booking-services-button,
            .bk-action-stack > .booking-checked-out-button {
                width: 9.5rem;
            }

            .bk-kpi {
                min-height: 4.85rem;
            }

            body.booking-detail-body .bk-page > .bk-guest-room > article {
                min-height: 16.2rem;
            }
        }

        @media (max-width: 420px) {
            body.booking-detail-body .bk-page > .bk-guest-room > article {
                min-height: auto;
            }
        }

        .bk-hero {
            min-height: 9.2rem;
            padding: 0.85rem 1rem;
            border-radius: 1rem;
            background: linear-gradient(105deg, #fff8fa 0%, #fff0f3 100%);
        }

        .bk-hero > div:first-child {
            align-items: center;
            gap: 0.9rem;
        }

        .bk-hero-mark {
            width: 3rem;
            height: 3rem;
            background: #ffe5eb;
            color: #c02f56;
        }

        .bk-kicker {
            font-size: 0.75rem;
        }

        .bk-guest-name {
            margin-top: 0.24rem;
            font-size: clamp(1.5rem, 2.15vw, 2.1rem);
            line-height: 1.08;
        }

        .bk-hero-meta {
            margin-top: 0.65rem;
            gap: 0.45rem 1.3rem;
            font-size: 0.88rem;
        }

        .bk-hero-meta svg {
            width: 1rem;
            height: 1rem;
        }

        .bk-hero-actions {
            display: grid;
            grid-template-columns: repeat(4, max-content);
            grid-template-rows: auto auto;
            align-items: center;
            justify-content: end;
            gap: 0.55rem;
            position: absolute;
            top: 0.85rem;
            right: 1rem;
            max-width: none;
            margin: 0;
        }

        .bk-action-stack {
            display: contents;
        }

        .bk-action-stack form {
            margin: 0;
        }

        .bk-hero-actions > .bk-btn,
        .bk-action-stack .bk-btn {
            min-height: 3rem;
            padding: 0.65rem 1rem;
            border-radius: 0.7rem;
            font-size: 0.82rem;
        }

        .bk-hero-actions > .bk-btn svg,
        .bk-action-stack .bk-btn svg {
            width: 1.05rem;
            height: 1.05rem;
        }

        .bk-hero-actions .booking-services-button {
            width: auto;
        }

        .bk-action-stack .bk-btn-primary {
            width: 10.8rem;
        }

        .bk-action-stack .booking-checked-out-button:disabled {
            border-color: #a80f3d;
            background: #a80f3d;
            color: #fff;
            opacity: 1;
            box-shadow: 0 5px 12px rgba(168, 15, 61, 0.2);
        }

        .bk-cancel-action {
            grid-column: 4;
            grid-row: 2;
            width: 10.8rem;
            flex: none;
        }

        .bk-cancel-action form,
        .bk-cancel-action > .bk-btn {
            width: 100%;
        }

        .bk-cancel-action .bk-btn {
            min-height: 2.8rem;
            padding: 0.6rem 0.85rem;
            font-size: 0.8rem;
        }

        @media (max-width: 1360px) {
            .bk-hero > div:first-child {
                align-items: flex-start;
            }

            .bk-hero-actions {
                position: static;
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                grid-template-rows: auto auto auto;
                justify-content: stretch;
            }

            .bk-hero-actions > .bk-btn,
            .bk-action-stack .bk-btn {
                width: 100%;
            }

            .bk-action-stack form {
                width: 100%;
            }

            .bk-cancel-action {
                grid-column: 2;
                grid-row: 3;
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .bk-hero {
                min-height: 0;
                padding: 0.9rem;
            }

            .bk-hero > div:first-child {
                width: 100%;
                align-items: flex-start;
            }

            .bk-hero-mark {
                width: 2.5rem;
                height: 2.5rem;
            }

            .bk-guest-name {
                font-size: clamp(1.35rem, 6vw, 1.8rem);
            }

            .bk-hero-actions {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                grid-template-rows: auto auto auto;
                max-width: 100%;
                justify-content: stretch;
                gap: 0.45rem;
            }

            .bk-hero-actions > .bk-btn,
            .bk-action-stack .bk-btn {
                width: 100%;
                min-height: 2.65rem;
                padding: 0.5rem 0.6rem;
                font-size: 0.76rem;
            }

            .bk-action-stack form {
                width: 100%;
            }

            .bk-cancel-action {
                grid-column: 2;
                grid-row: 3;
                width: 100%;
            }

            .bk-cancel-action .bk-btn {
                min-height: 2.65rem;
                font-size: 0.72rem;
            }
        }

        .bk-payment-chart {
            width: 184px;
            height: 184px;
            flex: 0 0 184px;
        }

        .bk-page .bk-nights {
            border-radius: 0.85rem;
        }

        .bk-kpi .stat-num {
            font-weight: 500;
        }

        .bk-payment-chart svg {
            width: 184px !important;
            height: 184px !important;
        }

        .bk-payment-breakdown {
            min-width: 12rem;
            border: 1px solid #f7e0e6;
            border-radius: 0.8rem;
            background: #fff7f9;
            padding: 0.75rem 1rem;
        }

        .bk-payment-breakdown > div {
            min-height: 2.5rem;
            padding: 0.5rem 0;
        }

        .bk-payment-breakdown > div + div {
            border-top: 1px solid #f3e2e7;
        }

        @media (max-width: 640px) {
            .bk-payment-chart,
            .bk-payment-chart svg {
                width: 168px !important;
                height: 168px !important;
            }

            .bk-payment-chart {
                flex-basis: 168px;
            }

            .bk-payment-breakdown {
                min-width: 0;
                padding: 0.6rem 0.8rem;
            }
        }

        body.booking-detail-body .bk-page > .bk-guest-room > .space-y-4 > article:first-child {
            min-height: 10rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > .space-y-4 > :not(:first-child) {
            margin-top: 0;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > .space-y-4 {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > .space-y-4 > article:first-child {
            flex: 0 0 auto;
        }

        body.booking-detail-body .bk-page > .bk-guest-room > .space-y-4 > article {
            padding: 0.5rem;
        }

        body.booking-detail-body .bk-page .h-28.w-36 {
            width: 7rem;
            height: 6rem;
            flex-basis: 7rem;
        }

        body.booking-detail-body .bk-page #checkout-timer,
        body.booking-detail-body .bk-page .booking-checkout-complete {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            justify-content: space-between;
            min-height: 10rem;
        }

        .booking-checkout-facts {
            gap: 0.28rem;
            margin-top: 0.55rem;
        }

        .booking-checkout-facts > div {
            gap: 0.12rem;
            padding: 0.35rem;
        }

        .booking-checkout-note {
            padding-top: 0.35rem;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid {
            grid-template-columns: minmax(0, 1fr) minmax(15rem, 36%);
            align-items: stretch;
        }

        body.booking-detail-body .bk-page > .bk-summary .bk-card-wrap {
            padding: 1rem 1.15rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .space-y-3 {
            margin-top: 0.8rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .space-y-3 > div {
            min-height: 1.85rem;
            align-items: center;
            border-bottom: 1px solid #edf0f4;
            padding: 0.3rem 0.55rem;
            font-size: 0.82rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .space-y-3 > .summary-total {
            min-height: 2.8rem;
            margin-top: 0.35rem;
            border: 0;
            border-radius: 0.55rem;
            background: #f1f3f5;
            padding: 0.55rem 0.7rem;
            font-size: 0.95rem;
        }

        body.booking-detail-body .bk-page > .bk-summary .summary-total .stat-num {
            font-size: 1.12rem;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > .bk-summary-due {
            position: relative;
            justify-content: flex-start !important;
            gap: 0.45rem;
            margin: 0.8rem;
            border: 1px solid #f6e0e6;
            border-radius: 0.8rem;
            background: #fff2f5;
            padding: 1.1rem 1.2rem;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > .bk-summary-due > .bk-summary-due-content {
            position: absolute;
            top: 50%;
            right: 1.2rem;
            left: 1.2rem;
            transform: translateY(-50%);
        }

        body.booking-detail-body .bk-page > .bk-summary .bk-summary-due-content > .text-sm {
            font-size: 0.95rem;
            font-weight: 700;
        }

        body.booking-detail-body .bk-page > .bk-summary .bk-summary-due-content > .text-4xl {
            font-size: 2.15rem;
            font-weight: 800;
        }

        body.booking-detail-body .bk-page > .bk-summary .bk-summary-due-content > p {
            font-size: 0.82rem;
            font-weight: 600;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > .bk-summary-due > .mt-6 {
            margin-top: auto !important;
        }

        body.booking-detail-body .bk-page > .bk-summary .bk-summary-due .text-4xl {
            margin-top: 0.35rem;
            font-size: 1.9rem;
            line-height: 1.1;
        }

        body.booking-detail-body .bk-page > .bk-summary > .grid > .bk-summary-due .btn {
            min-height: 3.3rem !important;
            margin-top: 1rem;
            border-radius: 0.55rem;
            font-size: 0.84rem;
        }

        @media (max-width: 640px) {
            body.booking-detail-body .bk-page > .bk-summary > .grid {
                grid-template-columns: minmax(0, 1fr);
            }

            body.booking-detail-body .bk-page > .bk-summary .bk-card-wrap {
                padding: 0.85rem;
            }

            body.booking-detail-body .bk-page > .bk-summary .bk-summary-due {
                margin: 0 0.65rem 0.65rem;
                padding: 0.9rem;
            }
        }

        @media (max-width: 640px) {
            body.booking-detail-body .bk-page .h-28.w-36 {
                width: 6.2rem;
                height: 5.2rem;
                flex-basis: 6.2rem;
            }
        }
    </style>

    <div class="bk-page">
        <section class="bk-hero">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-start gap-3.5">
                    <span class="bk-hero-mark"><x-icon name="bookmark" /></span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="bk-kicker">Booking #{{ $booking->id }}</p>
                            <span class="bk-badge {{ $booking->paymentState() === 'paid' ? 'bk-badge-success' : ($booking->paymentState() === 'partial' ? 'bk-badge-warning' : 'bk-badge-danger') }}">
                                {{ $booking->paymentStatusLabel() }} payment
                            </span>
                        </div>
                        <h2 class="bk-guest-name">{{ $booking->customer->name }}</h2>
                        <div class="bk-hero-meta">
                            <span><x-icon name="user" size="sm" /> S/O {{ $booking->customer->father_name ?: '—' }}</span>
                            <span><x-icon name="bed" size="sm" /> Room {{ $booking->room->number }}</span>
                            <span><x-icon name="calendar" size="sm" /> {{ $booking->check_in->format('d M Y') }} – {{ $booking->check_out->format('d M Y') }} ({{ $baseNights }} {{ \Illuminate\Support\Str::plural('night', $baseNights) }})</span>
                        </div>
                    </div>
                </div>
                <div class="bk-hero-actions">
                    <a href="{{ route('bookings.bill', $booking) }}" class="bk-btn">
                        <x-icon name="print" size="sm" /> Bill
                    </a>
                    <a href="{{ route('bookings.services.download', $booking) }}" class="bk-btn booking-services-button">
                        <x-icon name="cart" size="sm" /> Services
                    </a>
                    @if($booking->isActive() || $booking->status === 'checked_out')
                        <a href="{{ route('bookings.edit', $booking) }}" class="bk-btn">
                            <x-icon name="edit" size="sm" /> Edit
                        </a>
                    @endif
                    <div class="bk-action-stack">
                        @if($booking->status === 'reserved')
                            <form method="POST" action="{{ route('bookings.check-in', $booking) }}">@csrf<button class="bk-btn bk-btn-primary"><x-icon name="door" size="sm" /> Check In</button></form>
                        @endif
                        @if($booking->status === 'checked_in')
                            <button type="button" class="bk-btn bk-btn-primary" data-modal-open="checkout-modal"><x-icon name="logout" size="sm" /> Check Out</button>
                        @elseif($booking->status === 'checked_out')
                            <button type="button" class="bk-btn bk-btn-primary booking-checked-out-button" disabled aria-disabled="true">
                                <x-icon name="check" size="sm" /> Checked Out
                            </button>
                        @endif
                    </div>
                    @if($booking->isActive() || $booking->status === 'checked_out')
                        <div class="bk-cancel-action">
                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?')">@csrf<button class="bk-btn bk-btn-cancel"><x-icon name="x-circle" size="sm" /> Cancel Booking</button></form>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="bk-kpi-grid">
            <article class="bk-kpi">
                <div class="bk-kpi-title">
                    <span class="bk-icon bg-emerald-50 text-emerald-700"><x-icon name="bed" size="sm" /></span>
                    Stay Progress
                </div>
                <div class="stat-num">{{ $nightsCompleted }} / {{ $baseNights }}</div>
                <p class="mt-1.5 text-xs bk-meta">Nights completed</p>
                <div class="bk-progress-row">
                    <div class="bk-progress"><span style="width: {{ $stayPct }}%"></span></div>
                    <span class="text-[11px] font-medium bk-meta">{{ $stayPct }}%</span>
                </div>
            </article>
            <article class="bk-kpi">
                <div class="bk-kpi-title">
                    <span class="bk-icon bg-emerald-50 text-emerald-700"><x-icon name="card" size="sm" /></span>
                    Payment Status
                </div>
                <div class="stat-num">@money($totalPaid)</div>
                <p class="mt-1.5 text-xs bk-meta">of @money($totalBilled) paid</p>
                <div class="bk-progress-row">
                    <div class="bk-progress"><span style="width: {{ $paidPct }}%"></span></div>
                    <span class="text-[11px] font-medium bk-meta">{{ $paidPct }}%</span>
                </div>
            </article>
            <article id="kpi-checkout-card" class="bk-kpi {{ $booking->status === 'checked_in' && $lateDays > 0 ? 'is-overdue' : '' }}">
                <div class="bk-kpi-title">
                    <span class="bk-icon bg-amber-50 text-amber-700"><x-icon name="clock" size="sm" /></span>
                    Checkout In
                </div>
                @if($booking->status === 'checked_in')
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <div id="kpi-cd-days" class="stat-num !mt-0">0 days</div>
                        <span id="kpi-overdue-badge" class="bk-badge bk-badge-warning {{ $lateDays > 0 ? '' : 'hidden' }}">Overdue</span>
                    </div>
                    <p id="kpi-cd-hms" class="mt-1.5 text-sm font-semibold {{ $lateDays > 0 ? 'text-crimson' : 'bk-meta' }}">00h 00m 00s</p>
                @elseif($booking->status === 'checked_out')
                    <div class="stat-num">Done</div>
                    <p class="mt-1.5 text-xs bk-meta">{{ $booking->checked_out_at?->format('d M Y, h:i A') }}</p>
                @else
                    <div class="stat-num">—</div>
                    <p class="mt-1.5 text-xs bk-meta">Awaiting check-in</p>
                @endif
            </article>
            <article class="bk-kpi">
                <div class="bk-kpi-title">
                    <span class="bk-icon bg-sky-50 text-sky-700"><x-icon name="door" size="sm" /></span>
                    Room {{ $booking->room->number }}
                </div>
                <div class="stat-num text-[1.45rem]">{{ $bedLabel }}</div>
                <p class="mt-1.5 text-xs bk-meta">{{ $floorLabel }}</p>
            </article>
            <article class="bk-kpi">
                <div class="bk-kpi-title">
                    <span class="bk-icon bg-violet-50 text-violet-700"><x-icon name="users" size="sm" /></span>
                    Guests
                </div>
                <div class="stat-num">{{ $guestCount }} {{ \Illuminate\Support\Str::plural('Guest', $guestCount) }}</div>
                <p class="mt-1.5 text-xs bk-meta">{{ $booking->males }} Male • {{ $booking->females }} Female • {{ $booking->children }} Children</p>
            </article>
            <article class="bk-kpi is-total">
                <div class="bk-kpi-title">
                    <span class="bk-icon bg-rose-100 text-crimson"><x-icon name="receipt" size="sm" /></span>
                    Total Amount
                </div>
                <div class="stat-num">@money($totalBilled)</div>
                <p class="mt-1.5 text-sm font-semibold text-crimson">@money($totalDue) due</p>
            </article>
        </section>

        <section class="bk-split bk-stay-payment">
            <article class="bk-card bk-card-wrap">
                <div class="flex items-start gap-3">
                    <span class="bk-icon bg-rose-50 text-crimson"><x-icon name="calendar" size="sm" /></span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Stay Timeline</h3>
                        <p class="text-xs bk-meta">Check-in and check-out details</p>
                    </div>
                </div>
                <div class="mt-5 flex items-start justify-between gap-4">
                    <div class="bk-timeline flex-1 space-y-6">
                        <div class="relative">
                            <span class="bk-dot bg-emerald-500"></span>
                            <div class="text-[11px] font-bold uppercase tracking-[0.18em] bk-meta">Check-in</div>
                            <div class="mt-1 font-semibold text-slate-900">{{ $booking->check_in->format('D, d M Y') }}</div>
                            <div class="text-sm bk-meta">{{ $booking->checked_in_at?->format('h:i A') ?? 'Pending' }}</div>
                        </div>
                        <div class="relative">
                            <span class="bk-dot bg-crimson"></span>
                            <div class="text-[11px] font-bold uppercase tracking-[0.18em] bk-meta">Check-out</div>
                            <div class="mt-1 font-semibold text-slate-900">{{ $booking->check_out->format('D, d M Y') }}</div>
                            <div class="text-sm bk-meta">{{ $booking->checked_out_at?->format('h:i A') ?? $scheduledCheckOutAt->format('h:i A').' (cutoff)' }}</div>
                        </div>
                    </div>
                    <div class="bk-nights">
                        <div>
                            <x-icon name="moon" size="sm" class="bk-night-moon" />
                            <div class="stat-num text-2xl font-semibold">{{ $baseNights }}</div>
                            <div class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em]">Nights</div>
                        </div>
                    </div>
                </div>
                <div class="mt-5">
                    <div class="mb-2 flex items-center justify-between text-xs bk-meta">
                        <span>Stay progress</span>
                        <span>{{ $stayPct }}%</span>
                    </div>
                    <div class="bk-progress"><span style="width: {{ $stayPct }}%"></span></div>
                </div>
            </article>

            <article class="bk-card bk-card-wrap">
                <div class="flex items-start gap-3">
                    <span class="bk-icon bg-emerald-50 text-emerald-700"><x-icon name="wallet" size="sm" /></span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Payment Overview</h3>
                        <p class="text-xs bk-meta">Payment collection status and breakdown</p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-6">
                    <div class="bk-payment-chart relative mx-auto grid place-items-center">
                        <svg viewBox="0 0 120 120" aria-hidden="true">
                            <circle cx="60" cy="60" r="{{ $ringRadius }}" fill="none" stroke="#e5e7eb" stroke-width="12"></circle>
                            <circle id="pay-ring-arc" cx="60" cy="60" r="{{ $ringRadius }}" fill="none" stroke="#059669" stroke-width="12" stroke-linecap="round" stroke-dasharray="{{ number_format($ringCirc, 2, '.', '') }}" stroke-dashoffset="{{ number_format($ringOffset, 2, '.', '') }}" transform="rotate(-90 60 60)"></circle>
                        </svg>
                        <div class="absolute inset-0 grid place-items-center text-center">
                            <div>
                                <div id="paid-pct-label" class="stat-num text-3xl font-semibold">{{ $paidPct }}%</div>
                                <div class="mt-1 text-[11px] font-bold uppercase tracking-[0.18em] bk-meta">Paid</div>
                            </div>
                        </div>
                    </div>
                    <div class="bk-payment-breakdown flex-1 space-y-1 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="bk-meta">Total Amount</span>
                            <span class="stat-num font-semibold">@money($totalBilled)</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="bk-meta">Paid Amount</span>
                            <span class="stat-num font-semibold text-emerald-700">@money($totalPaid)</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="bk-meta">Due Amount</span>
                            <span class="stat-num font-semibold text-crimson">@money($totalDue)</span>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="bk-split bk-guest-room">
            <article class="bk-card bk-card-wrap">
                <div class="flex items-start justify-between gap-3">
                    <span class="bk-icon bg-rose-50 text-crimson"><x-icon name="user" size="sm" /></span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Guest Profile</h3>
                        <p class="text-xs bk-meta">Guest identity and stay details</p>
                    </div>
                    @if($booking->isActive())
                        <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-ghost ml-auto">
                            <x-icon name="edit" size="sm" /> Edit
                        </a>
                    @endif
                </div>

                <div class="mt-5 flex items-start gap-3">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-rose-100 text-sm font-semibold text-crimson">{{ $initials ?: 'G' }}</div>
                    <div>
                        <div class="text-lg font-semibold text-slate-900">{{ $booking->customer->name }}</div>
                        <div class="text-sm bk-meta">S/O {{ $booking->customer->father_name ?: '—' }}</div>
                    </div>
                </div>
                <div class="mt-4 grid gap-2 text-sm">
                    <div class="flex items-center gap-2 bk-meta"><x-icon name="phone" size="sm" /> {{ $booking->customer->phone ?: '—' }}</div>
                    <div class="flex items-center gap-2 bk-meta"><x-icon name="id" size="sm" /> {{ $booking->customer->cnic ?: 'No ID card number' }}</div>
                    <div class="flex items-center gap-2 bk-meta"><x-icon name="map" size="sm" /> {{ $booking->customer->address ?: '—' }}</div>
                    <div class="flex items-center gap-2 bk-meta"><x-icon name="car" size="sm" /> {{ $booking->vehicle_number ?: 'No vehicle' }}</div>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-sky-50 p-3">
                        <div class="mx-auto mb-1 grid h-8 w-8 place-items-center rounded-full bg-white text-sky-700"><x-icon name="male" size="sm" /></div>
                        <div class="stat-num text-xl font-semibold">{{ $booking->males }}</div>
                        <div class="mt-1 text-[11px] bk-meta">Male</div>
                    </div>
                    <div class="rounded-2xl bg-rose-50 p-3">
                        <div class="mx-auto mb-1 grid h-8 w-8 place-items-center rounded-full bg-white text-crimson"><x-icon name="female" size="sm" /></div>
                        <div class="stat-num text-xl font-semibold">{{ $booking->females }}</div>
                        <div class="mt-1 text-[11px] bk-meta">Female</div>
                    </div>
                    <div class="rounded-2xl bg-amber-50 p-3">
                        <div class="mx-auto mb-1 grid h-8 w-8 place-items-center rounded-full bg-white text-amber-700"><x-icon name="child" size="sm" /></div>
                        <div class="stat-num text-xl font-semibold">{{ $booking->children }}</div>
                        <div class="mt-1 text-[11px] bk-meta">Children</div>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="text-sm font-semibold text-slate-900">Identity Documents</div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach($guestDocuments as $document)
                            <div class="overflow-hidden rounded-2xl border border-slate-200">
                                <div class="border-b border-slate-100 px-3 py-2 text-xs font-semibold capitalize text-slate-700">{{ $document['label'] }}</div>
                                @if($document['url'])
                                    <a href="{{ $document['url'] }}" target="_blank" class="block transition hover:scale-[1.01] hover:border-rose-200">
                                        <img src="{{ $document['url'] }}" alt="{{ $document['label'] }}" class="h-36 w-full object-cover">
                                    </a>
                                @else
                                    <p class="px-3 py-5 text-xs bk-meta">No {{ $document['label'] }} uploaded.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($companionDocuments->isNotEmpty())
                        <div class="mt-4 text-sm font-semibold text-slate-900">Other guests’ ID documents</div>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            @foreach($companionDocuments as $document)
                                <a href="{{ $document['url'] }}" target="_blank" class="block overflow-hidden rounded-2xl border border-slate-200 transition hover:scale-[1.01] hover:border-rose-200">
                                    <div class="border-b border-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">{{ $document['label'] }}</div>
                                    <img src="{{ $document['url'] }}" alt="{{ $document['label'] }}" class="h-36 w-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if($documentUrls->isNotEmpty())
                        <a href="{{ $documentUrls->first() }}" target="_blank" class="mt-3 inline-flex text-sm font-medium text-crimson">View all documents ({{ $documentUrls->count() }})</a>
                    @endif
                </div>

                @if($booking->notes)
                    <p class="mt-4 text-sm bk-meta">{{ $booking->notes }}</p>
                @endif

                @if($booking->companions->isNotEmpty())
                    <div class="mt-5">
                        <div class="text-sm font-semibold text-slate-900">Other guests</div>
                        <div class="mt-3 grid gap-2">
                            @foreach($booking->companions as $companion)
                                <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm border border-slate-100">
                                    <div class="font-medium text-slate-900">{{ $companion->name }}</div>
                                    <div class="bk-meta">CNIC: {{ $companion->cnic ?: '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>

            <div class="space-y-4">
                <article class="bk-card bk-card-wrap">
                    <div class="flex items-start gap-3">
                        <span class="bk-icon bg-rose-50 text-crimson"><x-icon name="bed" size="sm" /></span>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Room Assignment</h3>
                            <p class="text-xs bk-meta">Current room details and assignment</p>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-4">
                        <div class="grid h-28 w-36 shrink-0 place-items-center overflow-hidden rounded-2xl bg-stone-100 text-crimson">
                            <img src="{{ asset('images/cyberstay-room.jpg') }}" alt="Hotel room" class="booking-room-photo">
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="text-xl font-semibold text-slate-900">Room {{ $booking->room->number }}</div>
                                <span class="bk-badge bk-badge-danger">Current Room</span>
                            </div>
                            <div class="mt-2 space-y-1 text-sm bk-meta">
                                <div>{{ $bedLabel }}</div>
                                <div>{{ $floorLabel }}</div>
                                <div>Capacity: {{ $booking->room->max_capacity }} guests</div>
                            </div>
                        </div>
                    </div>
                    @if($booking->isActive())
                        <form method="POST" action="{{ route('bookings.switch-room', $booking) }}" class="mt-4 grid gap-2">
                            @csrf
                            <select name="room_id" class="input" required>
                                @foreach($switchableRooms as $room)
                                    <option value="{{ $room->id }}" @selected($room->id === $booking->room_id)>
                                        Room {{ $room->number }}{{ $room->id === $booking->room_id ? ' (current)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary w-full" type="submit">
                                <x-icon name="switch" size="sm" /> Change Room
                            </button>
                        </form>
                    @endif
                    @if($booking->roomHistories->isNotEmpty())
                        <div class="mt-4 space-y-2">
                            @foreach($booking->roomHistories as $history)
                                <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs bk-meta border border-slate-100">
                                    Room {{ $history->room?->number ?: '—' }} · {{ $history->started_at->format('d M Y, h:i A') }} → {{ $history->ended_at?->format('d M Y, h:i A') ?: 'Current' }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>

                @if($booking->status === 'checked_in')
                    <article id="checkout-timer" class="bk-card bk-card-wrap" data-cutoff-at="{{ $scheduledCheckOutAt->toIso8601String() }}" data-price-per-night="{{ $booking->price_per_night }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="bk-icon bg-rose-50 text-crimson"><x-icon name="clock" size="sm" /></span>
                                <div>
                                    <h3 class="text-base font-semibold text-slate-900">Checkout Information</h3>
                                    <p class="text-xs bk-meta">Time remaining until checkout deadline</p>
                                </div>
                            </div>
                            <span id="checkout-countdown-value" class="bk-badge bk-badge-danger {{ $lateDays > 0 ? '' : 'hidden' }}">Overdue</span>
                        </div>
                        <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                            <div class="rounded-2xl bg-rose-50 p-3">
                                <div id="cd-days" class="stat-num text-2xl font-semibold text-crimson">0</div>
                                <div class="mt-1 text-[10px] uppercase tracking-[0.18em] bk-meta">Days</div>
                            </div>
                            <div class="rounded-2xl bg-rose-50 p-3">
                                <div id="cd-hours" class="stat-num text-2xl font-semibold text-crimson">0</div>
                                <div class="mt-1 text-[10px] uppercase tracking-[0.18em] bk-meta">Hours</div>
                            </div>
                            <div class="rounded-2xl bg-rose-50 p-3">
                                <div id="cd-mins" class="stat-num text-2xl font-semibold text-crimson">0</div>
                                <div class="mt-1 text-[10px] uppercase tracking-[0.18em] bk-meta">Minutes</div>
                            </div>
                            <div class="rounded-2xl bg-rose-50 p-3">
                                <div id="cd-secs" class="stat-num text-2xl font-semibold text-crimson">0</div>
                                <div class="mt-1 text-[10px] uppercase tracking-[0.18em] bk-meta">Seconds</div>
                            </div>
                        </div>
                        <p class="mt-3 text-sm bk-meta">Cutoff: {{ $scheduledCheckOutAt->format('d M Y, h:i A') }}</p>
                        <div id="late-charge-hint" class="mt-3 {{ $lateDays > 0 ? '' : 'hidden' }} rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900"></div>
                    </article>
                @elseif($booking->status === 'checked_out')
                    <article class="bk-card bk-card-wrap booking-checkout-complete">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="bk-icon bg-emerald-50 text-emerald-700"><x-icon name="check" size="sm" /></span>
                                <div>
                                    <h3 class="text-base font-semibold text-slate-900">Checkout Information</h3>
                                    <p class="text-xs bk-meta">Guest checkout is complete</p>
                                </div>
                            </div>
                            <span class="bk-badge bk-badge-success">Completed</span>
                        </div>
                        <div class="booking-checkout-facts">
                            <div>
                                <span>Checkout date</span>
                                <strong>{{ $booking->checked_out_at?->format('d M Y') ?? $booking->check_out->format('d M Y') }}</strong>
                            </div>
                            <div>
                                <span>Checkout time</span>
                                <strong>{{ $booking->checked_out_at?->format('h:i A') ?? '—' }}</strong>
                            </div>
                            <div>
                                <span>Stay duration</span>
                                <strong>{{ $baseNights }} {{ \Illuminate\Support\Str::plural('night', $baseNights) }}</strong>
                            </div>
                        </div>
                        @if($booking->checkout_note)
                            <p class="booking-checkout-note">{{ $booking->checkout_note }}</p>
                        @endif
                    </article>
                @endif
            </div>
        </section>

        <section class="bk-split bk-rent-extras">
            <article class="bk-card bk-card-wrap">
                <div class="flex items-start gap-3">
                    <span class="bk-icon bg-rose-50 text-crimson"><x-icon name="receipt" size="sm" /></span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Daily Rent</h3>
                        <p class="text-xs bk-meta">Room rent per day. You can pay in cash or online.</p>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="bk-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roomLines as $line)
                                <tr>
                                    <td class="bk-meta">{{ $loop->iteration }}</td>
                                    <td class="font-medium text-slate-900">{{ \Illuminate\Support\Carbon::parse($line['rent_date'])->format('d M Y') }}</td>
                                    <td class="stat-num font-semibold">@money($line['amount'])</td>
                                    <td>
                                        @if($line['remaining'] <= 0)
                                            <span class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-700"><x-icon name="check" size="sm" /> Paid</span>
                                        @else
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center gap-1 text-sm font-semibold text-amber-700">Pending</span>
                                                @if($booking->isActive())
                                                    <form method="POST" action="{{ route('bookings.payments.store', $booking) }}">
                                                        @csrf
                                                        <input type="hidden" name="type" value="room">
                                                        <input type="hidden" name="method" value="cash">
                                                        <input type="hidden" name="rent_date" value="{{ $line['rent_date'] }}">
                                                        <button class="text-xs font-semibold text-crimson">Paid</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('bookings.payments.store', $booking) }}">
                                                        @csrf
                                                        <input type="hidden" name="type" value="room">
                                                        <input type="hidden" name="method" value="online">
                                                        <input type="hidden" name="rent_date" value="{{ $line['rent_date'] }}">
                                                        <button class="text-xs font-semibold bk-meta">Online</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="bk-meta">{{ $paymentMethodLabel($line['paid_method'] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="bk-card bk-card-wrap">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="bk-icon bg-rose-50 text-crimson"><x-icon name="cart" size="sm" /></span>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Extra Charges</h3>
                            <p class="text-xs bk-meta">Additional services and charges</p>
                        </div>
                    </div>
                    @if($booking->isActive())
                        <a href="{{ route('pos.index', ['booking_id' => $booking->id]) }}" class="btn btn-ghost">
                            <x-icon name="plus" size="sm" /> Add Charge
                        </a>
                    @endif
                </div>
                <div class="mt-4 grid gap-3">
                    @forelse($extraLines as $line)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $line['label'] }}</div>
                                    <div class="text-sm bk-meta">{{ $line['detail'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="stat-num font-semibold">@money($line['amount'])</div>
                                    @if($line['remaining'] <= 0)
                                        <div class="text-sm font-semibold text-emerald-700">{{ ($line['paid_method'] ?? '') === 'online' ? 'Paid online' : 'Paid' }}</div>
                                    @else
                                        <div class="text-xs bk-meta">Due @money($line['remaining'])</div>
                                    @endif
                                </div>
                            </div>
                            @if($line['remaining'] > 0 && $booking->isActive())
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('bookings.payments.store', $booking) }}">
                                        @csrf
                                        <input type="hidden" name="type" value="extras">
                                        <input type="hidden" name="method" value="cash">
                                        <input type="hidden" name="booking_item_id" value="{{ $line['booking_item_id'] }}">
                                        <button class="btn btn-primary">Paid</button>
                                    </form>
                                    <form method="POST" action="{{ route('bookings.payments.store', $booking) }}">
                                        @csrf
                                        <input type="hidden" name="type" value="extras">
                                        <input type="hidden" name="method" value="online">
                                        <input type="hidden" name="booking_item_id" value="{{ $line['booking_item_id'] }}">
                                        <button class="btn btn-ghost">Paid online</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="grid min-h-40 place-items-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-center">
                            <div>
                                <div class="mx-auto mb-2 grid h-10 w-10 place-items-center rounded-full bg-white text-slate-500"><x-icon name="cart" /></div>
                                <div class="font-medium text-slate-900">No extra charges</div>
                                <p class="mt-1 text-sm bk-meta">No extra charges added for this booking.</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="bk-card bk-summary overflow-hidden">
            <div class="grid lg:grid-cols-[minmax(0,1fr)_18.5rem]">
                <div class="bk-card-wrap">
                    <div class="flex items-start gap-3">
                        <span class="bk-icon bg-rose-50 text-crimson"><x-icon name="receipt" size="sm" /></span>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Bill Summary</h3>
                            <p class="text-xs bk-meta">Complete breakdown of charges</p>
                        </div>
                    </div>
                    <div class="summary-rows mt-5 space-y-3 text-sm">
                        <div class="flex justify-between"><span class="bk-meta">Room Rent (per night)</span><span class="stat-num">@money($booking->price_per_night)</span></div>
                        <div class="flex justify-between"><span class="bk-meta">Room Rent ({{ $baseNights }} {{ \Illuminate\Support\Str::plural('night', $baseNights) }})</span><span class="stat-num">@money($baseRoomAmount)</span></div>
                        <div class="flex justify-between"><span class="bk-meta">Extra Charges</span><span class="stat-num">@money($extrasAmount)</span></div>
                        <div class="flex justify-between"><span class="bk-meta">Paid Amount</span><span class="stat-num text-crimson">- @money($paidAmount)</span></div>
                        <div class="summary-total flex justify-between text-base font-semibold"><span>Total Amount</span><span class="stat-num">@money($totalBilled)</span></div>
                    </div>
                    @if($booking->checkout_note)
                        <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm bk-meta border border-slate-200">
                            <div class="text-[11px] font-bold uppercase tracking-[0.18em]">Checkout note</div>
                            <p class="mt-1">{{ $booking->checkout_note }}</p>
                        </div>
                    @endif
                </div>
                <div class="bk-summary-due flex flex-col justify-between">
                    <div class="bk-summary-due-content">
                        <div class="text-sm bk-meta">Amount Due</div>
                        <div class="stat-num mt-2 text-4xl font-semibold text-crimson">@money($totalDue)</div>
                        <p class="mt-2 text-xs bk-meta">Remaining balance to be collected</p>
                    </div>
                    @if($booking->status === 'checked_in')
                        <button type="button" class="btn btn-primary mt-6 w-full" data-modal-open="checkout-modal">
                            <x-icon name="logout" size="sm" /> Check Out Guest
                        </button>
                    @elseif($booking->status === 'reserved')
                        <form method="POST" action="{{ route('bookings.check-in', $booking) }}" class="mt-6">@csrf<button class="btn btn-primary w-full"><x-icon name="door" size="sm" /> Check In Guest</button></form>
                    @elseif($booking->status === 'checked_out')
                        <button type="button" class="btn btn-primary mt-6 w-full" disabled aria-disabled="true">
                            <x-icon name="check" size="sm" /> Checked Out
                        </button>
                    @endif
                </div>
            </div>
        </section>
    </div>

    <div id="checkout-modal" class="modal-overlay" data-open-on-load="{{ ($errors->has('checkout_note') || $errors->has('room_paid_now') || $errors->has('extras_paid_now') || $errors->has('payment_method')) ? '1' : '0' }}" role="dialog" aria-modal="true" aria-labelledby="checkout-modal-title">
        <div class="modal-panel p-5 sm:p-6">
            <div class="mb-5 flex items-start justify-between gap-3">
                <x-section-title icon="logout" hint="Checkout shows already paid amounts and remaining balance.">
                    <span id="checkout-modal-title">Checkout guest</span>
                </x-section-title>
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl hover:bg-stone-100" data-modal-close aria-label="Close">
                    <x-icon name="x" />
                </button>
            </div>

            <form method="POST" action="{{ route('bookings.check-out', $booking) }}" class="space-y-4">
                @csrf
                <div id="late-checkout-box" class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 {{ $lateDays > 0 ? '' : 'hidden' }}">
                    <div class="font-semibold">Late checkout detected</div>
                    <div class="mt-1">Checkout cutoff was {{ $scheduledCheckOutAt->format('d M Y, h:i A') }}.</div>
                    <div class="mt-1">Extra days: <span id="late-days-value">{{ $lateDays }}</span> · Extra charge: <span id="late-charge-value">@money($lateCharge)</span></div>
                    <div class="mt-1 text-xs">If not waived, room/total will be increased automatically at checkout.</div>
                    <label class="mt-3 inline-flex items-center gap-2">
                        <input type="checkbox" name="waive_late_checkout_charge" value="1" @checked(old('waive_late_checkout_charge'))>
                        <span>Do not charge late checkout for this booking (admin override)</span>
                    </label>
                </div>

                <div class="rounded-2xl bg-slate-50 p-4 text-sm border border-slate-200">
                    <div class="flex justify-between"><span class="text-muted">Total bill</span><span class="stat-num">@money($booking->grand_total)</span></div>
                    <div class="mt-2 flex justify-between"><span class="text-muted">Rent paid</span><span class="stat-num">@money($booking->room_paid_amount)</span></div>
                    <div class="mt-2 flex justify-between"><span class="text-muted">Other charges paid</span><span class="stat-num">@money($booking->extras_paid_amount)</span></div>
                    <div class="mt-2 flex justify-between"><span class="text-muted">Already paid</span><span class="stat-num">@money($booking->amountPaid())</span></div>
                    <div class="mt-2 flex justify-between border-t border-slate-200 pt-2"><span class="text-muted">Remaining</span><span class="stat-num">@money($booking->balance_due)</span></div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label"><x-icon name="money" size="sm" /> Rent paying now</label>
                        <input class="input" type="number" min="0" step="0.01" name="room_paid_now" value="{{ old('room_paid_now', $booking->roomBalance()) }}" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label"><x-icon name="money" size="sm" /> Other charges paying now</label>
                        <input class="input" type="number" min="0" step="0.01" name="extras_paid_now" value="{{ old('extras_paid_now', $booking->extrasBalance()) }}" placeholder="0">
                    </div>
                </div>
                <div>
                    <label class="field-label"><x-icon name="wallet" size="sm" /> Payment method</label>
                    <select class="input" name="payment_method">
                        <option value="">Select if paying now</option>
                        <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                        <option value="online" @selected(old('payment_method') === 'online')>Online</option>
                    </select>
                </div>
                <div>
                    <label class="field-label"><x-icon name="edit" size="sm" /> Checkout note</label>
                    <textarea class="input" name="checkout_note" rows="4" required placeholder="Condition of room, settlement details, any pending follow-up…">{{ old('checkout_note') }}</textarea>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" class="btn btn-ghost flex-1" data-modal-close>Cancel</button>
                    <button class="btn btn-primary flex-1"><x-icon name="check" size="sm" /> Complete checkout</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const timer = document.getElementById('checkout-timer');
            if (!timer) {
                return;
            }

            const countdownValue = document.getElementById('checkout-countdown-value');
            const lateHint = document.getElementById('late-charge-hint');
            const lateBox = document.getElementById('late-checkout-box');
            const lateDaysValue = document.getElementById('late-days-value');
            const lateChargeValue = document.getElementById('late-charge-value');
            const cdDays = document.getElementById('cd-days');
            const cdHours = document.getElementById('cd-hours');
            const cdMins = document.getElementById('cd-mins');
            const cdSecs = document.getElementById('cd-secs');
            const kpiDays = document.getElementById('kpi-cd-days');
            const kpiHms = document.getElementById('kpi-cd-hms');
            const kpiCard = document.getElementById('kpi-checkout-card');
            const kpiOverdue = document.getElementById('kpi-overdue-badge');
            const cutoffAt = new Date(timer.dataset.cutoffAt).getTime();
            const pricePerNight = Number(timer.dataset.pricePerNight || 0);
            const pad = (value) => String(value).padStart(2, '0');
            const formatMoney = (value) => 'Rs ' + Math.round(value).toLocaleString();
            const splitDuration = (seconds) => {
                const days = Math.floor(seconds / 86400);
                const hours = Math.floor((seconds % 86400) / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = Math.floor(seconds % 60);
                return { days, hours, minutes, secs };
            };
            const setOverdue = (isOverdue) => {
                if (kpiCard) kpiCard.classList.toggle('is-overdue', isOverdue);
                if (kpiOverdue) kpiOverdue.classList.toggle('hidden', !isOverdue);
                if (kpiHms) {
                    kpiHms.classList.toggle('text-crimson', isOverdue);
                    kpiHms.classList.toggle('bk-meta', !isOverdue);
                }
            };
            const renderClock = (parts) => {
                if (cdDays) cdDays.textContent = String(parts.days);
                if (cdHours) cdHours.textContent = String(parts.hours);
                if (cdMins) cdMins.textContent = String(parts.minutes);
                if (cdSecs) cdSecs.textContent = String(parts.secs);
                if (kpiDays) kpiDays.textContent = parts.days + (parts.days === 1 ? ' day' : ' days');
                if (kpiHms) kpiHms.textContent = pad(parts.hours) + 'h ' + pad(parts.minutes) + 'm ' + pad(parts.secs) + 's';
            };

            const tick = () => {
                const now = Date.now();
                const diffMs = cutoffAt - now;

                if (diffMs >= 0) {
                    renderClock(splitDuration(Math.floor(diffMs / 1000)));
                    if (countdownValue) countdownValue.classList.add('hidden');
                    if (lateHint) {
                        lateHint.classList.add('hidden');
                        lateHint.textContent = '';
                    }
                    if (lateBox) lateBox.classList.add('hidden');
                    setOverdue(false);
                    return;
                }

                const lateSeconds = Math.floor((now - cutoffAt) / 1000);
                const lateDays = Math.ceil(lateSeconds / 86400);
                const lateCharge = lateDays * pricePerNight;
                renderClock(splitDuration(lateSeconds));
                if (countdownValue) countdownValue.classList.remove('hidden');
                setOverdue(true);
                if (lateHint) {
                    lateHint.classList.remove('hidden');
                    lateHint.textContent = 'Stay extends by ' + lateDays + ' day' + (lateDays > 1 ? 's' : '') + '. Extra rent ' + formatMoney(lateCharge) + '. Admin can waive this at checkout.';
                }
                if (lateBox) {
                    lateBox.classList.remove('hidden');
                    if (lateDaysValue) lateDaysValue.textContent = String(lateDays);
                    if (lateChargeValue) lateChargeValue.textContent = formatMoney(lateCharge);
                }
            };

            tick();
            setInterval(tick, 1000);
        });
    </script>
@endsection
