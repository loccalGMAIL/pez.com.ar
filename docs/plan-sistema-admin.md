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
| Facturación electrónica (ARCA/AFIP) | Más adelante; por ahora cobranza interna |
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
| `QuoteItem` | descripción, cantidad, precio unitario, subtotal, orden |
| `WorkOrder` | número, cliente, presupuesto (opcional), abono (opcional), título, descripción, estado, fechas, horas, adjuntos |
| `Supplier` | nombre, contacto, teléfono, email, rubro |
| `ExternalRepair` | trabajo, proveedor, equipo (descripción/marca/modelo/serie), falla, nro. orden del proveedor, fecha envío, fecha retorno, costo, precio al cliente, estado |
| `Subscription` | cliente, concepto, tipo (`fijo`/`variable`), monto base, día de generación, desde/hasta, estado |
| `Charge` | cliente, origen (presupuesto/trabajo/abono/manual), período (para abonos), concepto, monto, vencimiento, estado (`borrador`/`pendiente`/`parcial`/`pagado`/`anulado`) |
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
- **Dashboard:** total por cobrar, cargos vencidos, presupuestos enviados sin respuesta,
  trabajos abiertos, equipos en service externo, abonos variables por confirmar.

## Paquetes

- `filament/filament` ^5
- `barryvdh/laravel-dompdf` — PDFs de presupuestos (sin Chrome/Node, apto para hosting compartido)
- `spatie/laravel-backup` — backup diario de base y adjuntos
- Más adelante: `spatie/laravel-activitylog` (auditoría), SDK de MercadoPago, facturación ARCA

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
8. **Futuro:** facturación electrónica, links de pago MercadoPago, portal de clientes.

Cada fase incluye migraciones con `down()`, factories, tests (`php artisan test`), Pint
y su entrada en `CHANGELOG.md`.

## Versionado

- Versionado semántico con tags `vX.Y.Z` en `main`; detalle en `CHANGELOG.md`.
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
