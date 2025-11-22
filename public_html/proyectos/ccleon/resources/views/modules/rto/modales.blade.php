<!-- Modal para agregar proveedor -->
<div class="modal fade" id="agregarProveedorModal" tabindex="-1" aria-labelledby="agregarProveedorModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="agregarProveedorModalLabel">Agregar Nuevo Proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="nuevoProveedorForm" method="POST" action="{{ route('proveedores.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="nombreProveedor" class="form-label">Nombre del proveedor</label>
                        <input type="text" class="form-control" id="nombreProveedor" name="nombreProveedor" required>
                    </div>
                    <div class="mb-3">
                        <label for="dniProveedor" class="form-label">DNI</label>
                        <input type="text" class="form-control" id="dniProveedor" name="dniProveedor">
                    </div>
                    <div class="mb-3">
                        <label for="razonSocialProveedor" class="form-label">Razón Social</label>
                        <input type="text" class="form-control" id="razonSocialProveedor" name="razonSocialProveedor"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="cuitProveedor" class="form-label">CUIT</label>
                        <input type="text" class="form-control" id="cuitProveedor" name="cuitProveedor" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefonoProveedor" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefonoProveedor" name="telefonoProveedor">
                    </div>
                    <div class="mb-3">
                        <label for="mailProveedor" class="form-label">Email</label>
                        <input type="email" class="form-control" id="mailProveedor" name="mailProveedor">
                    </div>
                    <div class="mb-3">
                        <label for="direccionProveedor" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccionProveedor" name="direccionProveedor">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" form="nuevoProveedorForm" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar elemento del RTO -->
<div class="modal fade" id="agregarElementoModal" tabindex="-1" aria-labelledby="agregarElementoModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="agregarElementoModalLabel">Agregar Elemento al Remito</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="elementoRtoForm" method="POST" action="{{route('storeRtoDetalle.store')}}">
                    @csrf
                    <input type="hidden" name="rto_id" value="{{ $items->id }}">
                    <input type="hidden" name="rtoteorico_id" id="rtoteorico_id" value="">

                    <div class="mb-3">
                        <label for="elementoRto_id" class="form-label">Elemento</label>
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <select class="form-select elemento-select" id="elementoRto_id" name="elementoRto_id"
                                    required>
                                    <option value="">Seleccionar elemento</option>
                                    @foreach($elementosRto as $elemento)
                                        <option value="{{ $elemento->id }}">{{ $elemento->descripcionElementoRto }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ms-2">
                                <button type="button" class="btn btn-success" id="btnNuevoElemento">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sección para nuevo elemento (inicialmente oculta) -->
                    <div id="nuevoElementoSeccion" class="mb-3 border p-3 rounded d-none">
                        <div class="mb-2">
                            <label for="descripcionNuevoElemento" class="form-label">Descripción del nuevo
                                elemento</label>
                            <input type="text" class="form-control" id="descripcionNuevoElemento"
                                name="descripcionNuevoElemento">
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2"
                                id="btnCancelarNuevoElemento">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarNuevoElemento">Guardar
                                Elemento</button>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <label for="valorDolaresRtoTeorico" class="form-label">Valor USD</label>
                            <input type="number" step="0.01" class="form-control" id="valorDolaresRtoTeorico"
                                name="valorDolaresRtoTeorico">
                        </div>
                        <div class="col">
                            <label for="valorPesosRtoTeorico" class="form-label">Valor ARS</label>
                            <input type="number" step="0.01" class="form-control" id="valorPesosRtoTeorico"
                                name="valorPesosRtoTeorico">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="TC_RtoTeorico" class="form-label">Tipo de Cambio</label>
                        <input type="number" step="0.01" class="form-control" id="TC_RtoTeorico" name="TC_RtoTeorico">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Observaciones -->
<div class="modal fade" id="observacionesModal" tabindex="-1" aria-labelledby="observacionesModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="observacionesModalLabel">Agregar Observación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            {{-- <form id="observacionForm" action="#" method="POST"> --}}
            <form id="observacionForm" action="{{ route('observaciones.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="modal_rto_id" name="Rto_id">

                    <div class="mb-3">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" required>
                    </div>

                    <div class="mb-3">
                        <label for="descripcionObservacionesRto" class="form-label">Observación</label>
                        <textarea class="form-control" id="descripcionObservacionesRto"
                            name="descripcionObservacionesRto" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Reclamos -->
<div class="modal fade" id="reclamosModal" tabindex="-1" aria-labelledby="reclamosModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="reclamosModalLabel">Agregar Reclamo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="reclamoForm" action="{{ route('reclamos.store') }}" method="POST">
          @csrf
          <div class="modal-body">
            <input type="hidden" id="reclamo_rto_id" name="Rto_id">
            
            <div class="row mb-3">
              <label for="producto" class="col-sm-3 col-form-label">Producto</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="producto" name="producto" required>
              </div>
            </div>
            
            <div class="row mb-3">
              <label for="cantidad" class="col-sm-3 col-form-label">Cantidad</label>
              <div class="col-sm-9">
                <input type="number" class="form-control" id="cantidad" name="cantidad" step="0.01" required>
              </div>
            </div>
            
            {{-- <div class="row mb-3">
              <label for="nroRemitoReclamo" class="col-sm-3 col-form-label">Nro. Remito</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="nroRemitoReclamo" readonly>
              </div>
            </div> --}}
            
            <div class="row mb-3">
              <label for="observaciones" class="col-sm-3 col-form-label">Observación</label>
              <div class="col-sm-9">
                <textarea class="form-control" id="observaciones" name="observaciones" rows="3" required></textarea>
              </div>
            </div>
            
            <div class="row mb-3">
              <label for="estadoReclamoRto" class="col-sm-3 col-form-label">Estado</label>
              <div class="col-sm-9">
                <select class="form-select" id="estadoReclamoRto" name="estadoReclamoRto" required>
                  <option value="pendiente">Pendiente</option>
                  <option value="resuelto">Resuelto</option>
                </select>
              </div>
            </div>
            
            <div class="row mb-3" id="resolucionContainer" style="display: none;">
              <label for="resolucionReclamoRto" class="col-sm-3 col-form-label">Resolución</label>
              <div class="col-sm-9">
                <textarea class="form-control" id="resolucionReclamoRto" name="resolucionReclamoRto" rows="3"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>