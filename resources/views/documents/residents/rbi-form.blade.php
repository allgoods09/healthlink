<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RBI Form - {{ $resident->formal_name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            line-height: 1.35;
            background: #ffffff;
        }

        .page {
            border: 2px solid #374151;
            padding: 14px 16px 16px;
        }

        .title-wrap {
            text-align: center;
            margin-bottom: 10px;
        }

        .form-code {
            text-align: right;
            font-size: 10px;
            margin-bottom: 2px;
        }

        .title {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .meta-table,
        .field-table,
        .signature-table,
        .bottom-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td,
        .field-table td,
        .signature-table td,
        .bottom-table td {
            vertical-align: top;
            padding: 4px 5px;
        }

        .meta-label,
        .field-label,
        .section-title,
        .sub-label,
        .thumb-label,
        .foot-note {
            color: #111827;
        }

        .meta-label,
        .field-label,
        .sub-label,
        .thumb-label {
            font-size: 9px;
        }

        .meta-value {
            border-bottom: 1px solid #374151;
            min-height: 15px;
            padding: 2px 2px 1px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .section-title {
            margin: 12px 0 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .field-box {
            min-height: 20px;
            border: 1px solid #374151;
            padding: 4px 6px;
            box-sizing: border-box;
            word-wrap: break-word;
        }

        .field-box.tall {
            min-height: 28px;
        }

        .field-box.centered {
            text-align: center;
        }

        .checkbox-row {
            margin: 2px 0 0;
        }

        .checkbox-group {
            display: inline-block;
            margin: 0 12px 6px 0;
            white-space: nowrap;
        }

        .checkbox {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1px solid #374151;
            text-align: center;
            line-height: 11px;
            font-size: 9px;
            margin-right: 4px;
            vertical-align: middle;
        }

        .certification-copy {
            margin: 10px 0 14px;
            text-align: justify;
            font-size: 10px;
        }

        .line-box {
            border-bottom: 1px solid #374151;
            min-height: 18px;
            padding: 2px 2px 1px;
        }

        .attested-name {
            margin-top: 18px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .role-label {
            font-size: 10px;
        }

        .thumb-box {
            height: 92px;
            border: 1px solid #374151;
        }

        .mini-note {
            margin-top: 6px;
            font-size: 9px;
            font-style: italic;
        }

        .footer-meta {
            margin-top: 10px;
            font-size: 8.5px;
            color: #374151;
            text-align: right;
        }

        @media print {
            body {
                margin: 0;
                background: #ffffff;
            }

            .page {
                border-color: #111827;
            }
        }
    </style>
</head>
@php
    $barangay = $resident->household?->purok?->barangay;
    $household = $resident->household;
    $profile = $resident->socioEconomicProfile;
    $educationLevel = $profile?->highest_education_level;
    $educationStatus = $profile?->education_status;

    $educationSelections = [
        'Elementary' => $educationLevel === 'Elementary',
        'High School' => $educationLevel === 'High School',
        'College' => $educationLevel === 'College',
        'Post Grad' => $educationLevel === 'Post Grad',
        'Vocational' => $educationLevel === 'Vocational',
    ];

    $educationStatusSelections = [
        'Graduate' => $educationStatus === 'Graduate',
        'Under Graduate' => $educationStatus === 'Undergraduate',
    ];
@endphp
<body>
    @if(!empty($showBrowserPrintScript))
        <script>
            window.addEventListener('load', () => {
                window.print();
            });
        </script>
    @endif

    <div class="page">
        <div class="form-code">RBI Form B (Revised 2024)</div>
        <div class="title-wrap">
            <div class="title">Individual Records of Barangay Inhabitant</div>
        </div>

        <table class="meta-table">
            <tr>
                <td width="25%">
                    <div class="meta-label">Region</div>
                    <div class="meta-value">{{ $barangay?->region ?? 'VII' }}</div>
                </td>
                <td width="25%">
                    <div class="meta-label">Province</div>
                    <div class="meta-value">{{ $barangay?->province ?? 'Bohol' }}</div>
                </td>
                <td width="25%">
                    <div class="meta-label">City/Municipality</div>
                    <div class="meta-value">{{ $barangay?->municipality ?? 'Tubigon' }}</div>
                </td>
                <td width="25%">
                    <div class="meta-label">Barangay</div>
                    <div class="meta-value">{{ $barangay?->name ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>

        <div class="section-title">Personal Information</div>

        <table class="field-table">
            <tr>
                <td colspan="4">
                    <div class="field-label">PhilSys Card No.</div>
                    <div class="field-box">{{ $resident->philsys_card_no ?: 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td width="30%">
                    <div class="field-label">Last Name</div>
                    <div class="field-box">{{ $resident->last_name }}</div>
                </td>
                <td width="15%">
                    <div class="field-label">Suffix</div>
                    <div class="field-box">{{ $resident->suffix ?: 'N/A' }}</div>
                </td>
                <td width="27.5%">
                    <div class="field-label">First Name</div>
                    <div class="field-box">{{ $resident->first_name }}</div>
                </td>
                <td width="27.5%">
                    <div class="field-label">Middle Name</div>
                    <div class="field-box">{{ $resident->middle_name ?: 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td width="18%">
                    <div class="field-label">Birth Date (mm/dd/yyyy)</div>
                    <div class="field-box centered">{{ $resident->birth_date?->format('m/d/Y') ?? 'N/A' }}</div>
                </td>
                <td width="34%" colspan="2">
                    <div class="field-label">Birth Place</div>
                    <div class="field-box">{{ $resident->birth_place ?: 'N/A' }}</div>
                </td>
                <td width="12%">
                    <div class="field-label">Sex</div>
                    <div class="field-box centered">{{ $resident->sex ?: 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="field-label">Civil Status</div>
                    <div class="field-box">{{ $resident->civil_status ?: 'N/A' }}</div>
                </td>
                <td>
                    <div class="field-label">Religion</div>
                    <div class="field-box">{{ $resident->religion ?: 'N/A' }}</div>
                </td>
                <td>
                    <div class="field-label">Citizenship</div>
                    <div class="field-box">{{ $resident->citizenship ?: 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div class="field-label">Residence Address</div>
                    <div class="field-box tall">{{ $household?->household_address ?: 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="field-label">Profession / Occupation</div>
                    <div class="field-box">{{ $profile?->occupation ?: 'N/A' }}</div>
                </td>
                <td>
                    <div class="field-label">Contact Number</div>
                    <div class="field-box">{{ $resident->contact_number ?: 'N/A' }}</div>
                </td>
                <td>
                    <div class="field-label">Email Address</div>
                    <div class="field-box">{{ $resident->email_address ?: 'N/A' }}</div>
                </td>
            </tr>
        </table>

        <div class="section-title">Highest Educational Attainment</div>
        <div class="checkbox-row">
            @foreach($educationSelections as $label => $selected)
                <span class="checkbox-group">
                    <span class="checkbox">{{ $selected ? '✓' : '' }}</span>{{ $label }}
                </span>
            @endforeach
        </div>
        <div class="checkbox-row">
            @foreach($educationStatusSelections as $label => $selected)
                <span class="checkbox-group">
                    <span class="checkbox">{{ $selected ? '✓' : '' }}</span>{{ $label }}
                </span>
            @endforeach
        </div>

        <p class="certification-copy">
            I hereby certify that the above information is true and correct to the best of my knowledge.
            I understand that for the Barangay to carry out its mandate pursuant to Section 394(d)(6)
            of the Local Government Code of 1991, it needs to keep my personal information in its
            records for planning, documentation, and as an updated reference of the number of inhabitants
            of the Barangay. Therefore, I grant my consent and recognize the authority of the Barangay
            to process my personal information, subject to the provisions of the Philippine Data Privacy
            Act of 2012.
        </p>

        <table class="signature-table">
            <tr>
                <td width="48%">
                    <div class="field-label">Attested By</div>
                    <div class="line-box"></div>
                    <div class="attested-name">{{ $attestedByName ?: '__________________________' }}</div>
                    <div class="role-label">{{ $attestedByRole ?? 'Barangay Secretary' }}</div>
                </td>
                <td width="4%"></td>
                <td width="48%">
                    <div class="field-label">Date Accomplished</div>
                    <div class="line-box">{{ $documentDate?->format('m/d/Y') ?? now()->format('m/d/Y') }}</div>
                    <div class="field-label" style="margin-top: 16px;">Name/Signature of Person Accomplishing the Form</div>
                    <div class="line-box">{{ $accomplishingPartyName ?: '__________________________' }}</div>
                </td>
            </tr>
        </table>

        <table class="bottom-table" style="margin-top: 12px;">
            <tr>
                <td width="34%">
                    <div class="field-label">Household Number</div>
                    <div class="field-box centered">{{ $household?->household_no ?: 'N/A' }}</div>
                    <div class="mini-note">Note: The household number shall be filled up by the Barangay Secretary.</div>
                </td>
                <td width="8%"></td>
                <td width="24%">
                    <div class="thumb-label">Left Thumbmark</div>
                    <div class="thumb-box"></div>
                </td>
                <td width="4%"></td>
                <td width="24%">
                    <div class="thumb-label">Right Thumbmark</div>
                    <div class="thumb-box"></div>
                </td>
            </tr>
        </table>

        <div class="footer-meta">
            Resident Code: {{ $resident->official_resident_code ?: 'N/A' }} |
            Generated: {{ ($documentDate ?? now())->format('F d, Y h:i A') }}
        </div>
    </div>
</body>
</html>
