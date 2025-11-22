<?php

namespace App\Http\Controllers;

use App\Models\rto;
use App\Models\Camion;
use App\Models\Proveedor;
use App\Models\Reclamo;
use App\Models\Observacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Dashboard extends Controller
{
    public function index()
    {
        $proveedores = Proveedor::where('estadoProveedor', '1')->get();

        // Métricas generales
        $totalRemitos = Rto::count();
        
        // Aquí definimos los remitos por estado
        // Nota: Estos cálculos son simulados, debes adaptarlos según tu lógica de negocio
        
        // Por ejemplo, podríamos considerar "en espera" a los remitos que tienen totalFinalRto NULL
        $remitosEnEspera = Rto::whereNull('totalFinalRto')->count();
        
        // Remitos con deuda podrían ser aquellos que tienen una diferencia entre valores teóricos y finales
        $remitosConDeuda = Rto::whereRaw('totalFinalRto > totalTempRto')->count();
        
        // Remitos pagados (esto es solo un ejemplo, ajusta según tu lógica de negocio)
        $remitosPagados = $totalRemitos - $remitosEnEspera - $remitosConDeuda;
        
        // Conteos adicionales
        $totalProveedores = Proveedor::where('estadoProveedor', '1')->count();
        $totalCamiones = Camion::count();
        $totalReclamos = Reclamo::where('estadoReclamoRto', 'pendiente')->count();
        $totalObservaciones = Observacion::count();

        // Remitos recientes (últimos 10)
        $remitosRecientes = rto::with('proveedor')
                            ->orderBy('fechaIngresoRto', 'desc')
                            ->limit(10)
                            ->get();

        // Datos para el gráfico de remitos por mes (últimos 6 meses)
        $remitosPorMes = Rto::select(DB::raw('MONTH(fechaIngresoRto) as mes'), 
                                  DB::raw('YEAR(fechaIngresoRto) as año'),
                                  DB::raw('COUNT(*) as total'))
                          ->whereRaw('fechaIngresoRto >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)')
                          ->groupBy('año', 'mes')
                          ->orderBy('año', 'asc')
                          ->orderBy('mes', 'asc')
                          ->get();

        // Formateamos los datos para el gráfico de remitos por mes
        $mesesLabels = [];
        $mesesData = [];
        
        foreach ($remitosPorMes as $registro) {
            $nombreMes = date('M', mktime(0, 0, 0, $registro->mes, 10));
            $mesesLabels[] = $nombreMes . ' ' . $registro->año;
            $mesesData[] = $registro->total;
        }

        // Top 5 proveedores con más remitos
        $topProveedores = rto::select('proveedores.nombreProveedor', DB::raw('COUNT(*) as total'))
                             ->join('proveedores', 'rto.proveedores_id', '=', 'proveedores.id')
                             ->groupBy('proveedores.nombreProveedor')
                             ->orderBy('total', 'desc')
                             ->limit(5)
                             ->get();
                             
        // Formateamos los datos para el gráfico de top proveedores
        $proveedoresLabels = $topProveedores->pluck('nombreProveedor')->toArray();
        $proveedoresData = $topProveedores->pluck('total')->toArray();

        return view('modules.dashboard.home', compact(
            'totalRemitos', 
            'remitosEnEspera',
            'remitosConDeuda',
            'remitosPagados',
            'totalProveedores', 
            'totalCamiones', 
            'totalReclamos',
            'totalObservaciones',
            'remitosRecientes',
            'mesesLabels',
            'mesesData',
            'proveedoresLabels',
            'proveedoresData',
            'proveedores'
        ));
    }
}