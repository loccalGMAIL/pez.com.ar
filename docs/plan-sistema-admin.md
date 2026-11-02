# Plan: sistema de gestión con Filament

Sistema interno para administrar clientes, presupuestos, trabajos, envíos a service
de terceros, abonos y cobranza. Vive en la misma app Laravel que la landing, servido
desde un subdominio con un panel de Filament.

## Decisiones tomadas

| Tema | Decisión |
|---|---|
| Framework | Laravel 12 + Filament 5 (estable actual, requiere PHP 8.2+) |
| Ubicación | Misma app, panel en subdominio (`sistema.pez.com.ar`, configurable por `.env`) |
| Usuarios | Un solo usuario administrador (sin roles/permisos por ahora) |
| Moneda | Solo ARS (montos guardados como enteros en centavos) |
| Presupuestos | Numeración desde `P-00100`, precios finales sin IVA discriminado |
| Facturación electrónica (ARCA/AFIP) | Opcional, al cobrar: Factura C (monotributo) desde el recibo; ver "Fase 9" |
| Abonos mensuales | Sí, con montos fijos y variables |
| Hosting | Hostinger compartido: SSH + Composer, **sin npm** |
| Base de datos | MySQL en producción, SQLite en memoria para tests |

## Arquitectura

### Subdominio en la misma app

- Panel Filament con `->domain(config('app.admin_domain'))->path('')`, así queda en la
  raíz del subdominio.
- Rutas públicas de `routes/web.php` envueltas en `Route::domain(config('app.domain'))`
  para que la landing y el panel no se pisen en `/`.
- Variables nuevas en `.env`: `APP_DOMAIN=pez.com.ar`, `ADMIN_DOMAIN=sistema.pez.com.ar`.
  En local: `pez.test` / `sistema.pez.test`.
- `SESSION_DOMAIN` sin punto inicial: la sesión del panel no se comparte con el sitio público.
- En hPanel, el subdominio debe apuntar al mismo `public/` de Laravel (si hPanel no
  permite elegir la carpeta, un symlink de la carpeta del subdominio a `public/`).

### Deploy sin npm

- La landing ya se compila local y se commitea `public/build` (se mantiene así).
- El lector de códigos de barras del panel se compila aparte con `npm run build:scanner`
  y se commitea en `resources/js/dist/`; `php artisan filament:assets` lo publica en
  `public/js/app/`. Así no se toca el build de la landing. La cámara requiere HTTPS.
- Filament trae sus assets precompilados: se publican con `php artisan filament:assets`
  (corre solo en `post-autoload-dump` al hacer `composer install` en el servidor).
- **No** usar un tema custom de Filament compilado con Vite; personalización vía
  colores/fuente/logo desde el `PanelProvider`, que no requiere build.
- Deploy por SSH: `git pull && composer install --no-dev -o && php artisan migrate --force
  && php artisan optimize`.

### Tareas programadas y colas

- Cron de hPanel cada minuto: `php artisan schedule:run`. Corre `quotes:expire` (00:10)
  y `subscriptions:bill` (06:15); sin el cron no se generan los cargos de los abonos.
- Sin supervisor: `QUEUE_CONNECTION=database` y el scheduler corre
  `queue:work --stop-when-empty` cada minuto (para mails y PDFs).

## Modelo de datos

```
Client ─┬─< ClientContact
        ├─< Quote ─< QuoteItem >─ ServiceItem
        ├─< WorkOrder ─< ExternalRepair >─ Supplier
        ├─< Subscription (abono)
        ├─< Charge (cargo en cuenta corriente)
        └─< Payment ─< PaymentAllocation >─ Charge
```

