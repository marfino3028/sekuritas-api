@props(['title' => 'Victoria Sekuritas'])
@php
    // Palet brand Victoria Sekuritas (lihat ANALISA_DAN_DESIGN.md)
    $navy = '#0B2A5B'; $blue = '#1E56C9'; $gold = '#F5B301'; $slate = '#F5F7FB';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $slate }};font-family:Helvetica,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $slate }};padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(11,42,91,.08);">
                <tr>
                    <td style="background:linear-gradient(135deg,#0A1F44 0%,{{ $navy }} 55%,{{ $blue }} 100%);padding:28px 32px;">
                        <span style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.3px;">Victoria Sekuritas</span>
                        <span style="display:inline-block;width:8px;height:8px;background:{{ $gold }};border-radius:50%;margin-left:6px;"></span>
                    </td>
                </tr>
                <tr><td style="padding:32px;font-size:15px;line-height:24px;">
                    {{ $slot }}
                </td></tr>
                <tr>
                    <td style="background:{{ $navy }};padding:24px 32px;color:#c7d2e6;font-size:12px;line-height:20px;">
                        <strong style="color:#fff;">PT Victoria Sekuritas Indonesia</strong><br>
                        Berizin &amp; Diawasi oleh Otoritas Jasa Keuangan (OJK).<br>
                        Email ini dikirim otomatis, mohon tidak membalas. &copy; {{ date('Y') }} Victoria Sekuritas.
                    </td>
                </tr>
            </table>
            <p style="color:#9aa4b2;font-size:11px;margin:16px 0 0;">Investasi mengandung risiko. Kinerja masa lalu tidak mencerminkan kinerja masa depan.</p>
        </td></tr>
    </table>
</body>
</html>
