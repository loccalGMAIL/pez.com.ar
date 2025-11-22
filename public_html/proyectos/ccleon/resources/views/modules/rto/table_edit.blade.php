<table class="table remitos-datatable">
    <thead>
        <tr>
            <th class="text-center">Elementos</th>
            <th class="text-center">Dolares</th>
            <th class="text-center">Pesos</th>
            <th class="text-center">T.C. Teórico</th>
            <th class="text-center">SubTotal Teórico</th>
            <th class="text-center columna-final toggle-column" style="display: none;">Pesos Final</th>
            <th class="text-center columna-final toggle-column" style="display: none;">T.C. Final</th>
            <th class="text-center columna-final toggle-column" style="display: none;">SubTotal Final</th>
            <th class="text-center"></th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalTeorico = 0;
            $totalFinal = 0;
        @endphp
        @foreach ($detalles as $detalle)
                @php
                    $subtotalTeorico = $detalle->subTotalRtoTeorico;
                    $totalTeorico += $subtotalTeorico;

                    // Obtenemos el valor final si existe
                    $valorPesosRtoReal = $detalle->valorPesosRtoReal ?? 0;
                    $tcFinal = $detalle->TC_RtoReal ?? 0;
                    $subtotalFinal = $detalle->subTotalRtoReal;
                    $totalFinal += $subtotalFinal;
                @endphp
                <tr class="text-center">
                    <td>{{ $detalle->elemento->descripcionElementoRto ?? 'Sin descripción' }}</td>
                    <td class="text-end editable-cell" data-type="dolar" data-id="{{ $detalle->id }}"
                        data-field="valorDolaresRtoTeorico">
                        {{ number_format($detalle->valorDolaresRtoTeorico, 2, ',', '.') }}
                    </td>
                    <td class="text-end editable-cell" data-type="peso" data-id="{{ $detalle->id }}"
                        data-field="valorPesosRtoTeorico">
                        {{ number_format($detalle->valorPesosRtoTeorico, 2, ',', '.') }}
                    </td>
                    <td class="text-end editable-cell" data-type="tc" data-id="{{ $detalle->id }}" data-field="TC_RtoTeorico">
                        {{ number_format($detalle->TC_RtoTeorico, 2, ',', '.') }}
                    </td>
                    <td class="text-end" data-type="peso" data-id="{{ $detalle->id }}" data-field="subTotalRtoTeorico">
                        {{ number_format($subtotalTeorico, 2, ',', '.') }}
                    </td>
                    <td class="text-end columna-final toggle-column editable-cell" data-type="peso" data-id="{{ $detalle->id }}"
                        data-field="valorPesosRtoReal" style="display: none;">
                        {{ number_format($valorPesosRtoReal, 2, ',', '.') }}
                    </td>
                    <td class="text-end columna-final toggle-column editable-cell" data-type="tc" data-id="{{ $detalle->id }}"
                        data-field="TC_RtoReal" style="display: none;">
                        {{ number_format($tcFinal, 2, ',', '.') }}
                    </td>
                    <td class="text-end columna-final toggle-column" data-type="peso" data-id="{{ $detalle->id }}"
                        data-field="subTotalRtoReal" style="display: none;">
                        {{ number_format($subtotalFinal, 2, ',', '.') }}
                    </td>
                    <td>
                        <a href="#" class="badge bg-danger eliminar-elemento"
                            data-id="{{ $detalle->id }}"><i class="fa-solid fa-trash-can"></i></a>
                    </td>
                </tr>
        @endforeach
        <!-- Fila de totales -->
        <tr class="table-primary">
            <td class="fw-bold">TOTALES:</td>
            <td></td>
            <td></td>
            <td></td>
            <td class="fw-bold text-end">$ {{ number_format($totalTeorico, 2, ',', '.') }}</td>
            <td class="columna-final toggle-column" style="display: none;"></td>
            <td class="columna-final toggle-column" style="display: none;"></td>
            <td class="fw-bold text-end columna-final toggle-column" data-type="total" data-id="{{ $items->id }}"
                data-field="totalFinalRto" style="display: none;">$ {{ number_format($totalFinal, 2, ',', '.') }}</td>
        </tr>
        <!-- Fila de diferencia -->
        <tr class="table-info">
            <td class="fw-bold">DIFERENCIA:</td>
            <td></td>
            <td></td>
            <td colspan="2" class="fw-bold text-end">
                $ {{ number_format($totalTeorico - $totalFinal, 2, ',', '.') }}
            </td>

        </tr>
    </tbody>
