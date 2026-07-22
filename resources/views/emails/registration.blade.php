@php $gold = '#F5B301'; $blue = '#1E56C9'; @endphp
<x-mail-layout title="Registrasi Victoria Sekuritas">
    <p style="margin:0 0 16px;">Kepada Nasabah Yang Terhormat / <em>Dear Valued Client</em>,</p>

    <p style="margin:0 0 16px;">
        Terima kasih telah melakukan pendaftaran Pembukaan Rekening Online pada
        <strong>PT Victoria Sekuritas Indonesia</strong>.<br>
        <span style="color:#6b7280;"><em>Thank you for registering Online Account Opening with PT Victoria Sekuritas Indonesia.</em></span>
    </p>

    <p style="margin:0 0 8px;">Berikut adalah konfirmasi pendaftaran Anda / <em>Following is the confirmation of your registration</em>:</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;font-size:14px;">
        <tr><td style="padding:4px 12px 4px 0;color:#6b7280;">Email</td><td style="padding:4px 0;font-weight:600;">{{ $email }}</td></tr>
        @isset($userId)
        <tr><td style="padding:4px 12px 4px 0;color:#6b7280;">User ID</td><td style="padding:4px 0;font-weight:600;">{{ $userId }}</td></tr>
        @endisset
    </table>

    <p style="text-align:center;margin:0 0 24px;">
        <a href="{{ $activationUrl }}" style="background:{{ $blue }};color:#fff;text-decoration:none;padding:14px 32px;border-radius:12px;font-weight:700;display:inline-block;">
            Aktivasi Akun
        </a>
    </p>

    <p style="margin:0 0 16px;color:#6b7280;font-size:13px;">
        Anda dapat menggunakan Email dan Password ini untuk melengkapi data dan mengunggah dokumen yang diperlukan.<br>
        <em>You can use this Email and Password to complete the data and upload the required documents.</em>
    </p>

    <p style="margin:0;">Terima kasih,<br>Hormat kami, <strong>PT Victoria Sekuritas Indonesia</strong></p>
</x-mail-layout>
