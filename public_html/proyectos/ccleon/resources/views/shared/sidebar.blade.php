<aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

      <li class="nav-item">
        <a class="nav-link " href="{{route('home')}}">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li><!-- End Dashboard Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
          <i class="fa-solid fa-file-signature"></i><span>Remitos</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="components-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="{{route('remitos')}}">
              <i class="bi bi-circle"></i><span>Listar</span>
            </a>
          </li>
          <li>
            <a href="{{route('reclamos')}}">
              <i class="bi bi-circle"></i><span>Reclamos</span>
            </a>
          </li>
          <li>
            <a href="{{route('observaciones')}}">
              <i class="bi bi-circle"></i><span>Observaciones</span>
            </a>
          </li>
        </ul>
      </li><!-- End Components Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="{{route('proveedores')}}">
          <i class="fa-solid fa-truck-field"></i>
          <span>Proveedores</span>
        </a>
      </li><!-- End Login Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="{{route('usuarios')}}">
          <i class="fa-solid fa-users"></i>
          <span>Usuarios</span>
        </a>
      </li><!-- End Login Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="{{route('informes')}}">
          <i class="bi bi-menu-button-wide"></i>
          <span>Informes</span>
        </a>
      </li><!-- End Error 404 Page Nav -->

    </ul>

  </aside>