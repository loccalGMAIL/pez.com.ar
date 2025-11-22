@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')

  <main id="main" class="main">

    <div class="pagetitle">
    <h1>Usuarios</h1>

    </div><!-- End Page Title -->

    <section class="section">
    <div class="row">
      <div class="col-lg-12">

      <div class="card">
        <div class="card-body">
        <h5 class="card-title">Administrar Usuarios</h5>
        <p class="card-text">En esta sección podrá administrar las cuentas y roles de usuarios.</p>
        @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    @endif
        <a href="{{route('usuarios.create')}}" class="btn btn-primary mt-3 mb-3">
          <i class="fa-solid fa-circle-plus"> </i> Agregar nuevo Usuario
        </a>
        <!-- Table with stripped rows -->
        <table class="table datatable">
          <thead>
          <tr>
            <th class="text-center">Nombre</th>
            <th class="text-center">Email</th>
            <th class="text-center">Rol</th>
            <th class="text-center">Activo</th>
            <th class="text-center">Acciones</th>
          </tr>
          </thead>
          <tbody>
          @foreach ($items as $item)
          <tr class="text-center">
          <td>{{$item->name}}</td>
          <td>{{$item->email}}</td>
          <td>{{$item->rol}}</td>
          <td>{!! $item->activo ? '<div class="form-check form-switch text-center">
        <input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked" checked>
        </div>' :
      '<div class="form-check form-switch text-center">
        <input class="form-check-input" type="checkbox" id="flexSwitchCheckDefault">
        </div>' !!}</td>
          <td>
          <a href="{{route('usuarios.edit',$item->id)}}" class="btn btn-success btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
          <a href="#" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></a>
          </td>
          </tr>
      @endforeach
        </table>
        <!-- End Table with stripped rows -->

        </div>
      </div>

      </div>
    </div>
    </section>

  </main>

@endsection