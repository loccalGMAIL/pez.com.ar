@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')

  <main id="main" class="main">

    <div class="pagetitle">
    <h1>Remitos</h1>

    </div><!-- End Page Title -->

    <section class="section">
    <div class="row">
      <div class="col-lg-12">

      <div class="card">
        <div class="card-body">
        <h5 class="card-title">Listado de Remitos</h5>
        <p class="card-text">En esta sección podrá administrar los remitos de los envios.</p>

        <a href="#" class="btn btn-primary mt-3 mb-3" data-bs-toggle="modal" data-bs-target="#agregarRemitoModal">
          <i class="fa-solid fa-circle-plus"></i> Agregar nuevo remito
        </a>
        <!-- Table with stripped rows -->
        <table class="table datatable">
          <thead>
          <tr>
            <th class="text-center">Nro Remito</th>
            <th class="text-center">Proveedor</th>
            <th class="text-center">Nro Factura</th>
            <th class="text-center">Observ.</th>
            <th class="text-center">Reclamos</th>
            <th class="text-center">Ingreso</th>
            <th class="text-center">Actualiación</th>
            <th class="text-center">Estado</th>
            <th class="text-center">Acciones</th>
          </tr>
          </thead>
          <tbody>
          @foreach ($items as $item)
        <tr class="text-center">
        <td>
        {{ str_pad($item->proveedores_id, 3, '0', STR_PAD_LEFT) }}-{{ str_pad($item->camion, 3, '0', STR_PAD_LEFT) }}-{{ str_pad($item->id, 6, '0', STR_PAD_LEFT) }}
        </td>
        <td>{{ $item->proveedor->razonSocialProveedor ?? 'Sin proveedor' }}</td>
        <td>{{$item->nroFacturaRto}}</td>
        <td>{{ $item->observaciones_count ?? 0 }}</td>
        <td>{{ $item->reclamos_count ?? 0 }}</td>
        <td>{{ \Carbon\Carbon::parse($item->fechaIngresoRto)->format('d/m/Y') }}</td>
        <td>{{ \Carbon\Carbon::parse($item->updated_at)->format('d/m/Y') }}</td>
        <td>
          @if ($item->estado == 'Espera')
            <span class="badge bg-info">Espera</span>
          @elseif ($item->estado == 'Deuda')
            <span class="badge bg-danger">Deuda</span>
          @elseif ($item->estado == 'Pagado')
            <span class="badge bg-success">Pagado</span>
          @else
            <span class="badge bg-danger">Anulado</span>
          @endif
        <td class="d-flex justify-content-center gap-2">
        <a href="{{ route('remitos.edit', $item->id) }}" class="badge bg-success" title="Editar">
          <i class="fa-solid fa-pen-to-square"></i>
        </a>
        <a href="{{ route('observaciones.show', $item->id) }}" class="badge bg-secondary"
          title="Ver Observaciones">
          <i class="fa-solid fa-bullseye"></i>
        </a>
        <a href="{{ route('reclamos.show', $item->id) }}" class="badge bg-danger" title="Ver Reclamos">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </a>
        </td>

        </tr>
      @endforeach
        </table>
        <!-- End Table with stripped rows -->

        </div>
      </div>

      </div>
      <!-- Modal remito -->
      @include('modules.rto.modalNvoRto')
      <!-- End Table with stripped rows -->
    </div>



  </main>



@endsection