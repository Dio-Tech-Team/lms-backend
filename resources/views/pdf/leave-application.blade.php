<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Application for Leave</title>
    <style>
        /* Philippine "Long" bond paper = 8.5in x 13in (NOT US Legal, which is 8.5x14) */
        @page {
            size: 8.5in 13in;
            margin: 0.45in 0.55in;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.3;
        }

        .form-id {
            font-size: 9px;
            font-weight: bold;
            font-style: italic;
            line-height: 1.2;
        }

        table.header {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.header td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .header-lines {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            line-height: 1.25;
        }

        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 17px;
            margin: 8px 0 8px;
        }

        table.main {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        table.main td {
            border: 1px solid #000;
            padding: 7px 8px;
            vertical-align: top;
        }

        .section-title {
            font-weight: bold;
            text-align: center;
            font-size: 12.5px;
        }

        .checkbox {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid #000;
            margin-right: 4px;
            text-align: center;
            line-height: 10px;
            font-size: 9px;
            vertical-align: middle;
        }

        .checked {
            background-color: #000;
            color: #fff;
        }

        .leave-item {
            margin-bottom: 3px;
        }

        .cite {
            font-size: 7.5px;
        }

        .small-text {
            font-size: 9px;
        }

        .center {
            text-align: center;
        }

        /* Value written on a blank, e.g. "__1__ days with pay" */
        .fill {
            display: inline-block;
            min-width: 55px;
            border-bottom: 1px solid #000;
            text-align: center;
        }

        .blank-line {
            border-bottom: 1px solid #000;
            height: 17px;
        }

        /* Signature block: name on the line, label below */
        .sig-line {
            border-bottom: 1px solid #000;
            margin: 18px 25px 0;
            text-align: center;
            font-weight: bold;
            min-height: 15px;
        }

        .sig-label {
            text-align: center;
            font-size: 10px;
        }

        table.credits {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.credits td {
            border: 1px solid #000;
            padding: 4px 6px;
        }

        table.credits td.label {
            font-style: italic;
            font-weight: bold;
            width: 38%;
        }

        table.credits td.num {
            text-align: center;
        }

        /* ============ Back page (Instructions) ============ */
        .back-page {
            page-break-before: always;
        }

        .instructions-title {
            font-weight: bold;
            text-align: center;
            font-size: 13px;
            padding: 4px;
            margin-bottom: 8px;
        }

        .instructions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .instructions-table td {
            vertical-align: top;
            padding: 6px 10px;
            font-size: 9px;
            line-height: 1.4;
        }

        .instructions-table td.col {
            width: 50%;
        }

        .instructions-table td.footnote {
            font-size: 8px;
            line-height: 1.35;
            padding-top: 10px;
        }

        .instr-item {
            margin-bottom: 9px;
        }

        .instr-item strong {
            font-size: 9.5px;
        }
    </style>
</head>

<body>

    @php
        // Forced Leave has no credit of its own — its days are drawn from Vacation Leave,
        // so the deduction is certified in the Vacation Leave column of 7.A.
        $deductsFromVl = in_array($code, ['VL', 'FL'], true);
        $deductsFromSl = $code === 'SL';

        // Order, labels and legal bases exactly as printed on CS Form No. 6, Revised 2020
        $leaveTypes = [
            ['VL', 'Vacation Leave', 'Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
            ['FL', 'Mandatory/Forced Leave', 'Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
            ['SL', 'Sick Leave', 'Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
            ['ML', 'Maternity Leave', 'R.A. No. 11210 / IRR issued by CSC, DOLE and SSS'],
            ['PTL', 'Paternity Leave', 'R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended'],
            ['SPL', 'Special Privilege Leave', 'Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
            ['SOL', 'Solo Parent Leave', 'RA No. 8972 / CSC MC No. 8, s. 2004'],
            ['STL', 'Study Leave', 'Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
            ['VAWC', '10-Day VAWC Leave', 'RA No. 9262 / CSC MC No. 15, s. 2005'],
            ['RHL', 'Rehabilitation Privilege', 'Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
            ['SLB', 'Special Leave Benefits for Women', 'RA No. 9710 / CSC MC No. 25, s. 2010'],
            ['CAL', 'Special Emergency (Calamity) Leave', 'CSC MC No. 2, s. 2012, as amended'],
            ['ADL', 'Adoption Leave', 'R.A. No. 8552'],
        ];
        $listedCodes = array_column($leaveTypes, 0);

        // 1.000 -> "1", 2.500 -> "2.5"
        $trim = fn($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        // Credits always print with 3 decimals, like the rest of 7.A
        $three = fn($v) => number_format((float) $v, 3, '.', '');

        // Days without pay are only known once the application has been acted on.
        $noPay = (float) ($no_pay_days ?? 0);
        $withPay = max(0, (float) $application->days_applied - $noPay);
        $approved = $application->status === 'approved';
        $rejected = $application->status === 'rejected';

        // Embedded as base64 so dompdf never needs filesystem/remote access
        $logoPath = public_path('images/lgu.png');
        $logo = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    @endphp

    <!-- ============ FRONT PAGE ============ -->

    <div class="form-id">Civil Service Form No. 6<br>Revised 2020</div>

    <table class="header">
        <tr>
            <td style="width:30%; text-align:right; padding-right:12px;">
                @if ($logo)
                    <img src="{{ $logo }}" style="width:64px; height:64px;">
                @endif
            </td>
            <td style="width:40%;" class="header-lines">
                Republic of the Philippines<br>
                Province of Isabela<br>
                Municipality of Echague
            </td>
            <td style="width:30%;"></td>
        </tr>
    </table>

    <div class="form-title">APPLICATION FOR LEAVE</div>

    <table class="main">
        <tr>
            <td style="width:50%;">
                <strong>1. OFFICE/DEPARTMENT</strong><br>
                {{ $application->employee->department->name ?? 'N/A' }}
            </td>
            <td style="width:50%;">
                <strong>2. NAME:</strong>
                <table style="width:100%; border:none; border-collapse:collapse;">
                    <tr>
                        <td style="border:none; width:34%; padding:2px 0;">
                            <span class="small-text">(Last)</span><br>{{ $application->employee->surname }}
                        </td>
                        <td style="border:none; width:33%; padding:2px 0;">
                            <span class="small-text">(First)</span><br>{{ $application->employee->first_name }}
                        </td>
                        <td style="border:none; width:33%; padding:2px 0;">
                            <span class="small-text">(Middle)</span><br>{{ $application->employee->middle_name }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <strong>3. DATE OF FILING</strong><br>
                {{ $application->applied_at ? $application->applied_at->format('F d, Y') : 'N/A' }}
            </td>
            <td>
                <table style="width:100%; border:none; border-collapse:collapse;">
                    <tr>
                        <td style="border:none; width:62%; padding:0;">
                            <strong>4. POSITION</strong><br>
                            {{ $application->employee->position }}
                        </td>
                        <td style="border:none; width:38%; padding:0;">
                            <strong>5. SALARY</strong><br>
                            &nbsp;
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="main" style="margin-top:-1px;">
        <tr>
            <td colspan="2" class="section-title">6. DETAILS OF APPLICATION</td>
        </tr>
        <tr>
            <td style="width:57%;">
                <div style="margin-bottom:7px;"><strong>6.A TYPE OF LEAVE TO BE AVAILED OF</strong></div>

                @foreach ($leaveTypes as [$typeCode, $typeName, $basis])
                    <div class="leave-item">
                        <span
                            class="checkbox {{ $code === $typeCode ? 'checked' : '' }}">{{ $code === $typeCode ? 'X' : '' }}</span>{{ $typeName }}
                        <span class="cite">({{ $basis }})</span>
                    </div>
                @endforeach

                <div style="margin-top:8px;">
                    <em>Others:</em>
                    @if (!in_array($code, $listedCodes, true))
                        <span class="fill" style="min-width:180px;">{{ $application->leaveConfiguration->name }}</span>
                    @else
                        <span class="fill" style="min-width:180px;">&nbsp;</span>
                    @endif
                </div>
            </td>
            <td style="width:43%;">
                <div style="margin-bottom:7px;"><strong>6.B DETAILS OF LEAVE</strong></div>

                <em>In case of Vacation/Special Privilege Leave:</em><br>
                <span class="checkbox"></span>Within the Philippines ______________<br>
                <span class="checkbox"></span>Abroad (Specify) ________________<br><br>

                <em>In case of Sick Leave:</em><br>
                <span class="checkbox"></span>In Hospital (Specify Illness) _________<br>
                <span class="checkbox"></span>Out Patient (Specify Illness) _________<br>
                <div class="blank-line"></div><br>

                <em>In case of Special Leave Benefits for Women:</em><br>
                (Specify Illness) ______________________
                <div class="blank-line"></div><br>

                <em>In case of Study Leave:</em><br>
                <span class="checkbox"></span>Completion of Master's Degree<br>
                <span class="checkbox"></span>BAR/Board Examination Review<br><br>

                <em>Other purpose:</em><br>
                <span class="checkbox"></span>Monetization of Leave Credits<br>
                <span class="checkbox"></span>Terminal Leave
            </td>
        </tr>
        <tr>
            <td>
                <strong>6.C NUMBER OF WORKING DAYS APPLIED FOR</strong><br>
                <div class="blank-line" style="margin:0 20px 10px 0;">
                    {{ $trim($application->days_applied) }} day(s)
                </div>
                <strong>INCLUSIVE DATES</strong><br>
                <div class="blank-line" style="margin:0 20px 0 0;">
                    {{ $application->start_date->format('M d, Y') }} &ndash;
                    {{ $application->end_date->format('M d, Y') }}
                </div>
            </td>
            <td>
                <strong>6.D COMMUTATION</strong><br>
                <span class="checkbox checked">X</span>Not Requested<br>
                <span class="checkbox"></span>Requested
                <div class="sig-line" style="margin-top:20px;">&nbsp;</div>
                <div class="sig-label">(Signature of Applicant)</div>
            </td>
        </tr>
    </table>

    <table class="main" style="margin-top:-1px;">
        <tr>
            <td colspan="2" class="section-title">7. DETAILS OF ACTION ON APPLICATION</td>
        </tr>
        <tr>
            <td style="width:50%;">
                <strong>7.A CERTIFICATION OF LEAVE CREDITS</strong>
                <div class="center" style="margin-top:4px;">
                    As of <span class="fill"
                        style="min-width:130px;">{{ now()->timezone('Asia/Manila')->format('F d, Y') }}</span>
                </div>

                <table class="credits">
                    <tr>
                        <td></td>
                        <td class="num"><strong>Vacation Leave</strong></td>
                        <td class="num"><strong>Sick Leave</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Total Earned</td>
                        <td class="num">{{ $vl_total ?? '' }}</td>
                        <td class="num">{{ $sl_total ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Less this application</td>
                        <td class="num">{{ $deductsFromVl ? $three($application->days_applied) : '' }}</td>
                        <td class="num">{{ $deductsFromSl ? $three($application->days_applied) : '' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Balance</td>
                        <td class="num">{{ $vl_balance ?? '' }}</td>
                        <td class="num">{{ $sl_balance ?? '' }}</td>
                    </tr>
                </table>

                <div class="sig-line">{{ $signatories['hr_officer'] ?? '' }}</div>
                <div class="sig-label">{{ $positions['hr_officer'] ?? 'HR Officer' }}</div>
            </td>
            <td style="width:50%;">
                <strong>7.B RECOMMENDATION</strong><br>
                <span class="checkbox {{ $approved ? 'checked' : '' }}">{{ $approved ? 'X' : '' }}</span>For
                approval<br>
                <span class="checkbox {{ $rejected ? 'checked' : '' }}">{{ $rejected ? 'X' : '' }}</span>For disapproval
                due to
                @if ($rejected && $application->rejection_reason)
                    <div style="border-bottom:1px solid #000; padding:2px 0;">{{ $application->rejection_reason }}</div>
                @else
                    <div class="blank-line"></div>
                @endif
                <div class="blank-line"></div>
                <div class="blank-line"></div>
                <div class="sig-line" style="margin-top:22px;">&nbsp;</div>
                <div class="sig-label">(Authorized Officer)</div>
            </td>
        </tr>
        <tr>
            <td>
                <strong>7.C APPROVED FOR:</strong>
                <div style="margin:6px 0 0 10px; line-height:1.7;">
                    <span class="fill">{{ $approved ? $trim($withPay) : '' }}</span> days with pay<br>
                    <span class="fill">{{ $approved ? $trim($noPay) : '' }}</span> days without pay<br>
                    <span class="fill">&nbsp;</span> others (Specify)
                </div>
            </td>
            <td>
                <strong>7.D DISAPPROVED DUE TO:</strong>
                @if ($rejected && $application->rejection_reason)
                    <div style="border-bottom:1px solid #000; padding:2px 0; margin-top:6px;">
                        {{ $application->rejection_reason }}
                    </div>
                @else
                    <div class="blank-line"></div>
                @endif
                <div class="blank-line"></div>
                <div class="blank-line"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="center" style="padding-top:18px; padding-bottom:6px;">
                <strong><u>{{ $signatories['approving_authority'] ?? '' }}</u></strong><br>
                <strong>{{ $positions['approving_authority'] ?? '' }}</strong>
            </td>
        </tr>
    </table>

    <!-- ============ BACK PAGE — INSTRUCTIONS AND REQUIREMENTS ============ -->

    <div class="back-page">

        <div class="form-id" style="margin-bottom:6px;">Civil Service Form No. 6<br>Revised 2020</div>

        <div class="instructions-title">INSTRUCTIONS AND REQUIREMENTS</div>

        <table class="instructions-table">
            <tr>
                <td class="col">
                    <span class="small-text">
                        Application for any type of leave shall be made on this Form and
                        <strong><u>to be accomplished at least in duplicate</u></strong> with documentary
                        requirements, as follows:
                    </span>

                    <div class="instr-item" style="margin-top:8px;">
                        <strong>1. Vacation leave*</strong><br>
                        It shall be filed five (5) days in advance, whenever possible, of the
                        effective date of such leave. Vacation leave within the Philippines or
                        abroad shall be indicated in the form for purposes of securing travel
                        authority and completing clearance from money and work accountabilities.
                    </div>

                    <div class="instr-item">
                        <strong>2. Mandatory/Forced leave</strong><br>
                        Annual five-day vacation leave shall be forfeited if not taken during the
                        year. In case the scheduled leave has been cancelled in the exigency of
                        the service by the head of agency, it shall no longer be deducted from
                        the accumulated vacation leave. Availment of one (1) day or more Vacation
                        Leave (VL) shall be considered for complying the mandatory/forced leave
                        subject to the conditions under Section 25, Rule XVI of the Omnibus Rules
                        Implementing E.O. No. 292.
                    </div>

                    <div class="instr-item">
                        <strong>3. Sick leave*</strong><br>
                        &bull; It shall be filed immediately upon employee's return from such leave.<br>
                        &bull; If filed in advance or exceeding five (5) days, application shall be
                        accompanied by a <u>medical certificate</u>. In case medical consultation was
                        not availed of, an <u>affidavit</u> should be executed by an applicant.
                    </div>

                    <div class="instr-item">
                        <strong>4. Maternity leave* &ndash; 105 days</strong><br>
                        &bull; Proof of pregnancy e.g. ultrasound, doctor's certificate on the
                        expected date of delivery<br>
                        &bull; Accomplished Notice of Allocation of Maternity Leave Credits (CS
                        Form No. 6a), if needed<br>
                        &bull; Seconded female employees shall enjoy maternity leave with full pay
                        in the recipient agency.
                    </div>

                    <div class="instr-item">
                        <strong>5. Paternity leave &ndash; 7 days</strong><br>
                        Proof of child's delivery e.g. birth certificate, medical certificate and
                        marriage contract.
                    </div>

                    <div class="instr-item">
                        <strong>6. Special Privilege leave &ndash; 3 days</strong><br>
                        It shall be filed/approved for at least one (1) week prior to availment,
                        except on emergency cases. Special privilege leave within the Philippines
                        or abroad shall be indicated in the form for purposes of securing travel
                        authority and completing clearance from money and work accountabilities.
                    </div>

                    <div class="instr-item">
                        <strong>7. Solo Parent leave &ndash; 7 days</strong><br>
                        It shall be filed in advance or whenever possible five (5) days before
                        going on such leave with updated Solo Parent Identification Card.
                    </div>

                    <div class="instr-item">
                        <strong>8. Study leave* &ndash; up to 6 months</strong><br>
                        &bull; Shall meet the agency's internal requirements, if any;<br>
                        &bull; Contract between the agency head or authorized representative and
                        the employee concerned.
                    </div>

                    <div class="instr-item">
                        <strong>9. VAWC leave &ndash; 10 days</strong><br>
                        &bull; It shall be filed in advance or immediately upon the woman
                        employee's return from such leave.<br>
                        &bull; It shall be accompanied by any of the following supporting documents:<br>
                        &nbsp;&nbsp;a. Barangay Protection Order (BPO) obtained from the barangay;<br>
                        &nbsp;&nbsp;b. Temporary/Permanent Protection Order (TPO/PPO) obtained from
                        the court;<br>
                        &nbsp;&nbsp;c. If the protection order is not yet issued by the barangay or
                        the court, a certification issued by the Punong Barangay/Kagawad or
                        Prosecutor or the Clerk of Court that the application for the BPO, TPO or
                        PPO has been filed with the said office shall be sufficient to support the
                        application for the ten-day leave; or<br>
                        &nbsp;&nbsp;d. In the absence of the BPO/TPO/PPO or the certification, a
                        police report specifying the details of the occurrence of violence on the
                        victim and a medical certificate may be considered, at the discretion of
                        the immediate supervisor of the woman employee concerned.
                    </div>
                </td>

                <td class="col">
                    <div class="instr-item">
                        <strong>10. Rehabilitation leave* &ndash; up to 6 months</strong><br>
                        &bull; Application shall be made within one (1) week from the time of the
                        accident except when a longer period is warranted.<br>
                        &bull; Letter request supported by relevant reports such as the police
                        report, if any,<br>
                        &bull; Medical certificate on the nature of the injuries, the course of
                        treatment involved, and the need to undergo rest, recuperation, and
                        rehabilitation, as the case may be.<br>
                        &bull; Written concurrence of a government physician should be obtained
                        relative to the recommendation for rehabilitation if the attending
                        physician is a private practitioner, particularly on the duration of the
                        period of rehabilitation.
                    </div>

                    <div class="instr-item">
                        <strong>11. Special leave benefits for women* &ndash; up to 2 months</strong><br>
                        &bull; The application may be filed in advance, that is, at least five (5)
                        days prior to the scheduled date of the gynecological surgery that will be
                        undergone by the employee. In case of emergency, the application for
                        special leave shall be filed immediately upon employee's return but during
                        confinement the agency shall be notified of said surgery.<br>
                        &bull; The application shall be accompanied by a medical certificate filed
                        out by the proper medical authorities, e.g. the attending surgeon
                        accompanied by a clinical summary reflecting the gynecological disorder
                        which shall be addressed or was addressed by the said surgery; the
                        histopathological report; the operative technique used for the surgery;
                        the duration of the surgery including the peri-operative period (period of
                        confinement around surgery); as well as the employee's estimated period of
                        recuperation for the same.
                    </div>

                    <div class="instr-item">
                        <strong>12. Special Emergency (Calamity) leave &ndash; up to 5 days</strong><br>
                        &bull; The special emergency leave can be applied for a maximum of five (5)
                        straight working days or staggered basis within thirty (30) days from the
                        actual occurrence of the natural calamity/disaster. Said privilege shall be
                        enjoyed once a year, not in every instance of calamity or disaster.<br>
                        &bull; The head of office shall take full responsibility for the grant of
                        special emergency leave and verification of the employee's eligibility to
                        be granted thereof. Said verification shall include: validation of place of
                        residence based on latest available records of the affected employee;
                        verification that the place of residence is covered in the declaration of
                        calamity area by the proper government agency; and such other proofs as
                        may be necessary.
                    </div>

                    <div class="instr-item">
                        <strong>13. Monetization of leave credits</strong><br>
                        Application for monetization of fifty percent (50%) or more of the
                        accumulated leave credits shall be accompanied by letter request to the
                        head of the agency stating the valid and justifiable reasons.
                    </div>

                    <div class="instr-item">
                        <strong>14. Terminal leave*</strong><br>
                        Proof of employee's resignation or retirement or separation from the
                        service.
                    </div>

                    <div class="instr-item">
                        <strong>15. Adoption Leave</strong><br>
                        &bull; Application for adoption leave shall be filed with an authenticated
                        copy of the Pre-Adoptive Placement Authority issued by the Department of
                        Social Welfare and Development (DSWD).
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="footnote">
                    * For leave of absence for thirty (30) calendar days or more and terminal
                    leave, application shall be accompanied by a <u>clearance from money, property
                        and work-related accountabilities</u> (pursuant to CSC Memorandum Circular No.
                    2, s. 1985).
                </td>
            </tr>
        </table>

    </div>

</body>

</html>