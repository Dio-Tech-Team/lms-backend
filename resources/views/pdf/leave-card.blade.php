<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: letter landscape;
            margin: 30px 40px;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
        }

        h1 {
            font-size: 18px;
            text-align: center;
            margin: 0 0 3px;
        }

        h2 {
            font-size: 13px;
            text-align: center;
            margin: 0 0 18px;
            font-weight: normal;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .info-cell {
            display: table-cell;
            width: 50%;
            padding-right: 10px;
            line-height: 1.8;
        }

        .info-cell span.label {
            color: #666;
            display: inline-block;
            width: 130px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 9px 8px;
            text-align: center;
        }

        th {
            background: #eee;
            font-size: 10px;
            text-transform: uppercase;
        }

        td {
            font-size: 11px;
        }

        .left {
            text-align: left;
        }

        .num {
            font-family: 'DejaVu Sans Mono', monospace;
        }

        th.group {
            background: #ddd;
        }
    </style>
</head>

<body>
    <div style="text-align: center; margin-bottom: 25px;">
        <h1 style="margin: 0 0 3px;">LOCAL GOVERNMENT UNIT</h1>
        <h2 style="margin: 0 0 12px;">Echague, Isabela<br>EMPLOYEE'S LEAVE CARD</h2>
    </div>

    <div style="margin-bottom: 20px; line-height: 2;">
        <table style="border: none; width: 100%;">
            <tr>
                <td style="border: none; padding: 2px 0; text-align: left;">
                    <b>Name:</b> <span
                        style="border-bottom: 1px solid #333; padding: 0 4px;">{{ $employee['name'] }}</span>
                </td>

                <td style="border: none; padding: 2px 0; text-align: left;">
                    <b>First Day of Service:</b> <span
                        style="border-bottom: 1px solid #333; padding: 0 4px;">{{ \Carbon\Carbon::parse($employee['date_hired'])->format('m-d-y') }}</span>
                </td>

            </tr>
            <tr>
                <td style="border: none; padding: 2px 0; text-align: left;">
                    <b>Designation:</b> <span
                        style="border-bottom: 1px solid #333; padding: 0 4px;">{{ $employee['position'] }}</span>
                </td>
                <td style="border: none; padding: 2px 0; text-align: left;">
                    <b>Status of Appointment:</b> <span
                        style="border-bottom: 1px solid #333; padding: 0 4px;">{{ ucfirst($employee['employment_status']) }}</span>
                </td>

            </tr>
        </table>
    </div>
    @php
        // Merge vacation_leave and sick_leave into paired rows by period,
        // preserving chronological order, so VL and SL sit side by side
        // in one shared table like the physical leave card does.
        $vlByPeriod = [];
        foreach ($vacation_leave as $row)
            $vlByPeriod[$row['period']][] = $row;

        $slByPeriod = [];
        foreach ($sick_leave as $row)
            $slByPeriod[$row['period']][] = $row;

        $orderedPeriods = [];
        foreach (array_merge($vacation_leave, $sick_leave) as $row) {
            if (!in_array($row['period'], $orderedPeriods)) {
                $orderedPeriods[] = $row['period'];
            }
        }

        $mergedRows = [];
        foreach ($orderedPeriods as $period) {
            $vlRows = $vlByPeriod[$period] ?? [null];
            $slRows = $slByPeriod[$period] ?? [null];
            $max = max(count($vlRows), count($slRows));

            for ($i = 0; $i < $max; $i++) {
                $mergedRows[] = [
                    'period' => $period,
                    'vl' => $vlRows[$i] ?? null,
                    'sl' => $slRows[$i] ?? null,
                ];
            }
        }
    @endphp

    <table>
        <thead>
            <tr>
                <th rowspan="2">Period</th>
                <th rowspan="2">Particulars</th>
                <th colspan="4" class="group">Vacation Leave</th>
                <th colspan="4" class="group">Sick Leave</th>
            </tr>
            <tr>
                <th>Earned</th>
                <th>Abs. Und. W/P</th>
                <th>Balance</th>
                <th>Abs. Und. WOP</th>
                <th>Earned</th>
                <th>Abs. Und. W/P</th>
                <th>Balance</th>
                <th>Abs. Und. WOP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mergedRows as $row)
                <tr>
                    <td class="left">{{ $row['period'] }}</td>
                    <td class="left">{{ $row['vl']['particulars'] ?? $row['sl']['particulars'] ?? '' }}</td>
                    <td class="num">
                        {{ isset($row['vl']) && $row['vl']['earned'] > 0 ? number_format($row['vl']['earned'], 3) : '' }}
                    </td>
                    <td class="num">
                        {{ isset($row['vl']) && $row['vl']['abs_wp'] > 0 ? number_format($row['vl']['abs_wp'], 3) : '' }}
                    </td>
                    <td class="num">{{ isset($row['vl']) ? number_format($row['vl']['balance'], 3) : '' }}</td>
                    <td class="num">
                        {{ isset($row['vl']) && $row['vl']['abs_wop'] > 0 ? number_format($row['vl']['abs_wop'], 3) : '' }}
                    </td>
                    <td class="num">
                        {{ isset($row['sl']) && $row['sl']['earned'] > 0 ? number_format($row['sl']['earned'], 3) : '' }}
                    </td>
                    <td class="num">
                        {{ isset($row['sl']) && $row['sl']['abs_wp'] > 0 ? number_format($row['sl']['abs_wp'], 3) : '' }}
                    </td>
                    <td class="num">{{ isset($row['sl']) ? number_format($row['sl']['balance'], 3) : '' }}</td>
                    <td class="num">
                        {{ isset($row['sl']) && $row['sl']['abs_wop'] > 0 ? number_format($row['sl']['abs_wop'], 3) : '' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;color:#999;">No leave history yet</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>