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

- Panel de gestión con Filament 5 en `sistema.pez.com.ar` (configurable con `ADMIN_DOMAIN`).
- Acceso al panel para usuarios creados con `php artisan make:filament-user` (sin registro público).
- Librería `barryvdh/laravel-dompdf` para los PDF de presupuestos.
- Plan del sistema de gestión en `docs/plan-sistema-admin.md`.
- `.env.example` con la configuración de dominios, idioma y zona horaria.
- Tests de separación de dominios entre el sitio público y el panel.

### Cambiado

- Las rutas públicas (landing, contacto, gracias) solo responden en `APP_DOMAIN`;
  `www.pez.com.ar` debe redirigir a `pez.com.ar`.
- Idioma por defecto español y zona horaria `America/Argentina/Buenos_Aires`.
- El workflow de tests corre en los push a `main`.
- Este changelog reemplaza al del esqueleto de Laravel.

### Eliminado

- Workflow `update-changelog` del esqueleto de Laravel (el changelog se mantiene a mano).

## [1.0.0] - 2026-03-08

### Agregado

- Landing de PEZ Servicios IT con secciones de servicios IT, soluciones web y forma de trabajo.
- Formulario de contacto con envío por mail y página de gracias.
- Estilos y scripts de la landing compilados con Vite (`public/build` versionado).
