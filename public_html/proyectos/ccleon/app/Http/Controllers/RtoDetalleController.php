<?php

namespace App\Http\Controllers;

use App\Models\RtoDetalle;
use App\Models\ElementoRto;
use App\Models\Rto;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Response;

class RtoDetalleController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validar la entrada
        $validator = Validator::make($request->all(), [
            'rto_id' => 'required|exists:rto,id',
            'elementoRto_id' => 'required|exists:elementos_rto,id',
            'valorDolaresRtoTeorico' => 'nullable|numeric',
            'valorPesosRtoTeorico' => 'nullable|numeric',
            'TC_RtoTeorico' => 'nullable|numeric',
            'descripcionNuevoElemento' => 'nullable|string|max:255',
        ]);
    
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
    
        try {
            // Calcular el subtotal
            $subtotal = 0;
            if ($request->filled('valorPesosRtoTeorico') && $request->valorPesosRtoTeorico > 0) {
                $subtotal = $request->valorPesosRtoTeorico;
            } elseif ($request->filled('valorDolaresRtoTeorico') && $request->filled('TC_RtoTeorico') &&
                     $request->valorDolaresRtoTeorico > 0 && $request->TC_RtoTeorico > 0) {
                $subtotal = $request->valorDolaresRtoTeorico * $request->TC_RtoTeorico;
            }
    
            // Crear el nuevo detalle de RTO
            $rtoDetalle = RtoDetalle::create([
                'rto_id' => $request->rto_id,
                'elementoRto_id' => $request->elementoRto_id,
                'valorDolaresRtoTeorico' => $request->valorDolaresRtoTeorico ?? 0,
                'valorPesosRtoTeorico' => $request->valorPesosRtoTeorico ?? 0,
                'TC_RtoTeorico' => $request->TC_RtoTeorico ?? 0,
                'subTotalRtoTeorico' => $subtotal,
                'valorPesosRtoReal' => $request->valorPesosRtoTeorico ?? 0,
                'TC_RtoReal' => $request->TC_RtoTeorico ?? 0,
                'subTotalRtoReal' => $subtotal
            ]);
    
            // Actualizar el total en la tabla de RTO
            $this->actualizarTotalRto($request->rto_id);
    
            // Redireccionar al detalle del remito con mensaje de éxito
            return redirect()->route('remitos.edit', ['id' => $request->rto_id])
                ->with('success', 'Elemento agregado correctamente al remito');
        } catch (\Exception $e) {
            // Redireccionar con mensaje de error
            return back()->with('error', 'Error al agregar elemento: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza el total del RTO sumando todos los subtotales de los detalles.
     *
     * @param int $rtoId
     * @return void
     */
    private function actualizarTotalRto($rtoId)
    {
        $totalTeorico = RtoDetalle::where('rto_id', $rtoId)
            ->sum('subTotalRtoTeorico');
            
        $totalFinal = RtoDetalle::where('rto_id', $rtoId)
            ->sum('subTotalRtoReal');

        Rto::where('id', $rtoId)
            ->update([
                'totalTempRto' => $totalTeorico,
                'totalFinalRto' => $totalFinal
            ]);
    }

    public function actualizarCampo(Request $request)
    {
        try {
            $id = $request->input('id');
            $field = $request->input('field');
            $value = $request->input('value');
    
            // Validar datos
            if (!in_array($field, ['valorDolaresRtoTeorico', 'valorPesosRtoTeorico', 'TC_RtoTeorico', 'totalFinalRto', 'valorPesosRtoReal','valorDolaresRtoReal', 'TC_RtoReal'])) {
                return response()->json(['success' => false, 'message' => 'Campo no válido']);
            }
    
            if ($field === 'totalFinalRto') {
                // Si es el total final, actualizar directamente en la tabla Rto
                $rto = Rto::findOrFail($id);
                $rto->totalFinalRto = $value;
                $rto->save();
    
                $totalTeorico = $rto->totalTempRto;
                $totalFinal = $value;
                $diferencia = $totalTeorico - $totalFinal;
    
                return response()->json([
                    'success' => true,
                    'totalTeorico' => $totalTeorico,
                    'totalFinal' => $rto->totalFinalRto,
                    'diferencia' => $diferencia
                ]);
            }
    
            // Si no es el total final, continuar con la lógica existente para otros campos
            $detalle = RtoDetalle::findOrFail($id);
    
            // Actualizar el campo
            $detalle->$field = $value ?? 0;
    
            // IMPORTANTE: Mover este bloque de código aquí antes de los cálculos
            if (in_array($field, ['valorDolaresRtoTeorico', 'valorPesosRtoTeorico'])) {
                // Si se actualiza el valor en dólares o pesos, recalcular el subtotal final también
                if ($field === 'valorDolaresRtoTeorico' && $detalle->TC_RtoReal > 0) {
                    $detalle->subTotalRtoReal = $value * $detalle->TC_RtoReal;
                } elseif ($field === 'valorPesosRtoTeorico') {
                    $detalle->subTotalRtoReal = $value;
                }
            }
    
            // Calcular el subtotal teórico
            if ($detalle->valorPesosRtoTeorico > 0) {
                $detalle->subTotalRtoTeorico = $detalle->valorPesosRtoTeorico;
            } elseif ($detalle->valorDolaresRtoTeorico > 0 && $detalle->TC_RtoTeorico > 0) {
                $detalle->subTotalRtoTeorico = $detalle->valorDolaresRtoTeorico * $detalle->TC_RtoTeorico;
            } else {
                $detalle->subTotalRtoTeorico = 0;
            }
    
            // Calcular el subtotal final
            if ($field === 'TC_RtoReal' && $detalle->valorDolaresRtoTeorico > 0) {
                // Si estamos modificando el TC_RtoReal, calculamos el subtotal con ese valor
                $detalle->subTotalRtoReal = $detalle->valorDolaresRtoTeorico * $detalle->TC_RtoReal;
            } else {
                // En otros casos, mantenemos la lógica anterior
                if ($detalle->valorPesosRtoReal > 0) {
                    $detalle->subTotalRtoReal = $detalle->valorPesosRtoReal;
                } elseif ($detalle->valorDolaresRtoTeorico > 0 && $detalle->TC_RtoReal > 0) {
                    $detalle->subTotalRtoReal = $detalle->valorDolaresRtoTeorico * $detalle->TC_RtoReal;
                } else {
                    $detalle->subTotalRtoReal = 0;
                }
            }
    
            $detalle->save();
    
            // Recalcular totales
            $rtoId = $detalle->rto_id;
            $totalTeorico = RtoDetalle::where('rto_id', $rtoId)->sum('subTotalRtoTeorico');
            $totalFinal = RtoDetalle::where('rto_id', $rtoId)->sum('subTotalRtoReal');
    
            $diferencia = $totalFinal - $totalTeorico;
    
            // Actualizar el remito principal
            $rto = Rto::find($rtoId);
            $rto->totalTempRto = $totalTeorico;
            $rto->totalFinalRto = $totalFinal;
            $rto->save();
    
            return response()->json([
                'success' => true,
                'subtotal' => $detalle->subTotalRtoTeorico,
                'subtotalFinal' => $detalle->subTotalRtoReal,
                'totalTeorico' => $totalTeorico,
                'totalFinal' => $totalFinal,
                'diferencia' => $diferencia,
                'isRealField' => in_array($field, ['TC_RtoReal', 'valorDolaresRtoReal'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function obtenerValor($id, $field)
    {
        try {
            $detalle = RtoDetalle::findOrFail($id);

            if (!in_array($field, ['valorDolaresRtoTeorico', 'valorPesosRtoTeorico', 'TC_RtoTeorico', 'subTotalRtoTeorico', 'valorPesosRtoReal','TC_RtoReal', 'subTotalRtoReal'])) {
                return response()->json(['success' => false, 'message' => 'Campo no válido']);
            }

            return response()->json([
                'success' => true,
                'value' => $detalle->$field
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        try {
            // Encuentra el detalle a eliminar
            $detalle = RtoDetalle::findOrFail($id);
            
            // Guarda el ID del remito para actualizar totales después
            $rtoId = $detalle->rto_id;
            
            // Elimina el detalle
            $detalle->delete();
            
            // Actualiza los totales
            $this->actualizarTotalRto($rtoId);
            
            // Responde con JSON
            return response()->json([
                'success' => true,
                'message' => 'Elemento eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            // Registra el error para diagnóstico
            // \Log::error('Error al eliminar detalle: ' . $e->getMessage());
            
            // Retorna respuesta JSON con error
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}