| Entidad | Campos principales |
|---|---|
| `Client` | razón social, nombre fantasía, CUIT, condición IVA, email, teléfono, dirección, notas, activo |
| `ClientContact` | nombre, cargo, email, teléfono, es_principal |
| `ServiceItem` | nombre, descripción, unidad (hora/unidad/mes), precio base, activo |
| `Quote` | número correlativo, cliente, fecha, vencimiento, estado, subtotal/descuento/total, notas, condiciones, PDF archivado |
| `QuoteItem` | descripción, cantidad, precio unitario, subtotal, orden, proveedor y costo unitario (internos, no se imprimen) |
| `WorkOrder` | número, cliente, presupuesto (opcional), abono (opcional), título, descripción, estado, fechas, horas, adjuntos |
| `Supplier` | nombre, tipo (proveedor/particular), canal de compra (Facebook/MercadoLibre/otro), contacto, teléfono, email, rubro |
| `ExternalRepair` | trabajo, proveedor, equipo (descripción/marca/modelo/serie), falla, nro. orden del proveedor, fecha envío, fecha retorno, costo, precio al cliente, estado |
| `Subscription` | cliente, concepto, tipo (`fijo`/`variable`), monto base, día de generación, desde/hasta, estado |
| `Charge` | cliente, origen (presupuesto/trabajo/abono/manual), período (para abonos), concepto, monto, vencimiento, estado (`borrador`/`pendiente`/`parcial`/`pagado`/`anulado`), reventa (sí/no), proveedor y costo (internos; sin costo = "costo pendiente") |
| `Payment` | cliente, fecha, monto, medio (efectivo/transferencia/MercadoPago/otro), referencia, comprobante adjunto |
| `PaymentAllocation` | pago ↔ cargo, monto aplicado (permite pagos parciales y un pago que cancela varios cargos) |

Estados:

- **Presupuesto:** borrador → enviado → aceptado / rechazado / vencido. Aceptado habilita
  "Convertir en trabajo" y/o "Generar cargo".
- **Trabajo:** pendiente → en curso → esperando service externo → terminado → entregado.
- **Service externo:** enviado → en reparación → listo → retirado / sin reparación.

### Abonos fijos y variables

- Un comando programado (`abonos:generar`, diario) crea el `Charge` del período cuando
  llega el día de generación (idempotente: un cargo por abono y período).
- **Fijo:** el cargo se crea `pendiente` con el monto base.
- **Variable:** el cargo se crea en `borrador` con el monto base como sugerencia y la
  lista de trabajos del período asociados al abono; se revisa/ajusta el monto y se
  confirma desde el panel. Un widget muestra los borradores pendientes de confirmar.

## Panel (Filament)

- **Recursos:** Clientes (con relation managers de contactos, presupuestos, trabajos,
  abonos, cuenta corriente), Catálogo, Presupuestos, Trabajos, Service externo,
  Proveedores, Abonos, Cargos, Pagos.
- **Acciones:** descargar/enviar PDF de presupuesto, duplicar presupuesto, convertir en
  trabajo, registrar pago desde la ficha del cliente, confirmar cargo variable.
- **Dashboard:** total por cobrar, cargos vencidos,
  trabajos abiertos, equipos en service externo, abonos variables por confirmar.

## Paquetes

- `filament/filament` ^5
- `barryvdh/laravel-dompdf` — PDFs de presupuestos (sin Chrome/Node, apto para hosting compartido)
- `spatie/laravel-backup` — backup diario de base y adjuntos
- `chillerlan/php-qrcode` (fase 9) — QR de ARCA en las facturas, en PNG con GD
- Más adelante: `spatie/laravel-activitylog` (auditoría), SDK de MercadoPago

## Fases

1. ✅ **Base:** instalar Filament, panel en subdominio, separación de rutas por dominio,
   usuario admin (`make:filament-user`), configuración de deploy/cron, tests de dominios.
2. ✅ **Clientes y catálogo:** modelos, migraciones, factories, recursos y tests.
3. ✅ **Presupuestos:** ítems, numeración, totales, estados, PDF archivado y envío por mail.
4. ✅ **Trabajos y service de terceros:** proveedores, seguimiento de equipos, adjuntos.
5. ✅ **Cobranza y abonos:** cargos, pagos con imputación, cuenta corriente, comando de
   abonos, dashboard.
