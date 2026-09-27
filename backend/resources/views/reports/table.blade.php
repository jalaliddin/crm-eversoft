<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; font-weight: bold; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ $organization }} · Generated {{ $generatedAt }}</div>
    <table>
        <thead>
            <tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
