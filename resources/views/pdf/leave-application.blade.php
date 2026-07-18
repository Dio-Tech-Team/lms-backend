<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Application for Leave</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
        }

        .header-container {
            display: table;
            width: 100%;
            margin-bottom: 5px;
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
            padding: 4px;
            vertical-align: top;
        }

        .checkbox {
            display: inline-block;
            width: 8px;
            height: 8px;
            border: 1px solid #000;
            margin-right: 3px;
            text-align: center;
            line-height: 8px;
            font-size: 8px;
        }

        .checked {
            background-color: #000;
            color: #fff;
        }

        .section-title {
            font-weight: bold;
            background-color: #e8e8e8;
            text-align: center;
        }

        .small-text {
            font-size: 7px;
        }

        .underline {
            border-bottom: 1px solid #000;
        }

        .center {
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="header-text">
        <p style="margin:0;">Province of Isabela</p>
        <p style="margin:0;">Municipality of Echague</p>
        <h2 style="margin:5px 0;">APPLICATION FOR LEAVE</h2>
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

                <span class="checkbox {{ $code === 'PL' ? 'checked' : '' }}">{{ $code === 'PL' ? 'X' : '' }}</span>
                Paternity Leave <span class="small-text">(R.A. No. 8187)</span><br><br>

                <span class="checkbox {{ $code === 'SPL' ? 'checked' : '' }}">{{ $code === 'SPL' ? 'X' : '' }}</span>
                Special Privilege Leave <span class="small-text">(Sec. 21, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'SOLO' ? 'checked' : '' }}">{{ $code === 'SOLO' ? 'X' : '' }}</span>
                Solo Parent Leave <span class="small-text">(RA No. 8972)</span><br><br>

                <span class="checkbox {{ $code === 'STL' ? 'checked' : '' }}">{{ $code === 'STL' ? 'X' : '' }}</span>
                Study Leave <span class="small-text">(Sec. 68, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'VAWC' ? 'checked' : '' }}">{{ $code === 'VAWC' ? 'X' : '' }}</span>
                10-Day VAWC Leave <span class="small-text">(RA No. 9262)</span><br><br>

                <span
                    class="checkbox {{ $code === 'REHAB' ? 'checked' : '' }}">{{ $code === 'REHAB' ? 'X' : '' }}</span>
                Rehabilitation Privilege <span class="small-text">(Sec. 55, Rule XVI, Omnibus Rules)</span><br><br>

                <span class="checkbox {{ $code === 'SLB' ? 'checked' : '' }}">{{ $code === 'SLB' ? 'X' : '' }}</span>
                Special Leave Benefits for Women <span class="small-text">(RA No. 9710)</span><br><br>

                <span class="checkbox {{ $code === 'CAL' ? 'checked' : '' }}">{{ $code === 'CAL' ? 'X' : '' }}</span>
                Special Emergency (Calamity) Leave <span class="small-text">(CSC MC No. 2, s. 2012)</span><br><br>

                <span class="checkbox {{ $code === 'ADOP' ? 'checked' : '' }}">{{ $code === 'ADOP' ? 'X' : '' }}</span>
                Adoption Leave <span class="small-text">(R.A. No. 8552)</span><br><br>

                <strong>Others:</strong>
                {{ !in_array($code, ['VL', 'FL', 'SL', 'ML', 'PL', 'SPL', 'SOLO', 'STL', 'VAWC', 'REHAB', 'SLB', 'CAL', 'ADOP']) ? $application->leaveConfiguration->name : '_______________________' }}
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
                        <td style="border:1px solid #000;">{{ $code === 'VL' ? $application->days_applied : '' }}</td>
                        <td style="border:1px solid #000;">{{ $code === 'SL' ? $application->days_applied : '' }}</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #000;">Balance</td>
                        <td style="border:1px solid #000;">{{ $vl_balance ?? '' }}</td>
                        <td style="border:1px solid #000;">{{ $sl_balance ?? '' }}</td>
                    </tr>
                </table>
                <br>
                <div class="center underline">&nbsp;</div>
                <div class="center small-text">
                    <strong>FE A. BARTOLOME</strong><br>
                    HR Officer
                </div>
            </td>
            <td style="width:50%;">
                <strong>7.B RECOMMENDATION</strong><br><br>
                <span
                    class="checkbox {{ $application->status === 'approved' ? 'checked' : '' }}">{{ $application->status === 'approved' ? 'X' : '' }}</span>
                For approval<br>
                <span
                    class="checkbox {{ $application->status === 'cancelled' ? 'checked' : '' }}">{{ $application->status === 'cancelled' ? 'X' : '' }}</span>
                For disapproval due to ___________<br><br><br><br>
                <div class="center underline">&nbsp;</div>
                <div class="center small-text">(Authorized Officer)</div>
            </td>
        </tr>
        <tr>
            <td>
                <strong>7.C APPROVED FOR</strong><br><br>
                {{ $application->status === 'approved' ? $application->days_applied : '____' }} days with pay<br>
                ____ days without pay<br>
                ____ others (Specify)
            </td>
            <td>
                <strong>7.D DISAPPROVED DUE TO:</strong><br><br>
                _______________________<br>
                _______________________<br>
                _______________________
            </td>
        </tr>
    </table>

    <br>
    <div class="center">
        <strong>FAUSTINO A. DY, V</strong><br>
        <div class="underline">&nbsp;</div>
        Municipal Mayor
    </div>

</body>

</html>