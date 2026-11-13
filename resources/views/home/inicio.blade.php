@extends('layouts.app')
@section('css')
<style>
.sales-home .sales-banner{background:linear-gradient(115deg,#15263e,#245c73);border-radius:18px;color:white;padding:30px}.sales-home .sales-banner h1{color:white;font-size:28px}.sales-home .card{border-radius:16px}.sales-home .sales-kpi{border-top:4px solid var(--accent);height:100%}.sales-home .sales-value{font-size:clamp(24px,2.3vw,36px);font-weight:700;overflow-wrap:anywhere}.sales-home .sales-chart{min-height:320px}
</style>
@endsection
@section('content')
<div class="sales-home">
    <div class="sales-banner mb-7 d-flex flex-wrap justify-content-between align-items-center gap-5">
        <div><div class="mb-3 opacity-75">RESUMEN DEL NEGOCIO</div><h1 class="mb-3">Tus ventas, de un vistazo</h1><div class="opacity-75">{{ auth()->user()->isAdministrador() ? 'Todas las sucursales' : 'Tu sucursal' }} · {{ $inicio->format('d/m/Y') }} al {{ $fin->format('d/m/Y') }}</div></div>
        <a href="{{ route('factura.formulario') }}" class="btn btn-light">+ Nueva venta</a>
    </div>
    <form action="{{ route('home') }}" method="GET" class="card mb-7">
        <div class="card-body d-flex flex-wrap align-items-end gap-4 py-5">
            <div><label for="fecha_inicio" class="form-label">Desde</label><input class="form-control" type="date" id="fecha_inicio" name="fecha_inicio" value="{{ $inicio->toDateString() }}" required></div>
            <div><label for="fecha_fin" class="form-label">Hasta</label><input class="form-control" type="date" id="fecha_fin" name="fecha_fin" value="{{ $fin->toDateString() }}" required></div>
            <button class="btn btn-primary" type="submit">Ver resumen</button><a href="{{ route('home') }}" class="btn btn-light">Este mes</a><span class="text-muted pb-3">Ventas vigentes · Importes en Bs</span>
        </div>
        @if ($errors->any())<div class="px-7 pb-5 text-danger" role="alert">{{ $errors->first() }}</div>@endif
    </form>
    <div class="row g-5 mb-7">
        @foreach ([['Ventas del período', $resumen->total, '#239c91', 'Total vendido en las fechas elegidas', true], ['Ventas de hoy', $ventasHoy, '#4275d6', now()->format('d/m/Y'), true], ['Recibos del período', $resumen->cantidad, '#a26ad7', 'Operaciones registradas', false], ['Venta promedio', $resumen->promedio, '#e5a03b', 'Importe promedio por recibo', true]] as [$titulo, $valor, $color, $detalle, $moneda])
        <div class="col-sm-6 col-xl-3"><div class="card sales-kpi" style="--accent:{{ $color }}"><div class="card-body"><div class="text-muted fw-semibold mb-3">{{ $titulo }}</div><div class="sales-value text-gray-900">{{ $moneda ? 'Bs ' : '' }}{{ number_format($valor, $moneda ? 2 : 0, ',', '.') }}</div><div class="text-muted mt-3 fs-7">{{ $detalle }}</div></div></div></div>
        @endforeach
    </div>
    <div class="row g-5 mb-7">
        <div class="col-xl-8"><div class="card h-100"><div class="card-header"><h2 class="card-title">Evolución de ventas</h2></div><div class="card-body"><p class="text-muted">Total vendido cada día del período</p><div id="ventas-diarias" class="sales-chart" role="img" aria-label="Barras de ventas diarias"></div></div></div></div>
        <div class="col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="card-title">Ventas por estado de pago</h2></div><div class="card-body"><p class="text-muted">Importe de los recibos por estado; no representa el saldo pendiente.</p><div id="ventas-estados" class="sales-chart" role="img" aria-label="Torta de ventas por estado de pago"></div></div></div></div>
    </div>
    <div class="row g-5 mb-7">
        <div class="col-xl-7"><div class="card h-100"><div class="card-header"><h2 class="card-title">Productos más vendidos</h2><span class="card-toolbar text-muted">Por cantidad</span></div><div class="card-body"><div id="ventas-productos" class="sales-chart" role="img" aria-label="Barras de los ocho productos más vendidos"></div></div></div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header"><h2 class="card-title">Mejores vendedores</h2></div><div class="card-body">
            @forelse ($vendedores as $vendedor)
            <div class="d-flex justify-content-between gap-3 py-4 border-bottom"><div><span class="badge badge-light-primary me-3">{{ $loop->iteration }}</span><span class="fw-bold">{{ $vendedor->usuarioCreador?->name ?? 'Sin vendedor' }}</span><div class="text-muted mt-2">{{ $vendedor->cantidad }} recibos</div></div><span class="fw-bold text-nowrap">Bs {{ number_format($vendedor->total, 2, ',', '.') }}</span></div>
            @empty <p class="text-muted py-10 text-center">Todavía no hay ventas en este período.</p> @endforelse
        </div></div></div>
    </div>
    <div class="card"><div class="card-header"><h2 class="card-title">Últimas ventas del período</h2><a href="{{ route('factura.listado') }}" class="card-toolbar">Ver ventas →</a></div><div class="card-body"><div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted"><th>Recibo</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Estado de pago</th><th class="text-end">Total</th></tr></thead><tbody>
        @forelse ($recientes as $venta)
        <tr><td class="fw-bold">{{ $venta->numero_recibo ?? $venta->id }}</td><td>{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y H:i') }}</td><td>{{ $venta->razon_social ?: 'Sin nombre' }}</td><td>{{ $venta->usuarioCreador?->name ?? 'Sin vendedor' }}</td><td><span class="badge {{ $venta->estado_pago === 'PAGADO' ? 'badge-light-success' : 'badge-light-warning' }}">{{ $venta->estado_pago ?? 'SIN ESTADO' }}</span></td><td class="text-end fw-bold text-nowrap">Bs {{ number_format($venta->total, 2, ',', '.') }}</td></tr>
        @empty <tr><td colspan="6" class="text-center text-muted py-10">No hay ventas para las fechas seleccionadas.</td></tr> @endforelse
    </tbody></table></div></div></div>
</div>
@endsection
@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tendencia = {{ Illuminate\Support\Js::from($tendencia) }};
    const estados = {{ Illuminate\Support\Js::from($estados) }};
    const productos = {{ Illuminate\Support\Js::from($productos) }};
    const money = value => 'Bs ' + Number(value).toLocaleString('es-BO', {minimumFractionDigits:2,maximumFractionDigits:2});
    const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    function chart(id, options, hasData) {
        const element = document.getElementById(id);
        if (!hasData) {element.innerHTML = '<p class="text-muted text-center py-20">No hay ventas para mostrar en este período.</p>'; return;}
        if (typeof ApexCharts === 'undefined') {element.innerHTML = '<p class="text-muted">No se pudo cargar el gráfico. Actualiza la página.</p>'; return;}
        new ApexCharts(element, Object.assign({chart:{height:320,fontFamily:'inherit',toolbar:{show:false}},colors:['#239c91','#4275d6','#e5a03b','#a26ad7'],dataLabels:{enabled:false},tooltip:{y:{formatter:money}}},options)).render();
    }
    chart('ventas-diarias', {
        chart:{type:'bar',height:320,fontFamily:'inherit',toolbar:{show:false}},
        series:[{name:'Ventas',data:tendencia.map(item => item.total)}],
        xaxis:{categories:tendencia.map(item => item.fecha),tickAmount:Math.min(tendencia.length,8)},
        yaxis:{labels:{formatter:value => Number(value).toLocaleString('es-BO')}},
        plotOptions:{bar:{borderRadius:4,columnWidth:'55%'}},grid:{borderColor:'#e8edf2'}
    }, {{ (int) $resumen->cantidad }} > 0);
    chart('ventas-estados', {
        chart:{type:'donut',height:320,fontFamily:'inherit'},series:estados.map(item => Number(item.total)),labels:estados.map(item => item.nombre),
        legend:{position:'bottom',formatter:escapeHtml},plotOptions:{pie:{donut:{size:'68%'}}}
    }, estados.some(item => Number(item.total) > 0));
    chart('ventas-productos', {
        chart:{type:'bar',height:340,fontFamily:'inherit',toolbar:{show:false}},
        series:[{name:'Unidades',data:productos.map(item => Number(item.cantidad))}],xaxis:{categories:productos.map(item => item.nombre)},
        plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'55%'}},tooltip:{y:{formatter:value => Number(value).toLocaleString('es-BO')+' unidades'},x:{formatter:escapeHtml}}
    }, productos.length > 0);
});
</script>
@endsection