6. ✅ **Backups e ingreso con Google:** `laravel-backup` nocturno con página de
   descarga, y login con Google vía Socialite (en lugar de la verificación en dos pasos).
7. ✅ **Actualización y herramientas:** ver sección siguiente.
8. ✅ **Informes:** ver sección "Fase 8: Informes".
9. **Facturación electrónica ARCA:** ver sección "Fase 9: Facturación electrónica ARCA".
10. **Futuro:** links de pago MercadoPago, portal de clientes.

Cada fase incluye migraciones con `down()`, factories, tests (`php artisan test`), Pint
y su entrada en `CHANGELOG.md`.

## Versionado

- Versionado semántico con tags `vX.Y.Z` en `master`; detalle en `CHANGELOG.md`.
- `1.0.0` es la landing publicada. Cada fase del sistema se publica como versión menor
  (`1.1.0` = fase 1, `1.2.0` = fase 2, …). La actualización a Laravel 13 será `2.0.0`.

## Fase 7: Laravel 13, Boost y Larastan ✅

Se encara al terminar las fases funcionales, con todos los tests en verde.

1. **Previo:** confirmar en hPanel que el sitio corre con PHP 8.3 o superior (Laravel 13
   lo exige) y subir el mínimo de `composer.json` y del workflow de tests a 8.3.
2. **Laravel 13:** seguir la guía oficial de actualización 12 → 13, actualizar
   `laravel/framework` y dependencias de desarrollo, revisar configuración publicada.
   Filament 5 y dompdf ya declaran compatibilidad con Laravel 13.
3. **Larastan** (`larastan/larastan`, dev): `phpstan.neon` empezando en nivel 5, con
   baseline para lo existente, y subir de nivel progresivamente. Agregar
   `composer analyse` y correrlo en el workflow de CI.
4. **Laravel Boost** (`laravel/boost`, dev): `php artisan boost:install` para las guías
   de Laravel/Filament para agentes y el servidor MCP (consultas a la base, rutas,
   logs y documentación por versión). Solo en desarrollo, nunca en producción.
5. Verificar en Hostinger y publicar como `2.0.0`.

## Fase 8: Informes ✅

- Grupo **Informes** en el menú del panel, con una página por informe
  (`app/Filament/Pages/Reports`). Todas comparten los filtros de período y cliente, y el
  botón "Descargar PDF".
- El cálculo vive en `app/Support/Reports` (una clase por informe, que devuelve cifras y
  tablas con los valores sin formatear). La misma clase alimenta la pantalla y el PDF
  (`pdf/report.blade.php`, con el diseño de los demás documentos), y se testea sin la interfaz.
- La plata se cuenta **por fecha de pago**: lo cobrado en el período, no lo facturado. El margen
  descuenta el costo de lo revendido en proporción a lo que se pagó de cada cargo, igual que
  "Percibido" en el tablero. La deuda por antigüedad es una foto del día y no usa el período.
- Informes: Ingresos, Margen, Deuda por antigüedad, Clientes, Abonos, Taller y Presupuestos.
- Sin paquetes nuevos ni build: la pantalla usa los componentes y las variables de color de
  Filament, y el PDF sale con dompdf.

## Fase 9: Facturación electrónica ARCA

Emisor **monotributista**: todo se emite como **Factura C** (código 11) y se anula con
**Nota de crédito C** (código 13). Sin IVA discriminado: `ImpNeto = ImpTotal`, `ImpIVA = 0`
y sin alícuotas.

### Cuándo se factura

La factura es **opcional**. Por defecto se emite **al cobrar**, desde el recibo; para los
clientes que necesitan la factura para pagar, **antes de cobrar**, desde el cargo. Cargos y
recibos siguen igual que hoy; la factura es un documento aparte que se suma cuando hace falta.

- **Al cobrar:** acción "Facturar en ARCA" en el recibo (vista, tabla de Pagos y pagos del
  cliente). Abre una vista previa editable (ítems, concepto, período) y recién al confirmar
  pide el CAE.
