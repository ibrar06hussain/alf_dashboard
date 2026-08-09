<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ALF Applications Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h2 { margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f3f3; }
    </style>
</head>
<body>
    <h2>GB ALF Applications Export</h2>
    <p>Generated: {{ now()->format('Y-m-d H:i') }}</p>
    <table>
        <thead>
        <tr>
            <th>App No</th>
            <th>Applicant</th>
            <th>CNIC</th>
            <th>District</th>
            <th>Department</th>
            <th>Scheme</th>
            <th>Status</th>
            <th>Submitted</th>
        </tr>
        </thead>
        <tbody>
        @foreach($applications as $application)
            <tr>
                <td>{{ $application->application_no }}</td>
                <td>{{ $application->applicant?->full_name }}</td>
                <td>{{ $application->applicant?->cnic }}</td>
                <td>{{ $application->district?->name }}</td>
                <td>{{ $application->scheme?->department?->name }}</td>
                <td>{{ $application->scheme?->title }}</td>
                <td>{{ $application->status }}</td>
                <td>{{ optional($application->submitted_at ?? $application->created_at)?->format('Y-m-d') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
