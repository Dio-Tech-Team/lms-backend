<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Application for Leave</title>
    <style>
        /* Philippine "Long"/"Legal" bond paper = 8.5in x 13in (NOT US Legal, which is 8.5x14) */
        @page {
            size: 8.5in 13in;
            margin: 0.5in 0.6in;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .header-container {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }

        .header-text {
            text-align: center;
        }

        .header-text h4,
        .header-text h2 {
            margin: 2px 0;
        }

        table.main {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        table.main td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }

        .checkbox {
            display: inline-block;
            width: 9px;
            height: 9px;
            border: 1px solid #000;
            margin-right: 4px;
            text-align: center;
            line-height: 9px;
            font-size: 9px;
        }

        .checked {
            background-color: #000;
            color: #fff;
        }

        .section-title {
            font-weight: bold;
            background-color: #e8e8e8;
            text-align: center;
            padding: 5px;
        }

        .small-text {
            font-size: 8px;
        }

        .underline {
            border-bottom: 1px solid #000;
        }

        .center {
            text-align: center;
        }

        /* Back page (Instructions) */
        .back-page {
            page-break-before: always;
        }

        .instructions-title {
            font-weight: bold;
            text-align: center;
            font-size: 13px;
            border: 1px solid #000;
            padding: 6px;
            margin-bottom: 8px;
        }

        .instructions-table {
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
        }

        .instructions-table td {
            vertical-align: top;
            padding: 10px 12px;
            font-size: 9px;
            line-height: 1.4;
        }

        .instructions-table td.col {
            width: 50%;
            border: none;
        }

        .instructions-table td.footnote {
            border-top: 1px solid #000;
            font-size: 8px;
            line-height: 1.35;
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

        // Codes that have their own printed checkbox on this form.
        $listedCodes = ['VL', 'FL', 'SL', 'ML', 'PTL', 'SPL', 'SOL', 'STL', 'VAWC', 'RHL', 'SLB', 'CAL', 'ADL'];

        // Days without pay are only known once the application has been acted on.
        $noPay = (float) ($no_pay_days ?? 0);
        $withPay = max(0, (float) $application->days_applied - $noPay);
    @endphp

    <!-- ============ FRONT PAGE ============ -->

    <div class="header-text">
        <p style="margin:0; font-weight: bold;">Republic of the Philippines</p>
        <p style="margin:0; font-weight: bold;">Province of Isabela</p>
        <p style="margin:0;font-weight: bold;">Municipality of Echague</p>
        <h2 style="margin:5px 0; font-weight: bold;">APPLICATION FOR LEAVE</h2>
    </div>

    <table class="main">
        <tr>
            <td style="width:50%;">
                <strong>1. OFFICE/DEPARTMENT</strong><br><br>
                {{ $application->employee->department->name ?? 'N/A' }}
            </td>
            <td style="width:50%;">
                <strong>2. NAME:</strong>
                <table style="width:100%; border:none;">
                    <tr style="border:none;">
                        <td style="border:none; width:33%;">(Last)<br>{{ $application->employee->surname }}</td>
                        <td style="border:none; width:33%;">(First)<br>{{ $application->employee->first_name }}</td>
                        <td style="border:none; width:34%;">(Middle)<br>{{ $application->employee->middle_name }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <strong>3. DATE OF FILING</strong><br><br>
                {{ $application->applied_at ? $application->applied_at->format('F d, Y') : 'N/A' }}
            </td>
            <td>
                <table style="width:100%; border:none;">
                    <tr style="border:none;">
                        <td style="border:none; width:60%;">
                            <strong>4. POSITION</strong><br><br>
                            {{ $application->employee->position }}
                        </td>
                        <td style="border:none; width:40%;">
                            <strong>5. SALARY</strong><br><br>
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
            <td style="width:50%;">
                <strong>6.A TYPE OF LEAVE TO BE AVAILED OF</strong><br><br>

                <span class="checkbox {{ $code === 'VL' ? 'checked' : '' }}">{{ $code === 'VL' ? 'X' : '' }}</span>
                Vacation Leave <span class="small-text">(Sec. 51, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'FL' ? 'checked' : '' }}">{{ $code === 'FL' ? 'X' : '' }}</span>
                Mandatory/Forced Leave <span class="small-text">(Sec. 25, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'SL' ? 'checked' : '' }}">{{ $code === 'SL' ? 'X' : '' }}</span>
                Sick Leave <span class="small-text">(Sec. 43, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'ML' ? 'checked' : '' }}">{{ $code === 'ML' ? 'X' : '' }}</span>
                Maternity Leave <span class="small-text">(R.A. No. 11210)</span><br><br>

                <span class="checkbox {{ $code === 'PTL' ? 'checked' : '' }}">{{ $code === 'PTL' ? 'X' : '' }}</span>
                Paternity Leave <span class="small-text">(R.A. No. 8187)</span><br><br>

                <span class="checkbox {{ $code === 'SPL' ? 'checked' : '' }}">{{ $code === 'SPL' ? 'X' : '' }}</span>
                Special Privilege Leave <span class="small-text">(Sec. 21, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'SOL' ? 'checked' : '' }}">{{ $code === 'SOL' ? 'X' : '' }}</span>
                Solo Parent Leave <span class="small-text">(RA No. 8972)</span><br><br>

                <span class="checkbox {{ $code === 'STL' ? 'checked' : '' }}">{{ $code === 'STL' ? 'X' : '' }}</span>
                Study Leave <span class="small-text">(Sec. 68, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'VAWC' ? 'checked' : '' }}">{{ $code === 'VAWC' ? 'X' : '' }}</span>
                10-Day VAWC Leave <span class="small-text">(RA No. 9262)</span><br><br>

                <span class="checkbox {{ $code === 'RHL' ? 'checked' : '' }}">{{ $code === 'RHL' ? 'X' : '' }}</span>
                Rehabilitation Privilege <span class="small-text">(Sec. 55, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'SLB' ? 'checked' : '' }}">{{ $code === 'SLB' ? 'X' : '' }}</span>
                Special Leave Benefits for Women <span class="small-text">(RA No. 9710)</span><br><br>

                <span class="checkbox {{ $code === 'CAL' ? 'checked' : '' }}">{{ $code === 'CAL' ? 'X' : '' }}</span>
                Special Emergency (Calamity) Leave <span class="small-text">(CSC MC No. 2, s. 2012)</span><br><br>

                <span class="checkbox {{ $code === 'ADL' ? 'checked' : '' }}">{{ $code === 'ADL' ? 'X' : '' }}</span>
                Adoption Leave <span class="small-text">(R.A. No. 8552)</span><br><br>

                <strong>Others:</strong>
                {{ !in_array($code, $listedCodes, true) ? $application->leaveConfiguration->name : '_______________________' }}
            </td>
            <td style="width:50%;">
                <strong>6.B DETAILS OF LEAVE</strong><br><br>

                <em>In case of Vacation/Special Privilege Leave:</em><br>
                <span class="checkbox"></span> Within the Philippines _______________<br>
                <span class="checkbox"></span> Abroad (Specify) _______________<br><br>

                <em>In case of Sick Leave:</em><br>
                <span class="checkbox"></span> In Hospital (Specify Illness) _______________<br>
                <span class="checkbox"></span> Out Patient (Specify Illness) _______________<br><br><br>

                <em>In case of Special Leave Benefits for Women:</em><br>
                (Specify Illness) _______________________<br><br><br>

                <em>In case of Study Leave:</em><br>
                <span class="checkbox"></span> Completion of Master's Degree<br>
                <span class="checkbox"></span> BAR/Board Examination Review<br>
                <em>Other purpose:</em><br>
                <span class="checkbox"></span> Monetization of Leave Credits<br>
                <span class="checkbox"></span> Terminal Leave<br>
            </td>
        </tr>
        <tr>
            <td>
                <strong>6.C NUMBER OF WORKING DAYS APPLIED FOR</strong><br><br>
                {{ $application->days_applied }} days<br><br>
                <strong>INCLUSIVE DATES</strong><br>
                {{ $application->start_date->format('M d, Y') }} - {{ $application->end_date->format('M d, Y') }}
            </td>
            <td>
                <strong>6.D COMMUTATION</strong><br><br>
                <span class="checkbox checked">X</span> Not Requested<br>
                <span class="checkbox"></span> Requested<br><br><br><br>
                <div class="center underline">&nbsp;</div>
                <div class="center small-text">(Signature of Applicant)</div>
            </td>
        </tr>
    </table>

    <table class="main" style="margin-top:-1px;">
        <tr>
            <td colspan="2" class="section-title">7. DETAILS OF ACTION ON APPLICATION</td>
        </tr>
        <tr>
            <td style="width:50%;">
                <strong>7.A CERTIFICATION OF LEAVE CREDITS</strong><br><br>
                <table style="width:100%; border-collapse: collapse;">
                    <tr>
                        <td style="border:1px solid #000;"></td>
                        <td style="border:1px solid #000; text-align:center;"><strong>Vacation Leave</strong></td>
                        <td style="border:1px solid #000; text-align:center;"><strong>Sick Leave</strong></td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #000;">Total Earned</td>
                        <td style="border:1px solid #000;">{{ $vl_total ?? '' }}</td>
                        <td style="border:1px solid #000;">{{ $sl_total ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #000;">Less this application</td>
                        <td style="border:1px solid #000;">{{ $deductsFromVl ? $application->days_applied : '' }}</td>
                        <td style="border:1px solid #000;">{{ $deductsFromSl ? $application->days_applied : '' }}</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #000;">Balance</td>
                        <td style="border:1px solid #000;">{{ $vl_balance ?? '' }}</td>
                        <td style="border:1px solid #000;">{{ $sl_balance ?? '' }}</td>
                    </tr>
                </table>
                <br>

                <div class="center small-text">
                    <strong>FE A. BARTOLOME</strong>
                </div>
                <div class="center underline">&nbsp;</div>
                <strong> HR Officer</strong>
            </td>
            <td style="width:50%;">
                <strong>7.B RECOMMENDATION</strong><br><br>
                <span
                    class="checkbox {{ $application->status === 'approved' ? 'checked' : '' }}">{{ $application->status === 'approved' ? 'X' : '' }}</span>
                For approval<br>
                <span
                    class="checkbox {{ $application->status === 'rejected' ? 'checked' : '' }}">{{ $application->status === 'rejected' ? 'X' : '' }}</span>
                For disapproval due to
                {{ $application->status === 'rejected' && $application->rejection_reason ? $application->rejection_reason : '_________________________________________________' }}
                <br>
                _________________________________________________<br>
                _________________________________________________<br><br><br>
                <div class="center underline">&nbsp;</div>
                <div class="center small-text">(Authorized Officer)</div>
            </td>
        </tr>
        <tr>
            <td>
                <strong>7.C APPROVED FOR</strong><br><br>
                {{ $application->status === 'approved' ? rtrim(rtrim(number_format($withPay, 3, '.', ''), '0'), '.') : '_______' }}
                days with pay<br>
                {{ $application->status === 'approved' ? rtrim(rtrim(number_format($noPay, 3, '.', ''), '0'), '.') : '_______' }}
                days without pay<br>
                _______ others (Specify)
            </td>
            <td>
                <strong>7.D DISAPPROVED DUE TO:</strong><br><br>
                @if ($application->status === 'rejected' && $application->rejection_reason)
                    &nbsp;&nbsp;{{ $application->rejection_reason }}
                @else
                    &nbsp;&nbsp;__________________________________________<br>
                    &nbsp;&nbsp;__________________________________________<br>
                    &nbsp;&nbsp;__________________________________________
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="2" class="center">
                <strong><u>FAUSTINO A. DY, V</u></strong>
                <br>
                Municipal Mayor
            </td>
        </tr>
    </table>



    <!-- ============ BACK PAGE — INSTRUCTIONS AND REQUIREMENTS ============ -->

    <div class="back-page">

        <div class="instructions-title">INSTRUCTIONS AND REQUIREMENTS</div>

        <table class="instructions-table">
            <tr>
                <td class="col" style="border-right: 1px solid #000;">
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