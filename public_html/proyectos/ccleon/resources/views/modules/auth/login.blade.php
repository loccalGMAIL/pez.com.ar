@extends('layouts.login')

@section('titulo', $titulo)

@section('contenido')

<main>
    <div class="container">

      <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">

              <div class="d-flex justify-content-center py-4">
                <a href="#" class="logo d-flex align-items-center w-auto">
                  <img src="{{asset('NiceAdmin/assets/img/logo.png')}}" alt="">
                 
                  <span class="d-none d-lg-block">Comercial Compras</span>
                </a>
              </div><!-- End Logo -->

              <div class="card mb-3">

                <div class="card-body">

                  <div class="pt-4 pb-2">
                    <h5 class="card-title text-center pb-0 fs-4">Login de Usuario</h5>
                    <p class="text-center small">Ingresa tu email y password para acceder</p>
                  </div>

                  <form class="row g-3 needs-validation" novalidate action="{{route('loguear')}}" method="POST" >
                    @csrf
                    <div class="col-12">
                      <label for="email" class="form-label">Email</label>
                      <div class="input-group has-validation">
                        <span class="input-group-text" id="inputGroupPrepend">@</span>
                        <input type="text" name="email" class="form-control" id="email" required>
                        <div class="invalid-feedback">Escribe tu correo.</div>
                      </div>
                    </div>

                    <div class="col-12">
                      <label for="password" class="form-label">Password</label>
                      <input type="password" name="password" class="form-control" id="password" required>
                      <div class="invalid-feedback">Por favor, ingrese un password!</div>
                    </div>


                    <div class="col-12">
                      <button class="btn btn-primary w-100" type="submit">Login</button>
                    </div>

                    <div>
                      @if ($errors->any())
                      <p>
                        <ul>
                          @foreach ($errors->all() as $error)
                          <li>{{$error}}</li>
                          @endforeach
                        </ul>
                      </p>                                     
                      @endif
                    </div>

                  </form>

                </div>
              </div>

              <div class="credits">                
                Designed by <a target="_blank" href="https://pez.com.ar">Pez</a>
              </div>

            </div>
          </div>
        </div>

      </section>

    </div>
  </main>

@endsection