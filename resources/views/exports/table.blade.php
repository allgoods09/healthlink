<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 20px;
        }

        .meta {
            margin-bottom: 16px;
            color: #4b5563;
            font-size: 11px;
        }

        .filters {
            margin-bottom: 12px;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f3f4f6;
        }

        tr:nth-child(even) td {
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Generated at {{ $generatedAt->format('F j, Y g:i A') }}
    </div>

    @if(!empty($filters))
        <div class="filters">
            <strong>Filters:</strong>
            {{ collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode(' | ') }}
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
