<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->integer('usuario_creador_id')->nullable();
            $table->integer('sucursal_id');
            $table->dateTime('fecha');
            $table->decimal('total', 12, 2);
            $table->string('estado')->nullable();
            $table->string('estado_pago')->nullable();
            $table->softDeletes();
        });
        Schema::create('detalles', function (Blueprint $table) {
            $table->id();
            $table->integer('factura_id');
            $table->string('nombre_producto');
            $table->decimal('cantidad', 12, 2);
            $table->softDeletes();
        });
    }

    public function test_dashboard_filters_branch_dates_and_cancelled_or_deleted_sales(): void
    {
        foreach ([[1, 100, null, null], [2, 200, null, null], [1, 500, 'Anulado', null], [1, 800, null, now()]] as [$branch, $total, $state, $deleted]) {
            DB::table('facturas')->insert(['sucursal_id' => $branch, 'fecha' => now(), 'total' => $total, 'estado' => $state, 'deleted_at' => $deleted]);
        }
        DB::table('facturas')->insert(['sucursal_id' => 1, 'fecha' => now()->subYear(), 'total' => 999]);
        DB::table('detalles')->insert(['factura_id' => 1, 'nombre_producto' => 'Aceite', 'cantidad' => 3]);
        DB::table('detalles')->insert(['factura_id' => 3, 'nombre_producto' => 'Anulado', 'cantidad' => 100]);
        $user = new User;
        $user->id = 10;
        $user->rol_id = 2;
        $user->sucursal_id = 1;
        $response = $this->actingAs($user)->get('/home');
        $response->assertOk()->assertViewHas('resumen', fn ($resumen) => (float) $resumen->total === 100.0 && $resumen->cantidad === 1)
            ->assertViewHas('productos', fn ($productos) => $productos->count() === 1 && $productos->first()->nombre === 'Aceite');
        $user->rol_id = 1;
        $this->get('/home')->assertOk()->assertViewHas('resumen', fn ($resumen) => (float) $resumen->total === 300.0);
    }

    public function test_empty_period_and_invalid_dates_are_handled(): void
    {
        $user = new User;
        $user->id = 10;
        $user->rol_id = 1;
        $this->actingAs($user)->get('/home')->assertOk()->assertSee('No hay ventas para las fechas seleccionadas.');
        $this->get('/home?fecha_inicio=2026-11-10&fecha_fin=2026-11-01')->assertSessionHasErrors('fecha_fin');
        $this->get('/home?fecha_inicio=2020-01-01&fecha_fin=2026-11-01')->assertStatus(422);
    }
}
