<?php

namespace App\Http\Controllers;

use App\Models\Detalle;
use App\Models\Factura;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $datos = $request->validate([
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ]);
        $inicio = Carbon::parse($datos['fecha_inicio'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $fin = Carbon::parse($datos['fecha_fin'] ?? now()->toDateString())->endOfDay();
        abort_if($fin->lt($inicio) || $inicio->diffInDays($fin) > 366, 422, 'Selecciona un período válido de hasta un año.');
        $base = Factura::query()->where(function ($query) {
            $query->whereNull('estado')->orWhere('estado', '!=', 'Anulado');
        });
        if (! $request->user()->isAdministrador()) {
            $base->where('sucursal_id', $request->user()->sucursal_id ?? 0);
        }
        $ventas = (clone $base)->whereBetween('fecha', [$inicio, $fin]);
        $resumen = (clone $ventas)->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(AVG(total), 0) as promedio')->first();
        $ventasHoy = (clone $base)->whereBetween('fecha', [now()->startOfDay(), now()->endOfDay()])->sum('total');
        $diarias = (clone $ventas)->selectRaw('DATE(fecha) as dia, SUM(total) as total')->groupByRaw('DATE(fecha)')->pluck('total', 'dia');
        $tendencia = [];
        for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay()) {
            $tendencia[] = ['fecha' => $dia->format('d/m/Y'), 'total' => (float) ($diarias[$dia->toDateString()] ?? 0)];
        }
        $estados = (clone $ventas)->selectRaw("COALESCE(estado_pago, 'SIN ESTADO') as nombre, SUM(total) as total")->groupBy('estado_pago')->get();
        $productos = Detalle::query()->whereIn('factura_id', (clone $ventas)->select('id'))
            ->selectRaw("COALESCE(nombre_producto, 'Sin nombre') as nombre, SUM(cantidad) as cantidad")
            ->groupBy('nombre_producto')->orderByDesc('cantidad')->limit(8)->get();
        $vendedores = (clone $ventas)->with('usuarioCreador:id,name')->select('usuario_creador_id')
            ->selectRaw('COUNT(*) as cantidad, SUM(total) as total')->groupBy('usuario_creador_id')->orderByDesc('total')->limit(5)->get();
        $recientes = (clone $ventas)->with('usuarioCreador:id,name')->orderByDesc('fecha')->orderByDesc('id')->limit(6)->get();

        return view('home.inicio', compact('inicio', 'fin', 'resumen', 'ventasHoy', 'tendencia', 'estados', 'productos', 'vendedores', 'recientes'));
    }
}
