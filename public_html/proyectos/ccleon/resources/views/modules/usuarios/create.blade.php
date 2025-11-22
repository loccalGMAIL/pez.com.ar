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
              <h5 class="card-title">Agregar nuevo usuario</h5>
              <form action="{{route('usuarios.store')}}" method="POST">
                  @csrf
                <label for="name">Nombre de usuario</label>
                <input type="text" class="form-control" name="name" id="name" required>
                <label for="email">Email</label>
                <input type="text" class="form-control" name="email" id="email" required>
                <label for="password">Password</label>
                <input type="password" class="form-control" name="password" id="password" required>
                <label for="rol">Rol de Usuario</label>
                <select name="rol" id="rol" class="form-control">
                  <option value=""></option>
                  <option value="admin">Administrador</option>
                  <option value="usuario">Usuario</option>
                </select>
                <button class="btn btn-primary mt-3">Guardar</button>
                <a href="{{route('usuarios')}}" class="btn btn-info mt-3">Cancelar</a>
              </form>

            </div>
          </div>

        </div>
      </div>
    </section>

  </main>

@endsection