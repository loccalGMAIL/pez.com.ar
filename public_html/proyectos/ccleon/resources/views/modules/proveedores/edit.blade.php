@extends('layouts.main')
@section('titulo', $titulo)
@section('contenido')
<main id="main" class="main">
    <div class="pagetitle">
      <h1>Proveedores</h1>
    </div><!-- End Page Title -->
    
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Agregar nuevo proveedor</h5>
              
              <form action="{{ route('proveedores.update', $item->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row g-3 mb-4">
                  <!-- Primera columna -->
                  <div class="col-md-6">
                    <div class="form-group mb-3">
                      <label for="nombreProveedor" class="form-label">Nombre del proveedor</label>
                      <input type="text" class="form-control" name="nombreProveedor" id="nombreProveedor" value="{{ $item->nombreProveedor }}" required>
                    </div>
                    
                    <div class="form-group mb-3">
                      <label for="dniProveedor" class="form-label">DNI</label>
                      <input type="text" class="form-control" name="dniProveedor" id="dniProveedor" value="{{ $item->dniProveedor }}">
                    </div>
                    
                    <div class="form-group mb-3">
                      <label for="razonSocialProveedor" class="form-label">Razón Social</label>
                      <input type="text" class="form-control" name="razonSocialProveedor" id="razonSocialProveedor" value="{{$item->razonSocialProveedor}}" required>
                    </div>
                    
                    <div class="form-group mb-3">
                      <label for="cuitProveedor" class="form-label">CUIT</label>
                      <input type="text" class="form-control" name="cuitProveedor" id="cuitProveedor" value="{{$item->cuitProveedor}}" required>
                    </div>
                  </div>
                  
                  <!-- Segunda columna -->
                  <div class="col-md-6">
                    <div class="form-group mb-3">
                      <label for="direccionProveedor" class="form-label">Dirección</label>
                      <input type="text" class="form-control" name="direccionProveedor" id="direccionProveedor" value="{{$item->direccionProveedor}}">
                    </div>
                    
                    <div class="form-group mb-3">
                      <label for="telefonoProveedor" class="form-label">Teléfono</label>
                      <input type="text" class="form-control" name="telefonoProveedor" id="telefonoProveedor" value="{{$item->telefonoProveedor}}">
                    </div>
                    
                    <div class="form-group mb-3">
                      <label for="emailProveedor" class="form-label">Email</label>
                      <input type="email" class="form-control" name="emailProveedor" id="emailProveedor" value="{{$item->mailProveedor}}">
                    </div>
                    
                    <!-- Espacio vacío para alinear con la primera columna -->
                    <div class="form-group mb-3">
                      <!-- Espacio intencional para alineación -->
                    </div>
                  </div>
                </div>
                
                <div class="row mt-4">
                  <div class="col-12">
                    <div class="d-flex gap-2">
                      <button type="submit" class="btn btn-primary">Guardar</button>
                      <a href="{{ route('proveedores') }}" class="btn btn-info">Cancelar</a>
                    </div>
                  </div>
                </div>
              </form>
              
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
@endsection