<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Labels') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, "Segoe UI", Roboto, Arial, sans-serif;
            color: #111;
            background: #f4f4f5;
        }
        .toolbar {
            position: sticky;
            top: 0;
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 12px 20px;
            background: #fff;
            border-bottom: 1px solid #e4e4e7;
        }
        .toolbar button {
            font: inherit;
            padding: 8px 16px;
            border: 0;
            border-radius: 8px;
            background: #18181b;
            color: #fff;
            cursor: pointer;
        }
        .toolbar span { color: #71717a; font-size: 14px; }
        .sheet {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            padding: 16px;
        }
        .label {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: #fff;
            border: 1px solid #d4d4d8;
            border-radius: 8px;
            break-inside: avoid;
        }
        .label .qr { width: 72px; height: 72px; flex: 0 0 72px; }
        .label .qr svg { width: 100%; height: 100%; display: block; }
        .label .text { min-width: 0; }
        .label .title { font-weight: 600; font-size: 14px; word-break: break-word; }
        .label .line { font-size: 11px; color: #52525b; word-break: break-word; }
        .empty { padding: 48px; text-align: center; color: #71717a; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; gap: 4px; }
            .label { border-color: #999; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <span>{{ __(':count labels', ['count' => count($labels)]) }}</span>
    </div>

    @if (empty($labels))
        <p class="empty">{{ __('Nothing to label here.') }}</p>
    @else
        <div class="sheet">
            @foreach ($labels as $label)
                <div class="label">
                    <div class="qr">{!! $label['qr'] !!}</div>
                    <div class="text">
                        <div class="title">{{ $label['title'] }}</div>
                        @foreach ($label['lines'] as $line)
                            <div class="line">{{ $line }}</div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
