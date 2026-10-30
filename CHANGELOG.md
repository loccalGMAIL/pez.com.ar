# Changelog

Todos los cambios relevantes del proyecto se registran en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
proyecto usa [Versionado Semántico](https://semver.org/lang/es/):

- **MAYOR** (`2.0.0`): cambios que rompen algo existente (por ejemplo, la actualización a Laravel 13).
- **MENOR** (`1.1.0`): funcionalidades nuevas (cada fase del sistema de gestión).
- **PARCHE** (`1.0.1`): correcciones sin funcionalidad nueva.

Cada versión publicada lleva un tag `vX.Y.Z` en `main`.

## [Sin publicar]

### Agregado

- Fotos opcionales de los equipos y fotos de recepción en los trabajos (estado en que
  llega el equipo). Se achican en el navegador antes de subir y se guardan en privado;
  desde el celular se pueden sacar con la cámara.
- Lectura del número de serie con la cámara (códigos de barras Code 128/39/93, EAN, UPC,
  ITF, QR y DataMatrix). El lector se descarga solo al usarlo y requiere HTTPS.
- Alta de un equipo nuevo desde el mismo formulario del trabajo, al recibirlo.
- Equipos de cada cliente (tipo, identificación, marca, modelo, número de serie, notas)
  con historial de trabajos y envíos a service.
- Trabajos numerados desde `T-00001`: cliente, equipos, presupuesto de origen, pedido,
  diagnóstico, horas, fechas de ingreso y compromiso, adjuntos privados (fotos, informes)
  y estados pendiente → en curso → esperando service externo → terminado → entregado,
  con fecha de terminado y entregado automática.
- "Convertir en trabajo" en los presupuestos aceptados (uno por presupuesto).
- Proveedores de service y envíos a service externo: equipo, falla, número de orden del
  proveedor, fechas, costo y precio al cliente. Al enviar un equipo el trabajo pasa a
  "esperando service externo" y vuelve a "en curso" cuando no queda nada afuera.
- Menú agrupado en Ventas y Taller, con contadores de trabajos abiertos y equipos afuera.
- Presupuestos: numeración correlativa desde `P-00100` (sin reutilizar números), ítems
  cargados del catálogo o a mano, cantidades con decimales, descuento y totales como
  precio final (sin IVA discriminado), observaciones y condiciones.
- Estados de presupuesto: borrador, enviado, aceptado, rechazado y vencido. Los
  aceptados, rechazados y vencidos quedan cerrados; para cambiarlos se duplican.
- PDF del presupuesto con los datos de la empresa (`COMPANY_*` en `.env`), descarga
  desde el panel y archivo del PDF tal como se envió.
- Compartir el presupuesto por WhatsApp: abre WhatsApp con el mensaje escrito (número,
  total, validez) y un link firmado al PDF, que el cliente abre sin usuario. El número
  se toma del cliente o de su contacto principal; si no se puede interpretar, WhatsApp
  pide elegir el contacto.
- Envío alternativo por mail con el PDF adjunto, o "Marcar como enviado" para los que
  se entregan por otro medio.
- Comando `quotes:expire`, programado a diario, que marca como vencidos los presupuestos
  enviados cuya validez pasó (requiere el cron de `schedule:run`).
- Presupuestos del cliente en su ficha y contador de enviados sin respuesta en el menú.
- Clientes: razón social, nombre de fantasía, CUIT validado (dígito verificador, sin
  duplicados), condición frente al IVA, datos de contacto, notas y estado activo.
  Las bajas son lógicas (se pueden restaurar).
- Contactos por cliente, con un único contacto principal.
- Catálogo de servicios con unidad (hora, unidad, mes, servicio) y precio base en ARS.
- Montos guardados en centavos (`App\Casts\Money`) para evitar errores de redondeo.
- Panel de gestión con Filament 5 en `sistema.pez.com.ar` (configurable con `ADMIN_DOMAIN`).
- Acceso al panel para usuarios creados con `php artisan make:filament-user` (sin registro público).
- Librería `barryvdh/laravel-dompdf` para los PDF de presupuestos.
- Plan del sistema de gestión en `docs/plan-sistema-admin.md`.
- `.env.example` con la configuración de dominios, idioma y zona horaria.
- Tests de separación de dominios entre el sitio público y el panel.

### Cambiado

- PDF del presupuesto rediseñado según el modelo de PEZ: encabezado con logo, título,
  número, fecha y validez en días; datos del cliente; ítems con concepto y detalle; ítems
  en $0 destacados como "SIN CARGO"; total destacado; condiciones (garantía, forma de
  pago, plazo de entrega, observaciones) y pie con frase, responsable y contacto.
  Tipografía Lato embebida (licencia OFL en `resources/fonts/lato`).
- Cuotas opcionales en los presupuestos: al activar "Ofrecer cuotas" se proponen los
  planes de `QUOTES_DEFAULT_INSTALLMENTS` (por defecto 3, 6 y 12 sin recargo), cada uno
  con su recargo editable. El formulario muestra el valor de la cuota y el PDF lista
  los planes junto al total.
- Presupuestos con título, concepto por ítem (se completa desde el catálogo) y
  condiciones con valores por defecto configurables (`QUOTES_DEFAULT_*`). Validez por
  defecto: 7 días.
- URLs del panel en español: `/clientes`, `/equipos`, `/catalogo`, `/presupuestos`,
  `/trabajos`, `/service-externo`, `/proveedores`.
- Las rutas públicas (landing, contacto, gracias) solo responden en `APP_DOMAIN`;
  `www.pez.com.ar` debe redirigir a `pez.com.ar`.
- Idioma por defecto español y zona horaria `America/Argentina/Buenos_Aires`.
- El workflow de tests corre en los push a `main`.
- Este changelog reemplaza al del esqueleto de Laravel.

### Eliminado

- Workflow `update-changelog` del esqueleto de Laravel (el changelog se mantiene a mano).

### Seguridad

- Dependencias actualizadas dentro de Laravel 12 (`laravel/framework` 12.70, Symfony 7.4,
  Guzzle 7.16, CommonMark 2.10, entre otras): `composer audit` pasa de 48 avisos (uno
  crítico) a ninguno.

## [1.0.0] - 2026-03-08

### Agregado

- Landing de PEZ Servicios IT con secciones de servicios IT, soluciones web y forma de trabajo.
- Formulario de contacto con envío por mail y página de gracias.
- Estilos y scripts de la landing compilados con Vite (`public/build` versionado).
