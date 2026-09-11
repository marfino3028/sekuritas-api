@php $blue = '#198754'; @endphp
<x-mail-layout title="Lengkapi Akun — Danapathi Asset Management">
    <p style="margin:0 0 16px;">Kepada Nasabah Yang Terhormat / <em>Dear Valued Customer</em>,</p>

    <p style="margin:0 0 16px;">
        Terima kasih, Anda telah menyelesaikan proses pengisian pembukaan rekening online.<br>
        <span style="color:#6b7280;"><em>Thank you, you have completed filling the online account opening process.</em></span>
    </p>

    <p style="margin:0 0 8px;">
        Untuk melanjutkan, kami lampirkan formulir pembukaan rekening yang harus ditandatangani /
        <em>we attach application forms that must be signed</em>:
    </p>
    <ul style="margin:0 0 20px;padding-left:18px;font-size:14px;line-height:26px;">
        <li>Formulir Pembukaan Rekening Reksa Dana Danapathi — <a href="{{ $linkEfek ?? '#' }}" style="color:{{ $blue }};">Unduh</a></li>
        <li>Formulir Profil Risiko Pemodal — <a href="{{ $linkRdn ?? '#' }}" style="color:{{ $blue }};">Unduh</a></li>
        @isset($linkTax)<li>Formulir W-8BEN / W-9 / CRS — <a href="{{ $linkTax }}" style="color:{{ $blue }};">Unduh</a></li>@endisset
    </ul>

    <p style="margin:0 0 16px;color:#6b7280;font-size:13px;">
        Mohon tandatangani formulir tersebut dan pastikan tanda tangan sesuai dengan identitas, lalu kirimkan beserta
        dokumen pendukung yang dibutuhkan. Jika Anda menggunakan tanda tangan digital (Privy), proses ini dilakukan
        langsung di aplikasi.
    </p>

    <p style="margin:0 0 4px;font-size:13px;">Dokumen dikirimkan ke / <em>Documents send to</em>:</p>
    <p style="margin:0 0 20px;font-size:13px;line-height:22px;">
        Up. Customer Service — PT Danapathi Asset Management<br>
        (Manajer Investasi berizin dan diawasi OJK)
    </p>

    <p style="margin:0;font-size:13px;">
        Pertanyaan? Hubungi Client Services kami melalui telepon atau email resmi Danapathi Asset Management.
    </p>
</x-mail-layout>
