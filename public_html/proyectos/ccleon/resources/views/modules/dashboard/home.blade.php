@extends('layouts.main')

@section('contenido')
<main id="main" class="main">
    <div class="pagetitle">
      <h1>Dashboard</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
          <li class="breadcrumb-item active">Dashboard</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <!-- Botones de acción rápida -->
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Acciones Rápidas</h5>
              <div class="d-flex flex-wrap gap-4">
                <!-- Botón Nuevo Remito -->
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#agregarRemitoModal">
                  <i class="fa-solid fa-circle-plus"></i> Agregar nuevo remito
                </a>
                
                <!-- Botón Ver Remitos Pendientes -->
                <a href="#" class="btn btn-warning">
                  <i class="fa-solid fa-clipboard-list"></i> Remitos Pendientes
                </a>
                
                <!-- Botón Ver Reclamos -->
                <a href="{{ route('reclamos') }}" class="btn btn-danger">
                  <i class="fa-solid fa-triangle-exclamation"></i> Ver Reclamos
                </a>
                
                <!-- Botón Ver Observaciones -->
                <a href="{{ route('reclamos') }}" class="btn btn-success">
                  <i class="fa-solid fa-eye"></i> Ver Observaciones
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Tarjetas informativas -->
      <div class="row">
        <!-- Tarjeta Total Remitos -->
        <div class="col-md-6 col-lg-3">
          <div class="card info-card sales-card">
            <div class="card-body">
              <h5 class="card-title">Total Remitos</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                  <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div class="ps-3">
                  <h6>{{ $totalRemitos ?? 0 }}</h6>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tarjeta Remitos en Espera -->
        <div class="col-md-6 col-lg-3">
          <div class="card info-card revenue-card">
            <div class="card-body">
              <h5 class="card-title">Remitos en Espera</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                  <i class="fa-solid fa-clock"></i>
                </div>
                <div class="ps-3">
                  <h6>{{ $remitosEnEspera ?? 0 }}</h6>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tarjeta Remitos con Deuda -->
        <div class="col-md-6 col-lg-3">
          <div class="card info-card customers-card">
            <div class="card-body">
              <h5 class="card-title">Remitos con Deuda</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                  <i class="fa-solid fa-money-bill-wave"></i>
                </div>
                <div class="ps-3">
                  <h6>{{ $remitosConDeuda ?? 0 }}</h6>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tarjeta Remitos Pagados -->
        <div class="col-md-6 col-lg-3">
          <div class="card info-card customers-card">
            <div class="filter">
              <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
              <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                <li class="dropdown-header text-start">
                  <h6>Filtrar</h6>
                </li>
                <li><a class="dropdown-item" href="#">Hoy</a></li>
                <li><a class="dropdown-item" href="#">Este Mes</a></li>
                <li><a class="dropdown-item" href="#">Este Año</a></li>
              </ul>
            </div>
            <div class="card-body">
              <h5 class="card-title">Remitos Pagados</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                  <i class="fa-solid fa-check-circle"></i>
                </div>
                <div class="ps-3">
                  <h6>{{ $remitosPagados ?? 0 }}</h6>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Gráficos -->
      <div class="row">
        <!-- Gráfico: Remitos por Mes -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Remitos por Mes</h5>
              <div id="remitosPorMesChart" style="min-height: 365px;">
                <canvas id="remitosMensualChart"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Gráfico: Top Proveedores -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Top 5 Proveedores</h5>
              <div id="topProveedoresChart" style="min-height: 365px;">
                <canvas id="proveedoresChart"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Remitos Recientes -->
      <div class="row">
        <div class="col-12">
          <div class="card recent-sales overflow-auto">
            <div class="card-body">
              <h5 class="card-title">Remitos Recientes</h5>
              <table class="table table-borderless datatable">
                <thead>
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">Proveedor</th>
                    <th scope="col">Factura</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Total</th>
                    <th scope="col">Estado</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($remitosRecientes ?? [] as $remito)
                  <tr>
                    <th scope="row"><a href="#">{{ $remito->id }}</a></th>
                    <td>{{ $remito->proveedor->nombreProveedor }}</td>
                    <td>{{ $remito->nroFacturaRto }}</td>
                    <td>{{ $remito->fechaIngresoRto }}</td>
                    <td>${{ number_format($remito->totalFinalRto, 2) }}</td>
                    <td>
                      @if($remito->totalFinalRto === null)
                        <span class="badge bg-warning">En espera</span>
                      @elseif($remito->totalFinalRto > $remito->totalTempRto)
                        <span class="badge bg-danger">Con deuda</span>
                      @else
                        <span class="badge bg-success">Pagado</span>
                      @endif
                    </td>
                  </tr>
                  @empty
                  <tr>
                    <td colspan="6" class="text-center">No hay remitos recientes</td>
                  </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Modal remito -->
      @include('modules.rto.modalNvoRto')
      <!-- End Table with stripped rows -->
    </section>
  </main>
@endsection

@section('scripts')
<script>
  document.addEventListener("DOMContentLoaded", function() {
    // Chart.js scripts para los gráficos
    // Ejemplo para gráfico de remitos por mes
    const remitosMensualCtx = document.getElementById('remitosMensualChart');
    if (remitosMensualCtx) {
      new Chart(remitosMensualCtx, {
        type: 'line',
        data: {
          labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
          datasets: [{
            label: 'Remitos',
            data: [65, 59, 80, 81, 56, 55, 40],
            fill: false,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
          }]
        },
        options: {
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }

    // Ejemplo para gráfico de top proveedores
    const proveedoresCtx = document.getElementById('proveedoresChart');
    if (proveedoresCtx) {
      new Chart(proveedoresCtx, {
        type: 'bar',
        data: {
          labels: ['Proveedor A', 'Proveedor B', 'Proveedor C', 'Proveedor D', 'Proveedor E'],
          datasets: [{
            label: 'Cantidad de Remitos',
            data: [12, 19, 3, 5, 2],
            backgroundColor: [
              'rgba(255, 99, 132, 0.2)',
              'rgba(54, 162, 235, 0.2)',
              'rgba(255, 206, 86, 0.2)',
              'rgba(75, 192, 192, 0.2)',
              'rgba(153, 102, 255, 0.2)'
            ],
            borderColor: [
              'rgba(255, 99, 132, 1)',
              'rgba(54, 162, 235, 1)',
              'rgba(255, 206, 86, 1)',
              'rgba(75, 192, 192, 1)',
              'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }
  });
</script>
@endsection