- **Antes de cobrar (hecho):** check "Facturar antes de cobrar" en el abono. Sus cargos quedan
  "Para facturar" (columna, filtro y tablero) hasta que se usa "Facturar cargo", con la misma
  vista previa; vence cuando vence el cargo. "Facturar cargo" sirve para cualquier cargo.
- **Nunca dos veces:** cada ítem guarda el cargo que factura. Lo facturado de un cargo es la
  suma de sus ítems en facturas vigentes, más lo que recibió de pagos facturados con saldo "a
  cuenta". El recibo factura de cada cargo solo lo que le falta, y el cargo, lo que le falta.
- **Por cliente (pendiente):** campo "Facturación" en el cliente: `nunca` / `a pedido` (por defecto) /
  `siempre`. Con `siempre`, registrar un pago emite la factura en la cola; si ARCA falla, el
  pago queda registrado igual y el recibo muestra "Factura pendiente" con el error y un
  botón para reintentar. Con `nunca`, la acción se oculta.
- **Interruptor general** en Configuración: con la facturación apagada no aparece nada de ARCA.
- Recibos y presupuestos llevan la leyenda "Documento no válido como factura"; el recibo
  facturado muestra además el número de su factura.

### Qué se factura

- **Ítems:** uno por cada imputación del recibo (concepto del cargo e importe aplicado; "pago
  parcial de $X" si no lo cancela) y "Pago a cuenta" por lo que quedó sin imputar, sin lo que
  ya estaba facturado. Desde el cargo, un ítem por lo que le falta facturar.
- **Concepto ARCA:** productos (1) si todos los cargos son reventa, servicios (2) si ninguno,
  ambos (3) si hay de los dos. Editable en la vista previa.
- **Período del servicio** (obligatorio con servicios): el mes del abono si el cargo viene de
  uno; si no, la fecha de emisión de los cargos. **Vencimiento del pago:** desde el recibo, la
  fecha de la factura (ya está cobrada); desde el cargo, el vencimiento del cargo (nunca antes
  de la fecha de la factura).
- **Fecha:** desde el recibo, la del pago si ARCA la acepta (hasta 10 días atrás con
  servicios, 5 con productos); si no, y desde el cargo o la nota de crédito, la de hoy.
- **Receptor:** se copian sus datos a la factura al emitir. Documento: CUIT (80), si no DNI
  (96), si no consumidor final sin identificar (99 / 0, solo por debajo del tope vigente de
  ARCA). Condición IVA del receptor (RG 5616) desde `TaxCondition`: responsable inscripto 1,
  exento 4, consumidor final 5, monotributista 6.

### Modelo de datos

| Entidad | Campos principales |
|---|---|
| `Invoice` | cliente, origen (recibo o cargo), tipo (11/13), punto de venta, número, fecha, concepto, período desde/hasta, vencimiento del pago, receptor copiado (documento, condición IVA, razón social, domicilio), total, estado, CAE y su vencimiento, factura que anula (en las notas de crédito), respuesta de ARCA, errores y observaciones, PDF archivado |
| `InvoiceItem` | cargo que factura (vacío en "Pago a cuenta"), descripción, aclaración, importe, orden |
| `Client` | + DNI |
| `Subscription` | + facturar antes de cobrar |

- Estados: **pendiente → autorizada / rechazada**. Una factura autorizada no se edita ni se
  borra; una rechazada guarda el motivo y se puede corregir y reintentar.
- **Nota de crédito C (hecha):** "Anular con nota de crédito" en la página de la factura emite
  la NC por el total, asociada (`CbtesAsoc`), con los mismos ítems, y libera lo que facturaba.
  Un pago con factura vigente no se borra hasta anularla, y borrar un pago nunca borra una
  factura emitida. Anular un cargo facturado desde el cargo emite antes su NC (si ARCA no la
  autoriza, el cargo queda); si se facturó junto con su pago, se anula desde esa factura.

### Integración

- Cliente propio, sin paquetes, en `app/Support/Arca`: SOAP armado a mano sobre el cliente
  HTTP de Laravel (`Soap`), sin depender de `ext-soap`, y `openssl` para firmar.
  - **WSAA:** firma el pedido de acceso (CMS) con el certificado; el token y la firma
    duran 12 h y se guardan en caché.
  - **WSFEv1:** `FECompUltimoAutorizado`, `FECAESolicitar`, `FECompConsultar` y `FEDummy`
    (estado de los servidores, para "Probar conexión").
  - Los tests usan `Http::fake()` con respuestas de ARCA (`tests/Concerns/FakesArca.php`) y
    un certificado autofirmado: nunca llaman a ARCA.
- `InvoiceIssuer` emite: lock por punto de venta (`Cache::lock`), último número + 1, pedido
  del CAE. Ante un timeout **consulta con `FECompConsultar` antes de reintentar**, para no
  duplicar comprobantes. Guarda pedido, respuesta y observaciones tal como vienen.
- Los servidores de ARCA usan claves DH que OpenSSL 3 rechaza ("dh key too small"): la
  conexión SOAP baja el nivel de seguridad (`DEFAULT@SECLEVEL=1`) solo para esos hosts.
- `.env`: `ARCA_ENV` (`homologacion` / `produccion`). El certificado se genera e instala
  desde el panel (`App\Support\Arca\Certificate`), uno por ambiente en
  `storage/app/private/arca/{ambiente}/`; `ARCA_CERT_PATH` y `ARCA_KEY_PATH` solo para
  usar otra ubicación. El CUIT emisor es el de la empresa en Configuración. Certificado y
  clave nunca van al repo, a la base ni a los backups.
- Cada factura guarda su ambiente: las de homologación no se mezclan con las reales ni en la
  numeración ni en el listado.
- Configuración (panel): facturación activa, punto de venta, nombre, Ingresos Brutos, inicio
  de actividades y domicilio comercial; botón "Probar conexión" con ARCA. La categoría de
  monotributo y su tope llegan con la 2.7.0.

### La factura

Mismo diseño que presupuestos y recibos (`pdf/layout.blade.php`: encabezado oscuro, verde,
Lato, logo), con los datos que exige ARCA integrados:

- Encabezado: recuadro con la letra **C** y "COD. 11" al centro; N.º `00003-00000125`,
  fecha, CUIT, IIBB e inicio de actividades.
- Emisor y receptor: razón social, domicilio, condición IVA (emisor "Responsable
  Monotributo") y documento; período facturado y vencimiento del pago.
- Ítems y caja de total como en los presupuestos.
- Pie fiscal: **QR de ARCA** (RG 4291, JSON en base64 en `https://www.afip.gob.ar/fe/qr/`),
  CAE, vencimiento del CAE y "Comprobante autorizado".
- El PDF se archiva al emitir, se descarga desde el panel y se envía por mail con link
  firmado (`/factura/{invoice}`), como los recibos.

### Entregas

- **2.6.0** (hecha, falta probar en homologación): cliente ARCA y certificado desde el panel,
  configuración fiscal, factura desde el recibo y desde el cargo (abonos que se facturan antes
  de cobrar) con vista previa, notas de crédito, página de la factura, PDF con QR, mail y
  WhatsApp, leyendas en recibos y presupuestos y listado de facturas.
- **Siguiente:** facturación automática por cliente con reintento, informe de facturación
  (facturado vs. cobrado sin facturar) y aviso en el tablero de lo facturado en los últimos
  12 meses contra el tope de la categoría.

Tests de cada parte con ARCA falso: numeración y lock, reconciliación ante
timeout, rechazo con errores, armado de ítems desde las imputaciones, concepto y período,
receptor y condición IVA, QR, PDF y bloqueo de borrado.

### Puesta en marcha de ARCA

El certificado se maneja desde **Configuración → Facturación → Certificado de ARCA**: el
panel crea la clave privada en el servidor (nunca sale de ahí) y descarga el pedido (`.csr`);
después se sube el `.crt` que entrega ARCA, y el panel verifica que sea para el CUIT de la
empresa, que esté vigente y que corresponda al pedido. Hasta instalar el nuevo sigue
funcionando el anterior. Hay un certificado por ambiente, en
`storage/app/private/arca/{homologacion|produccion}/` (excluido del backup).

1. En Hostinger, verificar que `openssl`, `curl` y `gd` estén activos (`php -m`; ya
   verificado).
2. Deploy (`git pull`, `composer install --no-dev -o`, `php artisan migrate --force`,
   `php artisan optimize`) y en Configuración → Empresa cargar y guardar el CUIT.
3. **Homologación** (`ARCA_ENV=homologacion`, el valor por defecto):
   1. Configuración → Facturación → "Generar pedido": se descarga `pezsistema.csr`.
   2. Con clave fiscal, adherir "WSASS – Autogestión Certificados Homologación"; ahí crear el
      certificado pegando el `.csr` y autorizarlo al servicio `wsfe`.
   3. "Subir certificado" con el `.crt` (o pegando el texto), activar la facturación con
      cualquier punto de venta (por ejemplo 1), guardar y "Probar conexión".
   4. Facturar recibos de prueba: consumidor final, con DNI y responsable inscripto.
4. **Producción:**
   1. En el `.env`, `ARCA_ENV=produccion` y `php artisan optimize`.
   2. "Generar pedido" (ahora es para producción).
   3. En ARCA, "Administración de Certificados Digitales": crear el alias, subir el `.csr` y
      bajar el `.crt`. En "Administrador de Relaciones de Clave Fiscal": nueva relación con
      el servicio "Facturación Electrónica" para ese certificado (computador fiscal).
   4. En "Administración de puntos de venta y domicilios", un punto de venta **nuevo**
      "Factura Electrónica - Monotributo - Web Services" (distinto del de Comprobantes en
      línea).
   5. "Subir certificado", cargar el punto de venta nuevo, guardar y "Probar conexión". La
      primera factura real conviene que sea chica, y verificar su QR en el sitio de ARCA.
5. No correr `php artisan cache:clear` con la facturación en uso: el acceso de ARCA (12 h)
   vive en la caché y, si se borra, ARCA no entrega otro hasta que venza.

## Puesta en marcha en Hostinger (fase 1)

1. En hPanel → Dominios → Subdominios, crear `sistema.pez.com.ar` apuntando a la misma
   carpeta `public/` de Laravel que usa `pez.com.ar` (o symlink de la carpeta que cree
   hPanel hacia `public/`).
2. En el `.env` del servidor:
   ```
   APP_DOMAIN=pez.com.ar
   ADMIN_DOMAIN=sistema.pez.com.ar
   SESSION_DOMAIN=null
   ```
3. Para ingresar con Google: en Google Cloud Console crear un "ID de cliente OAuth"
   (aplicación web) con la URI de redirección
   `https://sistema.pez.com.ar/auth/google/callback`, y cargar `GOOGLE_CLIENT_ID` y
   `GOOGLE_CLIENT_SECRET` en el `.env`. El usuario debe existir con el mismo email.
4. Por SSH, en la carpeta del proyecto:
   ```
   git pull
   composer install --no-dev -o   # también publica los assets de Filament
   php artisan migrate --force
   php artisan make:filament-user   # solo la primera vez
   php artisan optimize
   ```
5. Probar el backup una vez: `php artisan backup:run` (usa `mysqldump`; si Hostinger lo
   tiene en otra ruta, se configura en `config/database.php` → `dump.dump_binary_path`).
6. Entrar a `https://sistema.pez.com.ar` (SSL del subdominio activado en hPanel) y
   completar los datos de la empresa en Sistema → Configuración.

Si se usa `www.pez.com.ar`, debe redirigir a `pez.com.ar`: las rutas públicas solo
responden en `APP_DOMAIN`.
