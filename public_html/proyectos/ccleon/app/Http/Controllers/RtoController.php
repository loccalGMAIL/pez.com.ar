<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Proveedor;
use App\Models\rto;
use App\Models\Camion;
use App\Models\ElementoRto;
use App\Models\RtoDetalle;

class RtoController extends Controller
{
    public function index()
    {
        $titulo = 'Remitos';
        $items = rto::with(['proveedor'])
            ->withCount('observaciones', 'reclamos')
            ->orderBy('fechaIngresoRto', 'desc')
            ->get();
        $proveedores = Proveedor::where('estadoProveedor', '1')->get();
        return view('modules.rto.index', compact('titulo', 'items', 'proveedores'));
    }

    public function create()
    {
        $titulo = 'Crear Remito';
        $proveedores = Proveedor::where('estadoProveedor', '1')
            ->orderBy('razonSocialProveedor')
            ->get();
        return view('modules.rto.create', compact('titulo', 'proveedores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fechaIngresoRto' => 'required|date',
            'nroFacturaRto' => 'required|string|max:50',
            'idProveedor' => 'required|exists:proveedores,id',
        ]);

        // Buscar el camión para este proveedor
        $camion = Camion::where('proveedores_id', $request->idProveedor)->first();

        // Si no existe un camión para este proveedor, creamos uno con contador inicial
        if (!$camion) {
            $camion = new Camion();
            $camion->contador = 1;
            $camion->proveedores_id = $request->idProveedor;
            $camion->save();
        }

        // Crear el remito usando el contador del camión como número de camión
        $remito = new Rto();
        $remito->fechaIngresoRto = $request->fechaIngresoRto;
        $remito->nroFacturaRto = $request->nroFacturaRto;
        $remito->proveedores_id = $request->idProveedor;
        $remito->camion = $camion->contador; // Usar el contador como número de camión

        // Incrementar el contador para el próximo remito
        $camion->contador += 1;
        $camion->save();

        // Guardar el remito
        $remito->save();

        return redirect()->route('remitos.edit', $remito->id)
        ->with('success', 'Remito creado correctamente y redirigido a la edición.');
    }

    public function edit($id)
    {
        // Obtener el remito
        $items = Rto::with(['proveedor'])->findOrFail($id);

        // Cargar los detalles del remito
        $detalles = RtoDetalle::with('elemento')
            ->where('rto_id', $id)
            ->get();

        $elementosRto = ElementoRto::all();

        // Obtener todos los proveedores para el selector
        $proveedores = Proveedor::where('estadoProveedor', '1')
            ->orderBy('razonSocialProveedor')
            ->get();

        return view('modules.rto.editar', [
            'titulo' => 'Editar Remito',
            'items' => $items,
            'detalles' => $detalles,
            'proveedores' => $proveedores,
            'elementosRto' => $elementosRto
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'fechaIngresoRto' => 'required|date',
            'nroFacturaRto' => 'required|string|max:50',
            'idProveedor' => 'required|exists:proveedores,id',
        ]);

        $remito = Rto::findOrFail($id);

        // Actualizar datos básicos del remito
        $remito->fechaIngresoRto = $request->fechaIngresoRto;
        $remito->nroFacturaRto = $request->nroFacturaRto;

        // Si cambia el proveedor, manejamos la lógica del camión
        if ($remito->proveedores_id != $request->idProveedor) {
            $remito->proveedores_id = $request->idProveedor;

            // Buscar el camión para el nuevo proveedor
            $camion = Camion::where('proveedores_id', $request->idProveedor)->first();

            // Si no existe un camión para este proveedor, creamos uno
            if (!$camion) {
                $camion = new Camion();
                $camion->contador = 1;
                $camion->proveedores_id = $request->idProveedor;
                $camion->save();
            }

            // Usar el contador actual como número de camión
            $remito->camion = $camion->contador;

            // Incrementar el contador para el próximo remito
            $camion->contador += 1;
            $camion->save();
        }

        $remito->save();

        return redirect()->route('remitos.edit', $id)
            ->with('success', 'Remito actualizado correctamente');
    }

    public function destroy($id)
    {
        $remito = Rto::findOrFail($id);
        $remito->delete();

        return redirect()->route('remitos')
            ->with('success', 'Remito eliminado correctamente');
    }
}