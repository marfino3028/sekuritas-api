@props(['title' => 'Danapathi Asset Management'])
@php
    // Palet brand Danapathi (lihat sekuritas-infra/DESIGN_DANAPATHI.md)
    $navy = '#14365F'; $blue = '#14365F'; $gold = '#198754'; $slate = '#F5F7FB';
    $logo = rtrim(config('app.frontend_url'), '/') . '/logo-white.png';
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
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(15,53,103,.08);">
                <tr>
                    <td style="background:linear-gradient(135deg,#0F2F55 0%,{{ $navy }} 55%,#1F4F87 100%);padding:24px 32px;">
                        <img src="{{ $logo }}" alt="Danapathi Asset Management" height="44" style="display:block;height:44px;width:auto;border:0;">
                    </td>
                </tr>
                <tr><td style="padding:32px;font-size:15px;line-height:24px;">
                    {{ $slot }}
                </td></tr>
                <tr>
                    <td style="background:#1A4978;padding:24px 32px;color:#dbe4ef;font-size:12px;line-height:20px;">
                        <strong style="color:#fff;">PT Danapathi Asset Management</strong><br>
                        Berizin &amp; Diawasi oleh Otoritas Jasa Keuangan (OJK).<br>
                        Email ini dikirim otomatis, mohon tidak membalas. &copy; {{ date('Y') }} Danapathi Asset Management.
                    </td>
                </tr>
            </table>
            <p style="color:#9aa4b2;font-size:11px;margin:16px 0 0;">Investasi melalui reksa dana mengandung risiko. Baca prospektus sebelum berinvestasi. Kinerja masa lalu tidak mencerminkan kinerja masa depan.</p>
        </td></tr>
    </table>
</body>
</html>
