@php $blue = '#1D5191'; @endphp
<x-mail-layout :title="$title">
    <p style="margin:0 0 16px;">Yth. Bapak/Ibu <strong>{{ $name }}</strong>,</p>

    @foreach ($lines as $line)
        <p style="margin:0 0 16px;">{{ $line }}</p>
    @endforeach

    @if (count($details))
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;font-size:14px;width:100%;background:#F9FAFB;border-radius:8px;">
            @foreach ($details as $label => $value)
                <tr>
                    <td style="padding:8px 12px;color:#6b7280;width:45%;">{{ $label }}</td>
                    <td style="padding:8px 12px;font-weight:600;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($cta)
        <p style="text-align:center;margin:0 0 24px;">
            <a href="{{ $cta['url'] }}" style="background:{{ $blue }};color:#fff;text-decoration:none;padding:14px 32px;border-radius:12px;font-weight:700;display:inline-block;">
                {{ $cta['label'] }}
            </a>
        </p>
    @endif

    <p style="margin:0;">Terima kasih,<br>Hormat kami, <strong>{{ $brand }}</strong></p>
</x-mail-layout>
