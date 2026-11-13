@if ($soloImagen ?? false)
<img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('assets/img/logo_moto.jpg'))) }}" alt="MR RAILL - Accesorios para motos" style="width: {{ $anchoLogo ?? 90 }}px; height: auto;">
@else
<table style="width:100%; border-collapse:collapse; margin:0 0 14px; border-bottom:1px solid #bbb; page-break-inside:avoid;">
    <tr>
        <td style="width:{{ $anchoLogo ?? 90 }}px; border:0; background:white; padding:0 12px 8px 0; vertical-align:middle;">
            @include('partials.logoReporte', ['soloImagen' => true])
        </td>
        <td style="border:0; background:white; padding:0 8px 8px; text-align:center; vertical-align:middle; color:#222;">{{ $slot }}</td>
        <td style="width:{{ $anchoLogo ?? 90 }}px; border:0; background:white; padding:0 0 8px 12px;"></td>
    </tr>
</table>
@endif
