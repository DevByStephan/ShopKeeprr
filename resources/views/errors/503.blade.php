<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>We'll be right back — {{ config('app.name', 'ShopKeeprr') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #17231f;
            background:
                radial-gradient(circle at 15% 10%, #dff3ec 0, transparent 38%),
                radial-gradient(circle at 88% 88%, #fdf0d4 0, transparent 40%),
                #f7f8f5;
        }
        .card {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border: 1px solid #e3e9e5;
            border-radius: 24px;
            padding: 44px;
            box-shadow: 0 24px 60px rgba(23, 35, 31, .08);
        }
        .mark {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: #0f766e;
            color: #fff;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -.04em;
        }
        .eyebrow {
            margin-top: 26px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #0f766e;
        }
        h1 {
            margin: 8px 0 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 36px;
            line-height: 1.1;
            letter-spacing: -.02em;
        }
        p { margin: 14px 0 0; color: #687771; line-height: 1.65; font-size: 14px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 28px; }
        a, button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 11px 20px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .primary { background: #0f766e; color: #fff; }
        .secondary { background: #f0f5f2; color: #17231f; }
        .note { margin-top: 24px; padding-top: 18px; border-top: 1px solid #edf2ef; font-size: 12px; color: #8a9892; }
        @media (max-width: 520px) { .card { padding: 30px 24px; } h1 { font-size: 30px; } }
    </style>
</head>
<body>
    <main class="card">
        <div class="mark">S</div>
        <div class="eyebrow">Scheduled maintenance</div>
        <h1>We'll be right back.</h1>
        <p>
            The store is being updated. Orders are paused for a few minutes while files and database changes are applied safely.
        </p>
        <p>Nothing in your cart or account has been lost. Please try again in a moment.</p>
        <div class="actions">
            <button class="primary" type="button" onclick="window.location.reload()">Try again</button>
            <a class="secondary" href="{{ url('/') }}">Back to store</a>
        </div>
        <div class="note">
            Store owners: an update is in progress, or the store was placed in maintenance mode deliberately.
            When the update finishes this page disappears automatically.
        </div>
    </main>
</body>
</html>
