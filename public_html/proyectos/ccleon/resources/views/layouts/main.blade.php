<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title> @yield('titulo') </title>
  <meta content="" name="description">
  <meta content="" name="keywords">
  <!-- Favicons -->
  <link href="{{asset('NiceAdmin/assets/img/logo.png')}}" rel="icon">
  <link href="{{asset('NiceAdmin/assets/img/logo.png')}}" rel="apple-touch-icon">
  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <!-- Vendor CSS Files -->
  <link href="{{asset('NiceAdmin/assets/vendor/bootstrap/css/bootstrap.min.css')}}" rel="stylesheet">
  <link href="{{asset('NiceAdmin/assets/vendor/bootstrap-icons/bootstrap-icons.css')}}" rel='stylesheet'>
  <link href="{{asset('NiceAdmin/assets/vendor/boxicons/css/boxicons.min.css')}}" rel="stylesheet">
  <link href="{{asset('NiceAdmin/assets/vendor/quill/quill.snow.css')}}" rel="stylesheet">
  <link href="{{asset('NiceAdmin/assets/vendor/quill/quill.bubble.css')}}" rel="stylesheet">
  <link href="{{asset('NiceAdmin/assets/vendor/remixicon/remixicon.css')}}" rel="stylesheet">
  <link href="{{asset('NiceAdmin/assets/vendor/simple-datatables/style.css')}}" rel="stylesheet">
  
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
  
  <!-- Template Main CSS File -->
  <link href="{{asset('NiceAdmin/assets/css/style.css')}}" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link href="{{ asset('NiceAdmin/assets/css/custom.css') }}" rel="stylesheet">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  
  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body>
  <!-- ======= Header ======= -->
  @include('shared.header')
  <!-- End Header -->
  
  <!-- ======= Sidebar ======= -->
  @include('shared.sidebar')
  <!-- End Sidebar-->
  
  @yield('contenido')
  
  </div><!-- End #main -->
  
  <!-- ======= Footer ======= -->
  @include('shared.footer')
  <!-- End Footer -->
  
  <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
  </a>
  
  <!-- Vendor JS Files -->
  <!-- jQuery first -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  
  <!-- DataTables Core -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  
  <!-- DataTables Buttons and Dependencies -->
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  
  <!-- Other Vendor JS Files -->
  <script src="{{asset('NiceAdmin/assets/vendor/apexcharts/apexcharts.min.js')}}"></script>
  <script src="{{asset('NiceAdmin/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
  <script src="{{asset('NiceAdmin/assets/vendor/chart.js/chart.umd.js')}}"></script>
  <script src="{{asset('NiceAdmin/assets/vendor/echarts/echarts.min.js')}}"></script>
  <script src="{{asset('NiceAdmin/assets/vendor/quill/quill.js')}}"></script>
  <script src="{{asset('NiceAdmin/assets/vendor/simple-datatables/simple-datatables.js')}}"></script>
  <script src="{{asset('NiceAdmin/assets/vendor/tinymce/tinymce.min.js')}}"></script>
  <script src="{{asset('NiceAdmin/assets/vendor/php-email-form/validate.js')}}"></script>
  
  <!-- Template Main JS File -->
  <script src="{{asset('NiceAdmin/assets/js/main.js')}}"></script>
  
  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  
  <!-- Script para mantener desplegado el menú de Remitos -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Obtener la URL actual
      const currentPath = window.location.pathname;
      
      // Verificar si estamos en una página relacionada con remitos
      const isRemitosPage = 
        currentPath.includes('/remitos') || 
        currentPath.includes('/observaciones') || 
        currentPath.includes('/reclamos');
      
      if (isRemitosPage) {
        // Buscar el enlace principal de Remitos
        const remitosLink = document.querySelector('a[href*="remitos"]');
        
        if (remitosLink) {
          // Encontrar el elemento padre que contiene la clase 'nav-item'
          const remitosNavItem = remitosLink.closest('.nav-item');
          
          if (remitosNavItem) {
            // Agregar la clase 'show' al elemento para mantenerlo desplegado
            remitosNavItem.classList.add('show');
            
            // Buscar el botón/enlace que tiene la clase 'nav-link' dentro del nav-item
            const navLink = remitosNavItem.querySelector('.nav-link');
            if (navLink && !navLink.classList.contains('collapsed')) {
              navLink.classList.remove('collapsed');
            }
            
            // Buscar el contenedor de submenús
            const navContent = remitosNavItem.querySelector('.nav-content');
            if (navContent) {
              navContent.style.display = 'block';
              
              // Marcar como activo el submenú correspondiente
              const subMenuLinks = navContent.querySelectorAll('a');
              subMenuLinks.forEach(link => {
                if (
                  (currentPath.includes('/listar') && link.textContent.trim() === 'Listar') ||
                  (currentPath.includes('/reclamos') && link.textContent.trim() === 'Reclamos') ||
                  (currentPath.includes('/observaciones') && link.textContent.trim() === 'Observaciones')
                ) {
                  link.classList.add('active');
                }
              });
            }
          }
        }
      }
    });
  </script>
</body>
</html>