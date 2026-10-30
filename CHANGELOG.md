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
