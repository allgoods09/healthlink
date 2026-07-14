<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $certificate->certificate_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.6;
            margin: 40px;
        }
        .text-center { text-align: center; }
        .uppercase { text-transform: uppercase; }
        .muted { color: #475569; }
        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 28px 0 8px;
            letter-spacing: 0.08em;
        }
        .certificate-no {
            margin-bottom: 24px;
            font-size: 11px;
            color: #334155;
        }
        .body-copy {
            margin-top: 28px;
            font-size: 13px;
            text-align: justify;
        }
        .signature {
            margin-top: 64px;
            width: 280px;
            margin-left: auto;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #0f172a;
            margin-bottom: 8px;
        }
        .meta {
            margin-top: 32px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="text-center">
        <div class="uppercase muted">Republic of the Philippines</div>
        <div class="uppercase muted">Province of {{ $certificate->barangay?->province ?? 'Bohol' }}</div>
        <div class="uppercase muted">Municipality of {{ $certificate->barangay?->municipality ?? 'Tubigon' }}</div>
        <div class="uppercase">{{ $certificate->barangay?->name }}</div>

        <div class="title uppercase">{{ $certificate->certificate_type_label }}</div>
        <div class="certificate-no">Certificate No. {{ $certificate->certificate_no }}</div>
    </div>

    <p class="body-copy">
        To whom it may concern:
    </p>

    <p class="body-copy">
        This is to certify that <strong>{{ $certificate->issued_to_name }}</strong>
        is a recorded {{ strtolower($certificate->recipient_type_label) }}
        of Barangay {{ $certificate->barangay?->name ?? 'N/A' }}, Municipality of
        {{ $certificate->barangay?->municipality ?? 'Tubigon' }}, Province of
        {{ $certificate->barangay?->province ?? 'Bohol' }}.
        This certification is being issued upon request for the following purpose:
        <strong>{{ $certificate->purpose }}</strong>.
    </p>

    @if($certificate->remarks)
        <p class="body-copy">
            Additional note: {{ $certificate->remarks }}
        </p>
    @endif

    <p class="body-copy">
        Issued this {{ $certificate->issued_at?->format('jS') }} day of {{ $certificate->issued_at?->format('F Y') }}
        at Barangay {{ $certificate->barangay?->name ?? 'N/A' }}, for whatever lawful purpose it may serve best.
    </p>

    <div class="signature">
        <div class="signature-line"></div>
        <div><strong>{{ $certificate->issuedBy?->name ?? 'Barangay Secretary' }}</strong></div>
        <div class="muted">Barangay Secretary</div>
    </div>

    <div class="meta">
        <div>Issued at: {{ $certificate->issued_at?->format('F d, Y h:i A') }}</div>
        <div>Generated from HealthLink barangay certificate log.</div>
    </div>
</body>
</html>
