@props(['title' => 'PT LiF Manajemen Investasi'])
@php
    // Palet brand LiF (lihat sekuritas-infra/DESIGN_LIF.md)
    $navy = '#0B203A'; $blue = '#1D5191'; $gold = '#E8762E'; $slate = '#F9FAFB';
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
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1);">
                <tr>
                    <td style="background:{{ $blue }};padding:24px 32px;">
                        <img src="{{ $logo }}" alt="PT LiF Manajemen Investasi" height="48" style="display:block;height:48px;width:auto;border:0;">
                    </td>
                </tr>
                <tr><td style="padding:32px;font-size:15px;line-height:24px;">
                    {{ $slot }}
                </td></tr>
                <tr>
                    <td style="background:{{ $navy }};padding:24px 32px;color:#dbe4ef;font-size:12px;line-height:20px;">
                        <strong style="color:#fff;">PT LiF Manajemen Investasi</strong><br>
                        Menara Batavia Lt. 6 Unit 3A, Jl. K.H. Mas Mansyur No. 126, Jakarta Pusat 10220 · (021) 2253 5128<br>
                        Berizin &amp; Diawasi oleh Otoritas Jasa Keuangan (OJK).<br>
                        Email ini dikirim otomatis, mohon tidak membalas. &copy; {{ date('Y') }} PT LiF Manajemen Investasi.
                    </td>
                </tr>
            </table>
            <p style="color:#9aa4b2;font-size:11px;margin:16px 0 0;">Investasi melalui reksa dana mengandung risiko. Baca prospektus sebelum berinvestasi. Kinerja masa lalu tidak mencerminkan kinerja masa depan.</p>
        </td></tr>
    </table>
</body>
</html>
