# Changelog

Todos los cambios relevantes del proyecto se registran en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
proyecto usa [Versionado Semántico](https://semver.org/lang/es/):

- **MAYOR** (`2.0.0`): cambios que rompen algo existente (por ejemplo, la actualización a Laravel 13).
- **MENOR** (`1.1.0`): funcionalidades nuevas (cada fase del sistema de gestión).
- **PARCHE** (`1.0.1`): correcciones sin funcionalidad nueva.

Cada versión publicada lleva un tag `vX.Y.Z` en `master`.

## [Sin publicar]

### Agregado

- **Facturación electrónica con ARCA (Factura C)**, opcional y al cobrar: "Facturar en ARCA"
  en el recibo abre una vista previa con el concepto (productos, servicios o ambos), el
  período facturado y el detail editable, y al confirmar pide el CAE. Los cargos y recibos
  siguen igual; solo se factura lo que se elige.
  - Cada ítem es un cargo que cancela el recibo (con "Pago parcial de $X" si no lo cancela
    entero) y lo que quedó sin imputar va como "Pago a cuenta". Lo que ya se facturó antes del
    pago no se vuelve a facturar: si el recibo no tiene nada nuevo, el botón no aparece.
  - La fecha es la del pago mientras ARCA la acepte (10 días atrás con servicios, 5 con
    productos) y nunca anterior a la última factura del punto de venta.
  - El cliente se identifica con su CUIT, su DNI (campo nuevo en el cliente) o como consumidor
    final sin identificar, e informa su condición frente al IVA. A un responsable inscripto,
    monotributista o exento sin CUIT no se le factura, y por encima del tope de ARCA hay que
    identificar al consumidor final.
  - Si ARCA rechaza la factura se muestran sus mensajes y se puede corregir y reintentar. Si
    no responde, la factura queda pendiente con su número y el reintento primero consulta a
    ARCA si llegó a autorizarla, para no duplicarla.
- **Facturar antes de cobrar**, para clientes que necesitan la factura para pagar: el abono
  tiene el check "Facturar antes de cobrar" y sus cargos quedan **"Para facturar"** (columna y
  filtro en Cargos, y aviso en el tablero) hasta que se factura con **"Facturar cargo"**. La
  factura vence cuando vence el cargo y el período es el mes del abono. "Facturar cargo" sirve
  también para cualquier otro cargo. Lo facturado se suma entre recibos y cargos, así que un
  cargo facturado en parte desde un recibo se completa desde el cargo, y al revés. Mientras un
  cargo tenga factura vigente, su monto y su concepto no se editan.
- **Notas de crédito C:** "Anular con nota de crédito" en la factura emite la nota por el total,
  asociada a la factura en ARCA, con su propia numeración. Al anular un cargo facturado se
  emite primero su nota de crédito, y si ARCA no la autoriza el cargo no se anula. Un cargo
  facturado junto con su pago se anula desde esa factura.
- Página de cada **factura** (Cobranza → Facturas): datos, ítems con el cargo que factura cada
  uno, mensajes de ARCA, nota de crédito asociada y las acciones (PDF, mail, WhatsApp, anular).
  El pago y el cargo muestran sus facturas y llevan a esa página.
- Factura en PDF con el diseño de los presupuestos: recuadro con la letra C y su código,
  datos del emisor y del cliente, período, detalle, total en letras y, al pie de la hoja, el
  QR de ARCA con el CAE y su vencimiento. Se archiva al autorizarse, se descarga y se envía por
  mail o WhatsApp con un link firmado. La nota de crédito usa el mismo diseño ("COD. 13" y la
  factura que anula). Las de homologación llevan la marca "sin validez fiscal".
- Listado de **Facturas** en Cobranza, con su estado (incluida "Anulada"), CAE y recibo.
- Sección **Facturación electrónica (ARCA)** en Configuración: activarla, punto de venta,
  nombre, domicilio comercial, Ingresos Brutos, inicio de actividades y botón "Probar
  conexión". El ambiente se elige con `ARCA_ENV` en el `.env`.
- **Certificado de ARCA desde el panel:** "Generar pedido" crea la clave privada en el
  servidor y descarga el pedido (`.csr`) para subir a ARCA, y "Subir certificado" instala el
  `.crt` que ARCA devuelve (archivo o texto pegado), después de verificar que sea para el CUIT
  de la empresa, que esté vigente y que corresponda al pedido. El certificado anterior sigue
  funcionando hasta instalar el nuevo, y la sección muestra el instalado y su vencimiento.
  Hay uno por ambiente (homologación y producción).

### Cambiado

- Configuración se organiza en pestañas: Empresa, Presupuestos (valores por defecto y cuotas),
  Cobranza y Facturación. La pestaña abierta queda en la dirección, así que guardar no vuelve
  a la primera.
- Recibos y presupuestos dicen "Documento no válido como factura"; el recibo facturado
  nombra sus facturas (la propia y las de los cargos facturados antes de cobrar).
- Un pago con factura vigente no se puede borrar: primero se anula la factura con nota de
  crédito. Borrar un pago nunca borra una factura emitida.
- El backup no incluye el certificado ni la clave de ARCA.

### Corregido

- Al editar un presupuesto cambiando precios y agregando (o modificando) cuotas en la misma
  guardada, las cuotas se calculaban con el total anterior. Ahora usan el total nuevo.

## [2.5.0] - 2026-11-02

### Agregado

- Sección **Informes** en el panel, con siete informes que se filtran por período (este mes,
  mes anterior, este año, año anterior u otro rango) y por cliente, y se descargan en PDF:
  - **Ingresos:** lo cobrado según la fecha de cada pago, por medio de pago y por mes, con el
    detalle de los recibos.
  - **Margen:** lo cobrado menos el costo de lo revendido (en proporción a lo que se pagó de
    cada cargo), por origen (abonos, presupuestos, órdenes, cargos manuales), lo que quedó a
    cuenta y los cobros de reventas sin costo cargado.
  - **Deuda por antigüedad:** saldos pendientes de hoy por cliente, en tramos de atraso
    (a vencer, 1–30, 31–60, 61–90 y más de 90 días), y el listado de cargos vencidos.
  - **Clientes:** ranking por lo cobrado en el período, con lo facturado y lo que debe hoy cada uno.
  - **Abonos:** abonos vigentes, mensual de los fijos, lo cobrado y lo adeudado de cada abono,
    variables a confirmar y altas y bajas del período.
  - **Taller:** órdenes ingresadas y terminadas, horas cargadas, días promedio de resolución,
    service externo por proveedor (envíos, demora, costo y precio) y equipos que siguen afuera.
  - **Presupuestos:** emitidos, aceptados, tasa de aceptación y montos, por estado y en detalle.

## [2.4.0] - 2026-10-31

### Agregado

- Costo y origen de lo que se compra para revender. Los ítems de presupuesto y los
  cargos manuales (marcando "Reventa") guardan a quién se le compró y cuánto costó;
  es información interna y no se imprime en el PDF ni se envía al cliente.
- Al cargar el costo de un ítem, el precio se sugiere con un recargo (30 % por defecto,
  editable en Configuración y en cada ítem); si se cambia el precio a mano, el recargo
  se recalcula. El presupuesto muestra el costo y el margen.
- Los proveedores tienen tipo (proveedor o particular) y canal de compra (Facebook,
  MercadoLibre u otro), para registrar a quien vende un usado.
- Las reventas sin costo cargado figuran como "costo pendiente": filtro en Cargos y
  aviso en el tablero, para completarlas después.
- El catálogo se separa en pestañas, Servicios y Productos. Cada producto indica si es
  nuevo o de reventa (usado), y "Agregar" abre el formulario con el tipo de la pestaña activa.
- Los productos del catálogo guardan su costo, su proveedor habitual y notas internas
  (links, referencias). Al elegirlos en un presupuesto se copian el costo y el proveedor, y
  se muestra el recargo resultante.
- El tablero muestra "Cobrado este mes" (lo que pagaron los clientes) y "Percibido este mes"
  (lo cobrado menos el costo de lo que se revendió), con aviso de las ventas sin costo cargado.

### Cambiado

- Presupuestos: los totales y los botones Crear/Guardar y Cancelar quedan flotando al pie de
  la pantalla, y ya no está "Crear y crear otro". Condiciones, Cuotas y los datos de Compra
  de cada ítem se ven plegados y se abren cuando hacen falta.
- Presupuestos: al editar, cada ítem se ve cerrado en una sola fila (cantidad, descripción e
  importe) y se abre para modificarlo; en un presupuesto nuevo el primer ítem arranca abierto.
- Presupuestos: las filas de ítems reparten mejor el ancho para que los importes no se corten
  (en la edición y en la vista), y los datos de compra se muestran bajo cada ítem.

### Eliminado

- El campo "Título" de los presupuestos (los que ya lo tenían lo conservan en el PDF).
- La tarjeta "Presupuestos sin respuesta" del tablero.

## [2.3.0] - 2026-10-30

### Agregado

- El panel se puede instalar como aplicación (PWA) en la computadora o el celular, con
  el nombre y el logo de PEZ, accesos directos (ingreso de equipo, órdenes de trabajo y
  presupuestos) y pantallas de inicio para iPhone.
- Aviso abajo de la pantalla para instalar la app (en iPhone explica cómo agregarla a
  inicio) y opción "Instalar app" en el menú del usuario.
- Aviso "Hay una versión nueva del sistema" con botón para actualizar después de cada
  publicación.
- Pantalla "Sin conexión" cuando no hay red. Las páginas y los datos del panel nunca se
  guardan en el dispositivo: solo se guardan los archivos estáticos (estilos, scripts e íconos).

### Cambiado

- En la lista de cargos, la columna Concepto se acorta: los abonos muestran "Abono — mes año"
  y los de órdenes de trabajo "OT número", y el nombre del cliente se recorta (completo al
  pasar el mouse). El texto completo sigue en el detalle del cargo.
- El botón "Ingreso de equipo" del Escritorio ahora se llama "Crear orden de trabajo".

## [2.2.0] - 2026-10-30

### Agregado

- Edición del perfil desde el menú del usuario: nombre, email y contraseña.
- Número de versión, en pequeño, en la esquina derecha de la barra superior del panel.
- Botón "Ingreso de equipo" en el Escritorio, que abre el alta de una orden de trabajo.
- Alta de cliente en un modal desde el formulario de la orden de trabajo.
- Comprobante de ingreso de la orden de trabajo en PDF (descarga y link público firmado) y
  acción para compartirlo con el resumen por WhatsApp.

### Cambiado

- Equipos pasa al grupo "Taller" del menú lateral.
- "Trabajo" se llama ahora "Orden de trabajo" en todo el panel (la URL `/trabajos` no cambia).
- El alta de una orden de trabajo ya no pide estado ni datos de resolución (horas,
  diagnóstico, adjuntos); la fecha de ingreso es la del día en que se crea.
- Los grupos del menú lateral del panel arrancan comprimidos (se abre solo el que
  contiene la página actual).

### Eliminado

- Widget de cuenta (con el botón de salir) del Escritorio; se sigue pudiendo cerrar
  sesión desde el menú del usuario.
- Campo "Comprometido para" de la orden de trabajo (formulario, ficha y listado).

## [2.0.0] - 2026-10-30

### Agregado

- Panel de gestión con Filament 5 en `sistema.pez.com.ar` (configurable con
  `ADMIN_DOMAIN`), en la misma app que la landing. Acceso solo para usuarios creados con
  `php artisan make:filament-user` (sin registro público).
- Ingreso al panel con Google (Laravel Socialite): solo entran las cuentas de Google con
  el mismo email que un usuario existente y verificado por Google; la foto de perfil
  se usa como avatar. El ingreso con contraseña sigue disponible.
- Clientes: razón social, nombre de fantasía, CUIT validado (dígito verificador, sin
  duplicados), condición frente al IVA, contacto, notas y estado activo. Las bajas son
  lógicas (se pueden restaurar). Contactos por cliente con un único contacto principal.
- Catálogo de servicios con unidad (hora, unidad, mes, servicio) y precio base en ARS.
  Montos guardados en centavos (`App\Casts\Money`) para evitar errores de redondeo.
- Presupuestos numerados desde `P-00100` (sin reutilizar números) con título, ítems con
  concepto y detalle (del catálogo o a mano, cantidades con decimales), ítems en $0
  como "SIN CARGO", descuento, total como precio final (sin IVA discriminado),
  condiciones (garantía, forma de pago, plazo de entrega, observaciones) y nota al pie.
- Estados de presupuesto: borrador, enviado, aceptado, rechazado y vencido. Los
  aceptados, rechazados y vencidos quedan cerrados; para cambiarlos se duplican. El
  comando diario `quotes:expire` marca como vencidos los enviados cuya validez pasó.
- Cuotas opcionales en los presupuestos: al activar "Ofrecer cuotas" se proponen los
  planes de la configuración, editables en cada presupuesto. Cada presupuesto guarda el
  total financiado y el importe de cada cuota, así un cambio posterior de recargos no
  altera los ya hechos.
- PDF del presupuesto con el diseño de PEZ (logo, tipografía Lato embebida con
  licencia OFL en `resources/fonts/lato`, cuotas junto al total, pie con frase,
  responsable y contacto), descarga desde el panel y archivo del PDF tal como se envió.
- Compartir el presupuesto por WhatsApp con el mensaje escrito y un link firmado al PDF
  que el cliente abre sin usuario. Envío alternativo por mail con el PDF adjunto, o
  "Marcar como enviado" para los que se entregan por otro medio.
- Equipos de cada cliente (tipo, identificación, marca, modelo, número de serie, notas,
  fotos opcionales) con historial de trabajos y envíos a service. Lectura del número de
  serie con la cámara (códigos de barras y QR; requiere HTTPS).
- Trabajos numerados desde `T-00001`: cliente, equipos (se pueden dar de alta al
  recibirlos), presupuesto de origen, pedido, diagnóstico, horas, fechas, fotos de
  recepción, adjuntos y estados pendiente → en curso → esperando service externo →
  terminado → entregado. "Convertir en trabajo" en los presupuestos aceptados.
- Proveedores y envíos a service externo (equipo, falla, número de orden del proveedor,
  fechas, costo y precio al cliente). Al enviar un equipo el trabajo pasa a "esperando
  service externo" y vuelve a "en curso" cuando no queda nada afuera.
- Cobranza: cuenta corriente por cliente con cargos (manuales, de presupuestos aceptados,
  de trabajos y de abonos) y pagos con medio, referencia y comprobante. Los pagos se
  imputan a los cargos elegidos o a los más antiguos; los parciales dejan el cargo en
  "pago parcial" y lo que sobra queda como saldo a favor para el próximo cargo. Anular
  un cargo devuelve sus pagos como saldo a favor y borrar un pago reabre sus cargos.
  Vencimiento según el plazo de pago configurado; los vencidos se marcan en rojo.
- Abonos mensuales fijos y variables con día de facturación y vigencia. El comando
  diario `subscriptions:bill` genera el cargo del mes: los fijos quedan pendientes y los
  variables como borrador con los trabajos terminados el mes anterior, para ajustar el
  monto y confirmarlo.
- Recibo de pago en PDF numerado desde `R-00001`, con los cargos que cancela, el saldo
  a cuenta, el medio de pago y el monto en letras. Se descarga o se envía por WhatsApp
  con un link firmado; al registrar un pago, el aviso ofrece enviarlo.
- Ficha del cliente con saldo (debe / a favor), vencido, "Registrar pago" y pestañas de
  contactos, equipos, presupuestos, trabajos, cuenta corriente, pagos y abonos.
- Escritorio con por cobrar, vencido, cobrado en el mes, abonos a confirmar,
  presupuestos sin respuesta, trabajos abiertos, equipos en service externo y la lista
  de cargos vencidos.
- Página Sistema → Configuración con los datos de la empresa, los planes de cuotas y su
  recargo, el plazo de pago de los cargos y los valores con que arranca cada
  presupuesto (estos datos no se leen del `.env`).
- Backups automáticos (`spatie/laravel-backup`): todas las noches la base de datos y los
  archivos subidos en `storage/app/backups`, con limpieza escalonada (máximo 2 GB) y aviso
  por mail si un backup falla o tiene más de un día. Página Sistema → Backups para
  verlos, descargarlos, borrarlos o hacer uno en el momento.
- Análisis estático con Larastan en nivel 6 (`composer analyse`, también en CI), sin
  baseline: los hallazgos se corrigieron en el código.
- Laravel Boost 2 (solo desarrollo): guías para agentes de IA en `AGENTS.md`, skills en
  `.claude/skills` y servidor MCP (`.mcp.json`) con consultas a la base, esquema, logs,
  último error y búsqueda en la documentación según las versiones instaladas.
- Plan del sistema en `docs/plan-sistema-admin.md`, `.env.example` y tests de todas las
  funciones.

### Cambiado

- **Laravel 13** (13.39) con PHPUnit 12, Tinker 3 y Guzzle 8. Requiere **PHP 8.3 o
  superior** (elegirlo en hPanel antes de desplegar). El panel usa el middleware
  `PreventRequestForgery`, que reemplaza a `VerifyCsrfToken`, y el caché ya no
  deserializa objetos (`serializable_classes` en `false`).
- Las rutas públicas (landing, contacto, gracias) solo responden en `APP_DOMAIN`;
  `www.pez.com.ar` debe redirigir a `pez.com.ar`.
- Idioma por defecto español y zona horaria `America/Argentina/Buenos_Aires`.
- El workflow de tests corre en los push a `master`, en PHP 8.3 y 8.4.
- Este changelog reemplaza al del esqueleto de Laravel.

### Corregido

- La app ya no falla al arrancar (por ejemplo en `composer install` del CI, antes de
  tener `.env`) cuando no hay email para los avisos de backup: usa
  `BACKUP_NOTIFICATION_EMAIL`, si no `MAIL_FROM_ADDRESS` y si no `info@pez.com.ar`.

### Eliminado

- Workflow `update-changelog` del esqueleto de Laravel (el changelog se mantiene a mano).

### Seguridad

- Dependencias actualizadas (`composer audit` pasaba de 48 avisos, uno crítico, a
  ninguno) antes de la actualización a Laravel 13.

## [1.0.0] - 2026-03-08

### Agregado

- Landing de PEZ Servicios IT con secciones de servicios IT, soluciones web y forma de trabajo.
- Formulario de contacto con envío por mail y página de gracias.
- Estilos y scripts de la landing compilados con Vite (`public/build` versionado).
