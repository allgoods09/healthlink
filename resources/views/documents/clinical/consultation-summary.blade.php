<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinical Consultation Summary - {{ $clinicalEncounter->resident?->formal_name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
        }

        .page {
            border: 1.5px solid #1e3a8a;
            padding: 18px 20px 20px;
        }

        .heading {
            margin-bottom: 16px;
            text-align: center;
        }

        .heading h1 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .heading p {
            margin: 4px 0 0;
            color: #475569;
            font-size: 10px;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .grid td {
            padding: 4px 6px;
            vertical-align: top;
        }

        .label {
            color: #475569;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .value {
            min-height: 16px;
            border-bottom: 1px solid #94a3b8;
            padding-top: 4px;
            font-weight: 600;
        }

        .section {
            margin-top: 16px;
        }

        .section-title {
            margin: 0 0 8px;
            color: #1e3a8a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .box {
            min-height: 52px;
            border: 1px solid #94a3b8;
            padding: 8px 10px;
            box-sizing: border-box;
        }

        .footer {
            margin-top: 18px;
            font-size: 9px;
            color: #475569;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="heading">
            <h1>Clinical Consultation Summary</h1>
            <p>Municipal Rural Health Unit · Tubigon, Bohol</p>
        </div>

        <table class="grid">
            <tr>
                <td width="33%">
                    <div class="label">Resident</div>
                    <div class="value">{{ $clinicalEncounter->resident?->formal_name ?? 'Unknown resident' }}</div>
                </td>
                <td width="33%">
                    <div class="label">Resident Code</div>
                    <div class="value">{{ $clinicalEncounter->resident?->official_resident_code ?? 'N/A' }}</div>
                </td>
                <td width="34%">
                    <div class="label">Encountered At</div>
                    <div class="value">{{ $clinicalEncounter->encountered_at?->format('F j, Y h:i A') ?? 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Barangay</div>
                    <div class="value">{{ $clinicalEncounter->resident?->household?->purok?->barangay?->name ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Purok</div>
                    <div class="value">{{ $clinicalEncounter->resident?->household?->purok?->display_name ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Encounter Source</div>
                    <div class="value">{{ $clinicalEncounter->encounter_source_label }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">PHN Reviewer</div>
                    <div class="value">{{ $clinicalEncounter->attendedBy?->name ?? 'Unknown PHN' }}</div>
                </td>
                <td>
                    <div class="label">Disposition</div>
                    <div class="value">{{ $clinicalEncounter->mhoReview?->final_disposition ?: ($clinicalEncounter->disposition ?: 'N/A') }}</div>
                </td>
                <td>
                    <div class="label">Follow-Up Status</div>
                    <div class="value">{{ $clinicalEncounter->follow_up_status_label }}</div>
                </td>
            </tr>
        </table>

        @if($clinicalEncounter->triageRecord)
            <div class="section">
                <p class="section-title">Linked BHW Triage</p>
                <table class="grid">
                    <tr>
                        <td width="25%">
                            <div class="label">Measured At</div>
                            <div class="value">{{ $clinicalEncounter->triageRecord?->measured_at?->format('F j, Y h:i A') ?? 'N/A' }}</div>
                        </td>
                        <td width="25%">
                            <div class="label">Blood Pressure</div>
                            <div class="value">
                                {{ $clinicalEncounter->triageRecord?->bp_systolic && $clinicalEncounter->triageRecord?->bp_diastolic ? "{$clinicalEncounter->triageRecord->bp_systolic}/{$clinicalEncounter->triageRecord->bp_diastolic}" : 'N/A' }}
                            </div>
                        </td>
                        <td width="25%">
                            <div class="label">Temperature</div>
                            <div class="value">{{ $clinicalEncounter->triageRecord?->temperature_celsius ? "{$clinicalEncounter->triageRecord->temperature_celsius} C" : 'N/A' }}</div>
                        </td>
                        <td width="25%">
                            <div class="label">Heart Rate</div>
                            <div class="value">{{ $clinicalEncounter->triageRecord?->heart_rate ?: 'N/A' }}</div>
                        </td>
                    </tr>
                </table>
                <div class="box">{{ $clinicalEncounter->triageRecord?->triage_notes ?: 'No BHW note recorded.' }}</div>
            </div>
        @endif

        <div class="section">
            <p class="section-title">Clinical Notes</p>
            <div class="box">{{ $clinicalEncounter->consultation_notes ?: 'No consultation note recorded.' }}</div>
        </div>

        <div class="section">
            <p class="section-title">Working Impression and Action Taken</p>
            <table class="grid">
                <tr>
                    <td width="50%">
                        <div class="box">{{ $clinicalEncounter->working_impression ?: 'No assessment recorded.' }}</div>
                    </td>
                    <td width="50%">
                        <div class="box">{{ $clinicalEncounter->action_taken ?: 'No action recorded.' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <p class="section-title">Treatment and Orders</p>
            <table class="grid">
                <tr>
                    <td width="50%">
                        <div class="label">Medicines Administered</div>
                        <div class="box">{{ $clinicalEncounter->medicines_administered ?: 'No medicine recorded.' }}</div>
                    </td>
                    <td width="50%">
                        <div class="label">Lifestyle Advice</div>
                        <div class="box">{{ $clinicalEncounter->lifestyle_advice ?: 'No lifestyle advice recorded.' }}</div>
                    </td>
                </tr>
                <tr>
                    <td width="50%">
                        <div class="label">Referral Notes</div>
                        <div class="box">{{ $clinicalEncounter->referral_notes ?: 'No referral note recorded.' }}</div>
                    </td>
                    <td width="50%">
                        <div class="label">Return Instructions</div>
                        <div class="box">{{ $clinicalEncounter->return_instructions ?: 'No return instruction recorded.' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <p class="section-title">Follow-Up and Escalation</p>
            <table class="grid">
                <tr>
                    <td width="33%">
                        <div class="label">Follow-Up Date</div>
                        <div class="value">{{ $clinicalEncounter->follow_up_date?->format('F j, Y') ?? 'No follow-up scheduled' }}</div>
                    </td>
                    <td width="33%">
                        <div class="label">Escalated to MHO</div>
                        <div class="value">{{ $clinicalEncounter->is_escalated_to_mho ? 'Yes' : 'No' }}</div>
                    </td>
                    <td width="34%">
                        <div class="label">Printed At</div>
                        <div class="value">{{ $printedAt->format('F j, Y h:i A') }}</div>
                    </td>
                </tr>
            </table>
            <div class="box">{{ $clinicalEncounter->follow_up_notes ?: ($clinicalEncounter->escalation_notes ?: 'No follow-up or escalation note recorded.') }}</div>
        </div>

        @if($clinicalEncounter->mhoReview)
            <div class="section">
                <p class="section-title">Municipal Health Officer Review</p>
                <table class="grid">
                    <tr>
                        <td width="33%">
                            <div class="label">Reviewed By</div>
                            <div class="value">{{ $clinicalEncounter->mhoReview?->reviewedBy?->name ?? 'Unknown MHO' }}</div>
                        </td>
                        <td width="33%">
                            <div class="label">Reviewed At</div>
                            <div class="value">{{ $clinicalEncounter->mhoReview?->reviewed_at?->format('F j, Y h:i A') ?? 'N/A' }}</div>
                        </td>
                        <td width="34%">
                            <div class="label">Referral Destination</div>
                            <div class="value">{{ $clinicalEncounter->mhoReview?->referral_destination ?: 'No referral destination recorded.' }}</div>
                        </td>
                    </tr>
                </table>
                <table class="grid">
                    <tr>
                        <td width="50%">
                            <div class="label">Final Assessment</div>
                            <div class="box">{{ $clinicalEncounter->mhoReview?->final_assessment ?: 'No final assessment recorded.' }}</div>
                        </td>
                        <td width="50%">
                            <div class="label">Diagnostic Override</div>
                            <div class="box">{{ $clinicalEncounter->mhoReview?->diagnostic_override ?: 'No diagnostic override recorded.' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%">
                            <div class="label">Prescription Notes</div>
                            <div class="box">{{ $clinicalEncounter->mhoReview?->prescription_notes ?: 'No prescription note recorded.' }}</div>
                        </td>
                        <td width="50%">
                            <div class="label">Return Instructions</div>
                            <div class="box">{{ $clinicalEncounter->mhoReview?->return_instructions ?: 'No return instruction recorded.' }}</div>
                        </td>
                    </tr>
                </table>
                <div class="box">{{ $clinicalEncounter->mhoReview?->resolution_notes ?: 'No municipal resolution note recorded.' }}</div>
            </div>
        @endif

        <div class="footer">
            Generated by HealthLink clinical module.
        </div>
    </div>
</body>
</html>
