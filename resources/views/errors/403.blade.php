<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Forbidden</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0e1a;
            color: #cbd5e1;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .wrap {
            display: flex;
            align-items: flex-start;
            gap: 24px;
            max-width: 520px;
            padding: 0 24px;
        }
        .code {
            font-size: 28px;
            font-weight: 600;
            color: #94a3b8;
            padding-top: 2px;
        }
        .divider {
            width: 1px;
            align-self: stretch;
            background: #334155;
        }
        .message {
            font-size: 18px;
            line-height: 1.6;
            color: #cbd5e1;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-secondary {
            background: transparent;
            color: #cbd5e1;
            border: 1px solid #334155;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="code">403</div>
        <div class="divider"></div>
        <div>
            <div class="message">
                {{ $exception->getMessage() ?: 'You are not authorized to access this page.' }}
            </div>
            <div class="actions">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Log Out</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>