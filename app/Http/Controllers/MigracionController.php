<?php

namespace App\Http\Controllers;

use App\Models\CategoriaProducto;
use App\Models\Movimiento;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MigracionController extends Controller
{
    public function migrarPorductos(Request $request){

        $ruta = public_path('assets/migration/nuevo.xlsx');
        $spreadsheet = null;

        $totalProductos = 0;
        $totalCategorias = 0;
        $totalMovimientos = 0;
        $totalExistentes = 0;
        $numeroFila = 0;

        try {

            if (!file_exists($ruta)) {
                throw new \Exception("No existe el archivo Excel: {$ruta}");
            }

            $usuario = Auth::user();

            if (!$usuario) {
                throw new \Exception("No existe un usuario autenticado.");
            }

            if (!$usuario->sucursal_id) {
                throw new \Exception("El usuario no tiene una sucursal asignada.");
            }

            $spreadsheet = IOFactory::load($ruta);

            // PRIMERA HOJA DEL EXCEL
            $hoja = $spreadsheet->getSheet(0);

            // $filas = $hoja->toArray(null, true, true, true);
            $filas = $hoja->toArray(null, true, false, true);

            // INICIAMOS TRANSACCIÓN
            DB::beginTransaction();

            foreach ($filas as $numeroFila => $fila) {

                // Saltar encabezados
                if ($numeroFila == 1) {
                    continue;
                }

                // Leer cada columna del Excel
                $codigoExcel        = trim((string) ($fila['A'] ?? ''));
                $categoriaExcel     = trim((string) ($fila['B'] ?? ''));
                $productoExcel      = trim((string) ($fila['C'] ?? ''));
                $descripcionExcel   = trim((string) ($fila['D'] ?? ''));
                $stockInicialExcel  = $this->convertirNumeroExcel($fila['E'] ?? 0);
                $precioCompraExcel  = $this->convertirNumeroExcel($fila['F'] ?? 0);
                $entradasExcel      = $this->convertirNumeroExcel($fila['G'] ?? 0);
                $salidasExcel       = $this->convertirNumeroExcel($fila['H'] ?? 0);
                $stockActualExcel   = $this->convertirNumeroExcel($fila['I'] ?? 0);
                $precioTotalExcel   = $this->convertirNumeroExcel($fila['J'] ?? 0);
                $precioVentaExcel   = $this->convertirNumeroExcel($fila['K'] ?? 0);

                $categoriaProducto = CategoriaProducto::where('nombre', $categoriaExcel)->first();

                // PREGUNTANMOS SI LA CATEGORIA EXISTE O NO
                if (is_null($categoriaProducto)) {
                    $categoriaProducto = new CategoriaProducto();
                    $categoriaProducto->usuario_creador_id = $usuario->id;
                    $categoriaProducto->nombre = $categoriaExcel;
                    $categoriaProducto->save();
                    $totalCategorias++;
                }

                // UNA VES SACANDO LA CATEGORIA CREAMOS LE PRODUCTO
                $producto = Producto::where('codigo', $codigoExcel)
                    ->where('nombre', $productoExcel)
                    ->first();
                if (is_null($producto)) {
                    $producto = new Producto();
                    $producto->usuario_creador_id = $usuario->id;
                    $producto->categoria_producto_id = $categoriaProducto->id;
                    $producto->nombre = $productoExcel;
                    $producto->codigo = $codigoExcel;
                    $producto->descripcion = $descripcionExcel;
                    $producto->minimo_stock = $stockInicialExcel;
                    $producto->precio_compra = $precioCompraExcel;
                    $producto->precio_venta = $precioVentaExcel;
                    $producto->control_stock = true;
                    $producto->tipo = 'PRODUCTO';
                    $producto->save();
                    $totalProductos++;
                }
                // else{
                //     $totalExistentes++;
                //     continue;
                // }

                // AHORA PREGUNTAMOS EL STOCK
                if ($stockActualExcel > 0) {
                    $movimiento = new Movimiento();
                    $movimiento->usuario_creador_id = $usuario->id;
                    $movimiento->producto_id = $producto->id;
                    $movimiento->sucursal_id = $usuario->sucursal_id;
                    $movimiento->ingreso = $stockActualExcel;
                    $movimiento->salida = 0;
                    $movimiento->fecha = date('Y-m-d H:i:s');
                    $movimiento->descripcion = "INGRESO POR MIGRACION";
                    $movimiento->precio_compra = $producto->precio_compra;
                    $movimiento->precio_venta = $producto->precio_venta;
                    $movimiento->save();
                }
            }

            // CONFIRMAR TODOS LOS REGISTROS
            DB::commit();

            return response()->json([
                'success' => true,
                'mensaje' => 'Migración completada correctamente.',
                'categorias_creadas' => $totalCategorias,
                'productos_creados' => $totalProductos,
                'productos_existentes' => $totalExistentes,
                'movimientos_creados' => $totalMovimientos
            ]);
        } catch (\Throwable $e) {
            // REVERTIR TODOS LOS CAMBIOS
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('ERROR MIGRACIÓN PRODUCTOS', [
                'fila' => $numeroFila,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'mensaje' => 'Error durante la migración. Se revirtieron los cambios de la transacción.',
                'fila_excel' => $numeroFila,
                'error' => $e->getMessage()
            ], 500);
        } finally {

            if ($spreadsheet !== null) {
                $spreadsheet->disconnectWorksheets();
            }
        }
    }

    private function convertirNumeroExcel($valor)
    {
        if ($valor === null || trim((string) $valor) === '') {
            return 0;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $valor = preg_replace('/[\s\p{Z}\p{C}]+/u', '', (string) $valor);
        $valor = preg_replace('/Bs\.?/iu', '', $valor);

        if ($valor === '' || preg_match('/^-+$/', $valor)) {
            return 0;
        }

        $valor = preg_replace('/[^\d,.\-]/u', '', $valor);

        if (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        if (!is_numeric($valor)) {
            throw new \Exception("Número inválido en Excel: {$valor}");
        }

        return (float) $valor;
    }

}
