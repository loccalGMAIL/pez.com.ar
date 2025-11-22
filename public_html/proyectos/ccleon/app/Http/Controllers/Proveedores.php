<?php

namespace App\Http\Controllers;

use App\Models\Camion;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class Proveedores extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $titulo = 'Proveedores';
        $items = Proveedor::all();
        return view('modules.proveedores.index', compact('titulo', 'items'));
    }

    public function indexCamiones(){
        $titulo = 'Camiones';
        $items = Camion::with('proveedor')->get();
        $proveedores = Proveedor::all();
        return view('modules.proveedores.camiones.index', compact('titulo', 'items', 'proveedores'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $titulo = 'Nuevo Proveedor';
        return view('modules.proveedores.create', compact('titulo'));
    }

    public function createCamiones(){
        $titulo = 'Nuevo Camion';
        $proveedores = Proveedor::all();
        return view('modules.proveedores.camiones.create', compact('titulo', 'proveedores'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $proveedor = new Proveedor();
        $proveedor->nombreProveedor = $request->nombreProveedor;
        $proveedor->dniProveedor = $request->dniProveedor;
        $proveedor->razonSocialProveedor = $request->razonSocialProveedor;
        $proveedor->cuitProveedor = $request->cuitProveedor;
        $proveedor->telefonoProveedor = $request->telefonoProveedor;
        $proveedor->mailProveedor = $request->mailProveedor;
        $proveedor->direccionProveedor = $request->direccionProveedor;
        $proveedor->estadoProveedor = '1'; // Asegura que se establece como activo
        $proveedor->save();
        
        // Comprobar si la solicitud es AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Proveedor agregado correctamente',
                'proveedor' => $proveedor
            ]);
        }
        
        // Para solicitudes normales, redirigir
        return redirect()->route('proveedores');
    }

    public function storeCamiones(Request $request){
        $camion = new Camion();
        $camion->patente = $request->patente;
        $camion->proveedores_id = $request->proveedor_id;
        $camion->save();
        return redirect()->route('proveedores.camiones');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $titulo = 'Editar Proveedor';
        $item = Proveedor::find($id);
        return view('modules.proveedores.edit', compact('titulo', 'item'));
    }
    
    public function editCamiones(string $id){
        $titulo = 'Editar Camion';
        $item = Camion::find($id);
        $proveedores = Proveedor::all();
        return view('modules.proveedores.camiones.edit', compact('titulo', 'item', 'proveedores'));
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $proveedor = Proveedor::find($id);
        $proveedor->nombreProveedor = $request->nombreProveedor;
        $proveedor->dniProveedor = $request->dniProveedor;
        $proveedor->razonSocialProveedor = $request->razonSocialProveedor;
        $proveedor->cuitProveedor = $request->cuitProveedor;
        $proveedor->telefonoProveedor = $request->telefonoProveedor;
        $proveedor->mailProveedor = $request->mailProveedor;
        $proveedor->direccionProveedor = $request->direccionProveedor;
        $proveedor->save();
        return redirect()->route('proveedores');
    }

    public function updateCamiones(Request $request, string $id){
        $camion = Camion::find($id);
        $camion->patente = $request->patente;
        $camion->proveedores_id = $request->proveedor_id;
        $camion->save();
        return redirect()->route('proveedores.camiones');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getCamiones($id)
    {
        
        $camiones = Camion::where('proveedores_id', $id)->get();
        
        return response()->json($camiones);
    }
}
