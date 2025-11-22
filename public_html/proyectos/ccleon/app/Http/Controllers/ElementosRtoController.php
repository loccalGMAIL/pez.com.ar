<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ElementoRto;
use App\Models\rto;

class ElementosRtoController extends Controller
{
    public function storeElementoRto(Request $request)
    {
        $request->validate([
            'descripcionElementoRto' => 'required|string|max:255'
        ]);
        
        $elemento = new ElementoRto();
        $elemento->descripcionElementoRto = $request->descripcionElementoRto;
        $elemento->save();
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Elemento creado correctamente',
                'elemento' => $elemento
            ]);
        }
        
        return redirect()->back()->with('success', 'Elemento creado correctamente');
    }

//     public function agregarElementoRto(Request $request)
// {
//     // Validar los datos del formulario
//     $request->validate([
//         'rto_id' => 'required|exists:rto,id',
//         'elementoRto_id' => 'required|exists:elementos_rto,id',
//         'valorDolaresRtoTeorico' => 'nullable|numeric',
//         'valorPesosRtoTeorico' => 'nullable|numeric',
//         'TC_RtoTeorico' => 'nullable|numeric',
//     ]);
    
//     // Crear nuevo RTO teórico
//     $rtoTeorico = new RtoTeorico();
//     $rtoTeorico->rto_id = $request->rto_id;
//     $rtoTeorico->elementoRto_id = $request->elementoRto_id;
    
//     // Asignar valores, permitiendo nulos
//     $rtoTeorico->valorDolaresRtoTeorico = $request->valorDolaresRtoTeorico;
//     $rtoTeorico->valorPesosRtoTeorico = $request->valorPesosRtoTeorico;
//     $rtoTeorico->TC_RtoTeorico = $request->TC_RtoTeorico;
    
//     // Calcular el subtotal según los datos disponibles
//     if ($request->filled('valorPesosRtoTeorico')) {
//         // Si hay valor en pesos, usar ese directamente
//         $rtoTeorico->subTotalRtoTeorico = $request->valorPesosRtoTeorico;
//     } else if ($request->filled('valorDolaresRtoTeorico') && $request->filled('TC_RtoTeorico')) {
//         // Si hay valor en dólares y tipo de cambio, calcular
//         $rtoTeorico->subTotalRtoTeorico = $request->valorDolaresRtoTeorico * $request->TC_RtoTeorico;
//     } else {
//         // Si no hay suficiente información, establecer a 0
//         $rtoTeorico->subTotalRtoTeorico = 0;
//     }
    
//     // Guardar en la base de datos
//     $rtoTeorico->save();
    
//     // Actualizar el total temporal del RTO
//     $this->actualizarTotalRto($request->rto_id);
    
//     // Responder según el tipo de solicitud
//     if ($request->ajax() || $request->wantsJson()) {
//         return response()->json([
//             'success' => true,
//             'message' => 'Elemento agregado correctamente',
//             'rtoteorico' => $rtoTeorico
//         ]);
//     }
    
//     // Para solicitudes no-AJAX, redirigir con mensaje de éxito
//     return redirect()->route('remitos.edit', $request->rto_id)
//                      ->with('success', 'Elemento agregado correctamente');
// }

/**
 * Método auxiliar para actualizar el total del RTO
  */
// private function actualizarTotalRto($rtoId)
// {
//     $rto = Rto::findOrFail($rtoId);
    
//     // Sumar todos los subtotales de los elementos teóricos
//     $totalTemp = RtoTeorico::where('rto_id', $rtoId)->sum('subTotalRtoTeorico');
    
//     $rto->totalTempRto = $totalTemp;
//     $rto->save();
    
//     return $totalTemp;
// }
// }

}