</table>

<script>
    // Inicializar DataTable (sin mostrar los botones automáticos)
    if (typeof $ !== 'undefined' && $.fn.dataTable) {
        var table = $('.remitos-datatable').DataTable({
            searching: false,
            lengthChange: false,
            info: false,
            paging: true,
        });
    }

    document.getElementById('btnPDF').addEventListener('click', function () {
        exportTableToPDF();
    });

    document.getElementById('btnPrint').addEventListener('click', function () {
        printTable();
    });


    // Función para imprimir
    function printTable() {
    // Obtener la tabla de remitos
    const tableHTML = document.querySelector('.remitos-datatable').outerHTML;

    // Obtener detalles del remito
    const nroRemito = document.querySelector('.card-title').textContent.trim().split('Nro:')[1]?.trim() || '';

    // Obtener información del proveedor del select
    let proveedorText = 'No especificado';
    try {
        const proveedorSelect = document.getElementById('idProveedor');
        if (proveedorSelect && proveedorSelect.selectedIndex >= 0) {
            proveedorText = proveedorSelect.options[proveedorSelect.selectedIndex].text;
        }
    } catch (e) {
        console.error('Error al obtener proveedor:', e);
    }

    // Obtener fecha y número de factura
    let fechaIngreso = '';
    let nroFactura = 'No especificado';
    try {
        const fechaInput = document.getElementById('fechaIngresoRto');
        if (fechaInput) {
            fechaIngreso = fechaInput.value;
        } else {
            fechaIngreso = new Date().toISOString().slice(0, 10);
        }

        const facturaInput = document.getElementById('nroFacturaRto');
        if (facturaInput) {
            nroFactura = facturaInput.value;
        }
    } catch (e) {
        console.error('Error al obtener fecha o factura:', e);
    }

    // Formatear fecha para mostrar
    let fechaFormateada = '';
    try {
        if (fechaIngreso) {
            fechaFormateada = new Date(fechaIngreso).toLocaleDateString('es-AR');
        } else {
            fechaFormateada = new Date().toLocaleDateString('es-AR');
        }
    } catch (e) {
        console.error('Error al formatear fecha:', e);
        fechaFormateada = new Date().toLocaleDateString('es-AR');
    }

    // Verificar valores y usar predeterminados si es necesario
    if (!proveedorText || proveedorText === '') {
        proveedorText = 'No especificado';
    }

    if (!nroFactura || nroFactura === '') {
        nroFactura = 'No especificado';
    }

    if (!fechaFormateada || fechaFormateada === '') {
        fechaFormateada = new Date().toLocaleDateString('es-AR');
    }

    console.log('Valores finales para impresión:');
    console.log('Proveedor:', proveedorText);
    console.log('Factura:', nroFactura);
    console.log('Fecha:', fechaFormateada);

    // Crear ventana de impresión
    const printWindow = window.open('', '_blank');

    // Escribir el contenido con estilos optimizados para impresión
    printWindow.document.write(`
    <html>
    <head>
        <title>Impresión de Remito ${nroRemito}</title>
        <style>
            /* Esta es la línea clave para ocultar el encabezado */
            @page :first {
                margin-top: 0;
            }
            /* Estilos generales */
            body {
                font-family: Arial, sans-serif;
                font-size: 11px; /* Tipografía más pequeña */
                line-height: 1.3;
                color: #000;
                margin: 0;
                padding: 10px;
            }
            
            /* Estilos de cabecera */
            .header {
                margin-bottom: 15px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }
            
            h1 {
                font-size: 16px;
                margin: 0 0 5px 0;
            }
            
            h2 {
                font-size: 14px;
                margin: 0 0 5px 0;
            }
            
            .fecha {
                font-style: italic;
                margin-bottom: 5px;
            }
            
            /* Estilos para detalles del remito */
            .remito-detalles {
                margin-bottom: 15px;
                padding: 10px;
                border: 1px solid #ddd;
                background-color: #f9f9f9;
            }
            
            .remito-detalles table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .remito-detalles table td {
                padding: 3px;
                border: none;
            }
            
            .remito-detalles .label {
                font-weight: bold;
                width: 120px;
            }
            
            /* Estilos de tabla */
            table {
                border-collapse: collapse;
                width: 100%;
                margin-bottom: 15px;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 4px; /* Padding más compacto */
                text-align: left;
                font-size: 10px; /* Tipografía aún más pequeña para la tabla */
            }
            
            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            
            /* Clases para mostrar columnas finales */
            .toggle-column {
                display: table-cell !important;
            }
            
            /* Ocultar columna de acciones */
            th:last-child, td:last-child {
                display: none;
            }
            
            /* Estilos para datos numéricos */
            .text-end, .text-right {
                text-align: right;
            }
            
            /* Estilos para filas especiales */
            .table-primary, .table-info {
                font-weight: bold;
            }
            
            .table-primary {
                background-color: #e6f2ff !important;
            }
            
            .table-info {
                background-color: #e8f4f8 !important;
            }
            
            /* Pies de página */
            .footer {
                margin-top: 20px;
                font-size: 9px;
                text-align: center;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            
            .company-header {
                display: flex;
                flex-direction: row; /* Asegura que los elementos estén en línea */
                align-items: center; /* Alinea verticalmente en el centro */
                margin-bottom: 10px;
            }

            .logo {
                flex: 0 0 auto; /* No permite que el logo se estire */
                margin-right: 20px; /* Espacio entre el logo y el texto */
            }

            .company-info {
                flex: 1 1 auto; /* Permite que el texto ocupe el espacio restante */
            }
        </style>
    </head>
    <body>
        
        <div class="header">
            <div class="company-header">
                <div class="logo">
                    <img src="http://grupoleonsa.com/wp-content/uploads/2021/07/logo-1.png" alt="Logo Empresa" style="height: 60px; max-width: 200px;">
                </div>
                <div class="company-info">
                    <h2 style="margin: 0; font-size: 14px;">Comercial Compras - Grupo León</h2>
                    <p style="margin: 2px 0; font-size: 10px;">Ruta Provincial 39 km 146 – C. del Uruguay – Entre Ríos</p>
                    <p style="margin: 2px 0; font-size: 10px;">Tel: 08004440479 | Email: contacto@grupoleonsa.com</p>
                    <p style="margin: 2px 0; font-size: 10px;">CUIT: 30-12345678-9</p>
                </div>
            </div>
            <div style="border-bottom: 1px solid #ddd; margin: 10px 0;"></div>
            <h1>Detalle de Remito ${nroRemito}</h1>
        </div>
        
        <div class="remito-detalles">
            <table>
                <tr>
                    <td class="label">Proveedor: <span style="font-weight: normal;">${proveedorText}</span></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="label">Nro. de Factura: <span style="font-weight: normal;">${nroFactura}</span></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="label">Fecha de Ingreso: <span style="font-weight: normal;">${fechaFormateada}</span></td>
                    <td></td>
                </tr>
            </table>
        </div>
        
        ${tableHTML}
        
        <div class="footer">
            <p>Documento generado por Sistema de Remitos - ${new Date().toLocaleString('es-AR')}</p>
        </div>
    </body>
    </html>
    `);

    printWindow.document.close();
    printWindow.focus();

        // Agregar evento para cerrar la ventana después de imprimir
        printWindow.addEventListener('afterprint', function() {
        printWindow.close();
    });
    
    // Aplicar modificaciones a la tabla en la ventana de impresión
    printWindow.onload = function() {
        // Asegurar que las columnas finales sean visibles
        const printToggleColumns = printWindow.document.querySelectorAll('.toggle-column');
        printToggleColumns.forEach(col => {
            col.style.display = 'table-cell';
        });
        
        // Ocultar columna de acciones (última columna)
        const actionCells = printWindow.document.querySelectorAll('th:last-child, td:last-child');
        actionCells.forEach(cell => {
            cell.style.display = 'none';
        });
        
        // Asegurarse de que la fila de totales y diferencia están visibles
        const totalRow = printWindow.document.querySelector('.table-primary');
        const diffRow = printWindow.document.querySelector('.table-info');
        
        if (totalRow) {
            const totalCells = totalRow.querySelectorAll('td');
            totalCells.forEach(cell => {
                if (cell.classList.contains('toggle-column')) {
                    cell.style.display = 'table-cell';
                }
            });
        }
        
        if (diffRow) {
            const diffCells = diffRow.querySelectorAll('td');
            diffCells.forEach(cell => {
                cell.style.display = 'table-cell';
            });
        }
        
        // Dar tiempo para que se apliquen los cambios y luego imprimir
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
}
    
// Para PDF podríamos usar html2pdf.js u otra biblioteca similar
    // Esta función requerirá incluir una biblioteca adicional
    function exportTableToPDF() {
        alert('Para exportar a PDF, necesitarás incluir una biblioteca');
        // Implementación con html2pdf.js requeriría cargar esa biblioteca
    }

    // Variables para la edición en línea
    let activeEditCell = null;
    let columnasFinalesVisibles = false;

    // Función para alternar la visibilidad de las columnas finales
    function toggleFinalColumns() {
        const toggleColumns = document.querySelectorAll('.toggle-column');
        const toggleButton = document.getElementById('toggleFinalColumns');

        columnasFinalesVisibles = !columnasFinalesVisibles;

        toggleColumns.forEach(col => {
            col.style.display = columnasFinalesVisibles ? '' : 'none';
        });

        if (toggleButton) {
            toggleButton.innerHTML = columnasFinalesVisibles
                ? '<i class="fa-solid fa-eye-slash"></i> Ocultar Columnas Finales'
                : '<i class="fa-solid fa-eye"></i> Mostrar Columnas Finales';
        }

        // Si estamos ocultando columnas y hay una celda en edición en alguna columna final, cerrar la edición
        if (!columnasFinalesVisibles && activeEditCell && activeEditCell.classList.contains('columna-final')) {
            finishEditing(false);
        }
    }

    // Función para verificar y aplicar restricciones de edición a las celdas
    function aplicarRestriccionesEdicion() {
        // Recorrer todas las filas de la tabla (excluyendo encabezados y totales)
        const filas = document.querySelectorAll('.remitos-datatable tbody tr:not(.table-primary):not(.table-info)');

        filas.forEach(fila => {
            // Obtener celdas relevantes de la fila
            const celdaDolares = fila.querySelector('td[data-field="valorDolaresRtoTeorico"]');
            const celdaPesos = fila.querySelector('td[data-field="valorPesosRtoTeorico"]');
            const celdaTCTeorico = fila.querySelector('td[data-field="TC_RtoTeorico"]');
            const celdaTCFinal = fila.querySelector('td[data-field="TC_RtoReal"]');

            if (!celdaDolares || !celdaPesos || !celdaTCTeorico || !celdaTCFinal) return;

            // Obtener valores numéricos
            const valorDolares = parseFloat(celdaDolares.textContent.replace(/\./g, '').replace(',', '.')) || 0;
            const valorPesos = parseFloat(celdaPesos.textContent.replace(/\./g, '').replace(',', '.')) || 0;

            // Aplicar restricciones
            if (valorPesos > 0) {
                // Si hay pesos, desactivar dólares y tipos de cambio
                desactivarCelda(celdaDolares);
                desactivarCelda(celdaTCTeorico);
                desactivarCelda(celdaTCFinal);
            } else if (valorDolares > 0) {
                // Si hay dólares, desactivar pesos
                desactivarCelda(celdaPesos);
            } else {
                // Si no hay valores, activar todas las celdas
                activarCelda(celdaDolares);
                activarCelda(celdaPesos);
                activarCelda(celdaTCTeorico);
                activarCelda(celdaTCFinal);
            }
        });
    }

    // Función para desactivar una celda
    function desactivarCelda(celda) {
        if (!celda) return;

        // Remover clase editable-cell si existe
        celda.classList.remove('editable-cell');

        // Agregar clase para indicar visualmente que está desactivada
        celda.classList.add('celda-desactivada');

        // Remover eventos click que pudieran estar adjuntos
        const nuevoElemento = celda.cloneNode(true);
        celda.parentNode.replaceChild(nuevoElemento, celda);
    }

    // Función para activar una celda
    function activarCelda(celda) {
        if (!celda) return;

        // Si ya está activa, no hacemos nada
        if (celda.classList.contains('editable-cell')) return;

        // Agregar clase editable-cell
        celda.classList.add('editable-cell');

        // Remover clase de desactivación si existe
        celda.classList.remove('celda-desactivada');

        // Agregar evento click para edición
        celda.addEventListener('click', function () {
            startEditing(this);
        });
    }

    // Función para iniciar la edición de una celda
    function startEditing(cell) {
        // Verificar si la celda está desactivada
        if (cell.classList.contains('celda-desactivada')) {
            return; // No permitir edición si está desactivada
        }

        // Si ya hay una celda en edición, terminar primero
        if (activeEditCell && activeEditCell !== cell) {
            finishEditing(false); // false = no guardar
        }

        // Marcar esta celda como activa
        activeEditCell = cell;

        // Obtener el valor actual (sin formato)
        const currentText = cell.textContent.trim();
        const currentValue = currentText.replace(/\./g, '').replace(',', '.');

        // Guardar el valor original como atributo para posible cancelación
        cell.setAttribute('data-original-text', currentText);

        // Crear input de manera segura
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentValue;

        // Limpiar la celda manualmente
        while (cell.firstChild) {
            cell.removeChild(cell.firstChild);
        }

        // Añadir clase de edición y el input
        cell.classList.add('editing');
        cell.appendChild(input);

        // Dar foco al input
        input.focus();
        input.select();

        // Manejar teclas para guardar/cancelar
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                finishEditing(true); // true = guardar
            } else if (e.key === 'Escape') {
                e.preventDefault();
                finishEditing(false); // false = cancelar
            }
        });

        // También guardar al perder el foco
        input.addEventListener('blur', function () {
            if (activeEditCell === cell) {
                finishEditing(true);
            }
        });
    }

    // Función para finalizar la edición
    function finishEditing(save) {
        if (!activeEditCell) return;

        const input = activeEditCell.querySelector('input');
        if (!input) {
            restoreCell(activeEditCell);
            return;
        }

        if (save) {
            saveCell(activeEditCell, input.value);
        } else {
            // Cancelar - restaurar valor original
            const originalText = activeEditCell.getAttribute('data-original-text') || '';
            restoreCell(activeEditCell, originalText);
        }

        // Aplicar restricciones de edición después de finalizar
        setTimeout(aplicarRestriccionesEdicion, 100);
    }

    // Función para restaurar una celda
    function restoreCell(cell, text = '') {
        if (!cell) return;

        try {
            // Limpiar la celda de manera segura
            while (cell.firstChild) {
                cell.removeChild(cell.firstChild);
            }

            cell.classList.remove('editing');
            cell.textContent = text;

            if (cell === activeEditCell) {
                activeEditCell = null;
            }
        } catch (error) {
            console.error('Error al restaurar celda:', error);
            // Si hay error, intentar simplemente asignar el texto
            if (cell) {
                try {
                    cell.textContent = text;
                    cell.classList.remove('editing');
                    if (cell === activeEditCell) {
                        activeEditCell = null;
                    }
                } catch (e) {
                    console.error('Error secundario al restaurar celda:', e);
                }
            }
        }
    }

    // Función para guardar el valor de una celda
    function saveCell(cell, value) {
        if (!cell) return;

        let newValue = value.trim();

        // Validar que sea un número
        if (!/^[0-9]*[.,]?[0-9]*$/.test(newValue)) {
            alert('Por favor, ingrese un valor numérico válido');
            const input = cell.querySelector('input');
            if (input) {
                input.focus();
            }
            return;
        }

        // Convertir coma a punto para cálculos
        newValue = newValue.replace(',', '.');

        // Obtener datos para la actualización
        const id = cell.dataset.id;
        const field = cell.dataset.field;

        if (!id || !field) {
            console.error('Falta id o field en la celda editable');
            const originalText = cell.getAttribute('data-original-text') || '';
            restoreCell(cell, originalText);
            return;
        }

        // Mostrar valor formateado mientras se guarda
        const formattedValue = parseFloat(newValue).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Guardar una referencia al dataset para acceder después de la llamada fetch
        const cellInfo = {
            id: id,
            field: field,
            isRealField: cell.classList.contains('columna-final')
        };

        // Restaurar la celda con el nuevo valor formateado
        try {
            restoreCell(cell, formattedValue);
        } catch (e) {
            console.error('Error al restaurar celda antes del fetch:', e);
        }

        // Crear FormData para envío
        const formData = new FormData();
        formData.append('id', id);
        formData.append('field', field);
        formData.append('value', newValue);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

// Enviar actualización al servidor
fetch('/remitos/actualizarCampo', {
    method: 'POST',
    body: formData,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json' // Añadir esta línea
    }
})
    .then(response => {
        // Verificar si la respuesta es exitosa
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        // Verificar que la respuesta es realmente JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            // Si no es JSON, intenta obtener el texto para ver el error
            return response.text().then(text => {
                console.error('Respuesta no JSON:', text);
                throw new TypeError("La respuesta no es JSON");
            });
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Usar una referencia más estable para la actualización de la UI
            const updatedCell = document.querySelector(`[data-id="${cellInfo.id}"][data-field="${cellInfo.field}"]`);
            if (updatedCell) {
                updateUIWithServerData(updatedCell, data);
            } else {
                // Si no podemos encontrar la celda original, actualizar por selección directa
                updateUIWithoutCell(cellInfo, data);
            }
            // Aplicar restricciones de edición después de actualizar la UI
            setTimeout(aplicarRestriccionesEdicion, 100);
        } else {
            handleServerError(data.message);
        }
    })
    .catch(error => {
        console.error('Error completo:', error);
        handleServerError(error.message || 'Error en la comunicación con el servidor');
    });
    }

    // Función para actualizar la UI con datos del servidor
    function updateUIWithServerData(cell, data) {
        try {
            if (!cell) return;

            // Determinar si estamos en una columna final
            const isRealField = cell.classList.contains('columna-final') || data.isRealField;
            const row = cell.closest('tr');

            if (!row) return;

            // Cuando se actualiza dólares o pesos, necesitamos actualizar ambos subtotales
            const updateBothSubtotals = ['valorDolaresRtoTeorico', 'valorPesosRtoTeorico'].includes(cell.dataset.field);

            // Actualizar subtotal teórico si corresponde
            if (data.subtotal !== undefined) {
                const theoreticSubtotalCell = row.querySelector('td:nth-child(5)');
                if (theoreticSubtotalCell) {
                    theoreticSubtotalCell.textContent = parseFloat(data.subtotal).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            // Actualizar subtotal final si corresponde o si estamos actualizando dólares/pesos
            if ((isRealField || updateBothSubtotals) && data.subtotalFinal !== undefined) {
                const finalSubtotalCell = row.querySelector('td:nth-child(8)');
                if (finalSubtotalCell) {
                    finalSubtotalCell.textContent = parseFloat(data.subtotalFinal).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            // Actualizar totales generales
            if (data.totalTeorico !== undefined) {
                const totalTeoricoElement = document.querySelector('tr.table-primary td:nth-child(5)');
                if (totalTeoricoElement) {
                    totalTeoricoElement.textContent = '$ ' + parseFloat(data.totalTeorico).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            if (data.totalFinal !== undefined) {
                const totalFinalElement = document.querySelector('tr.table-primary td:nth-child(8)');
                if (totalFinalElement) {
                    totalFinalElement.textContent = '$ ' + parseFloat(data.totalFinal).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            // Actualizar diferencia
            if (data.diferencia !== undefined) {
                const diferenciaElement = document.querySelector('tr.table-info td:nth-child(2)');
                if (diferenciaElement) {
                    diferenciaElement.textContent = '$ ' + parseFloat(data.diferencia).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        } catch (error) {
            console.error('Error al actualizar UI:', error);
        }
    }

    // Nueva función para actualizar la UI sin referencia a la celda original
    function updateUIWithoutCell(cellInfo, data) {
        try {
            // Encontrar la fila por id
            const row = document.querySelector(`tr td[data-id="${cellInfo.id}"]`).closest('tr');
            if (!row) return;

            // Determinar si estamos en un campo final
            const isRealField = cellInfo.isRealField || data.isRealField;

            // Actualizar subtotales según corresponda
            if (isRealField && data.subtotalFinal !== undefined) {
                const finalSubtotalCell = row.querySelector('td:nth-child(8)');
                if (finalSubtotalCell) {
                    finalSubtotalCell.textContent = parseFloat(data.subtotalFinal).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            } else if (data.subtotal !== undefined) {
                const theoreticSubtotalCell = row.querySelector('td:nth-child(5)');
                if (theoreticSubtotalCell) {
                    theoreticSubtotalCell.textContent = parseFloat(data.subtotal).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            // Actualizar totales generales
            updateTotals(data);
        } catch (error) {
            console.error('Error al actualizar UI sin celda:', error);
        }
    }

    // Nueva función para actualizar solo los totales
    function updateTotals(data) {
        try {
            // Actualizar totales generales
            if (data.totalTeorico !== undefined) {
                const totalTeoricoElement = document.querySelector('tr.table-primary td:nth-child(5)');
                if (totalTeoricoElement) {
                    totalTeoricoElement.textContent = '$ ' + parseFloat(data.totalTeorico).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            if (data.totalFinal !== undefined) {
                const totalFinalElement = document.querySelector('tr.table-primary td:nth-child(8)');
                if (totalFinalElement) {
                    totalFinalElement.textContent = '$ ' + parseFloat(data.totalFinal).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            // Actualizar diferencia
            if (data.diferencia !== undefined) {
                const diferenciaElement = document.querySelector('tr.table-info td:nth-child(2)');
                if (diferenciaElement) {
                    diferenciaElement.textContent = '$ ' + parseFloat(data.diferencia).toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        } catch (error) {
            console.error('Error al actualizar totales:', error);
        }
    }

    // Función auxiliar para actualizar elementos de totales
    function updateTotalElement(selector, formattedValue) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = formattedValue;
        }
    }

    // Manejar errores del servidor
    function handleServerError(message) {
        alert('Error: ' + (message || 'Error desconocido'));
    }

    // Documento cargado
    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar botón de toggle para columnas finales
        const toggleBtn = document.getElementById('toggleFinalColumns');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleFinalColumns);
        }

        // Aplicar restricciones iniciales
        aplicarRestriccionesEdicion();

        // Inicializar las celdas editables
        const editableCells = document.querySelectorAll('.editable-cell');
        if (editableCells && editableCells.length > 0) {
            editableCells.forEach(cell => {
                if (cell && !cell.classList.contains('celda-desactivada')) {
                    cell.addEventListener('click', function () {
                        startEditing(this);
                    });
                }
            });
        }
    });
</script>