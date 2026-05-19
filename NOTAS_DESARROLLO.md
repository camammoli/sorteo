# Sorteador de YouTube — Notas de desarrollo

## Stack y deploy

- **Backend:** PHP 8 + SQLite (PDO, WAL mode), sin framework
- **Frontend:** HTML/CSS/JS vanilla — sin dependencias externas
- **API:** YouTube Data API v3 (key en `web/config.php`, excluido del repo)
- **Streaming:** Server-Sent Events (SSE) para descarga en tiempo real
- **Deploy:** `lftp` a `/sorteo/` en mammoli.ar (FTP directo, no `/public_html/`)
- **Repo:** `camammoli/sorteo` (privado en GitHub)
- **URL pública:** https://mammoli.ar/sorteo/

## Archivos principales

| Archivo | Función |
|---|---|
| `web/index.php` | UI completa (4 estados: form / fetching / ready / winners) |
| `web/api.php` | API JSON: `create`, `sortear`, `get` |
| `web/fetch.php` | SSE endpoint — descarga comentarios de YouTube |
| `web/db.php` | SQLite: schema, CRUD, migraciones inline |
| `web/certificate.php` | Certificado imprimible con QR de verificación |
| `web/verificar.php` | Verificación pública de certificados |
| `web/config.php` | `YT_API_KEY`, `SORTEO_ADMIN_KEY`, `SORTEO_HMAC_SECRET` — excluido de git (`.gitignore`) |
| `web/config.example.php` | Plantilla de config.php con placeholders — incluida en git |
| `web/data/sorteo.db` | Base SQLite — excluida de git |

## Base de datos

```sql
sorteos   (id UUID, video_id, video_ids JSON, video_title, video_thumb,
           video_comment_count, options JSON, status, total_fetched, created_at)
comments  (id, sorteo_id, comment_id, author, author_id, text,
           published_at, is_reply, like_count, source_video_id)
winners   (id, sorteo_id, comment_rowid, position, is_backup, drawn_at)
```

- Los sorteos y sus comentarios se eliminan automáticamente a los **7 días**
- `status`: `pending` → `fetching` → `ready` → `done`

## Funcionalidades

- Hasta **5 videos por sorteo** (comentarios combinados)
- Límite configurable: 1k / 5k / 10k / 50k / sin límite
- Filtro por palabra clave, fecha desde/hasta, mínimo de likes
- Máximo de entradas por usuario (1 sin dup · 2 · 3 · 5 · sin límite)
- Exclusión de usuarios por nombre (auto-detecta dueño del canal)
- Ganadores suplentes (hasta 20)
- **Modo festejo:** dados animados al sortear + confetti al revelar ganadores
- Certificado imprimible con QR de verificación de autenticidad
- Página de verificación pública (`/sorteo/verificar.php`)
- Idioma ES/EN + tema claro/oscuro (persistido en localStorage)
- URL compartible (`?v=UUID`)

## Lógica de sorteo (api.php + db.php)

1. `create` → valida URLs, llama YouTube API para metadatos, guarda en DB
2. `fetch.php` (SSE) → pagina `commentThreads.list` (100/página), inserta en batches de 500
3. `sortear` → `get_eligible_rowids()` filtra en SQLite + cap por usuario en PHP
   - `$video_total` (por video) se usa para el límite, no `$total` global — permite multi-video
4. `shuffle()` + `array_slice()` → ganadores + suplentes
5. `save_winners()` → guarda con `is_backup 0/1`

## Verificación de certificados

Desde v2.3 se usa **HMAC-SHA256** con clave secreta del servidor (antes era MD5 simple).

### Hash actual (v2.3+)

```php
$hmac_data = implode('|', [
    $id,                                              // UUID del sorteo
    $sorteo['video_id'],                              // ID del video YouTube
    $drawn_at_raw,                                    // fecha UTC tal como está en la BD
    implode(',', array_column($winners, 'comment_id')),
    implode(',', array_column($winners, 'author')),
]);
$hash = strtoupper(substr(hash_hmac('sha256', $hmac_data, SORTEO_HMAC_SECRET), 0, 32));
```

- Se calcula en `certificate.php` y se muestra como texto + QR (32 chars hex)
- El QR apunta a `verificar.php?v=UUID&h=HASH&lang=…`
- `verificar.php` recalcula el HMAC desde la DB y compara
- Sin `SORTEO_HMAC_SECRET` es imposible generar un hash válido
- Los certificados con hash de 16 chars (MD5 legacy) siguen verificando con el método anterior

### Compatibilidad legacy

`verificar.php` detecta la longitud del hash:
- 32 chars → verifica con HMAC-SHA256
- 16 chars → verifica con MD5 (certificados emitidos antes de v2.3), muestra aviso

### Lo que la verificación garantiza y lo que no

- **Garantiza:** el sorteo existe en el servidor con esos ganadores exactos
- **No garantiza:** que el documento impreso o PDF no fue editado (el QR es una imagen fija)
- **Mitigación:** la página de verificación muestra los datos del servidor para comparación visual

### Generar las claves para config.php

**YouTube Data API v3:**
1. Ir a https://console.cloud.google.com → Crear proyecto
2. Habilitar "YouTube Data API v3"
3. Crear credencial → Clave de API → restringir a la API de YouTube
4. Pegar en `config.php` como `YT_API_KEY`

**SORTEO_HMAC_SECRET** (clave de firma):
```bash
openssl rand -hex 32
```
Pegar el resultado en `config.php` como `SORTEO_HMAC_SECRET`. Nunca compartirla ni subirla al repo.

**SORTEO_ADMIN_KEY** (panel admin):
```bash
openssl rand -hex 16
```
Acceso por URL: `mammoli.ar/sorteo/admin.php?key=VALOR`

## Historial de versiones

### v2.7 — 2026-05-19 (Claude Code)

**Integridad del certificado:**
- `index.php`: al hacer click en "Certificado PDF" se deshabilita el botón "Sortear de nuevo" para preservar la integridad del certificado emitido — no se puede re-sortear una vez que el certificado fue abierto

### v2.6 — 2026-05-19 (Claude Code)

**Panel de bloqueo:**
- Panel de bloqueo movido de `index.php` a `certificate.php`
- Solo visible si `localStorage.sorteoLockCandidate` contiene el UUID del certificado actual con antigüedad menor a 8 horas — garantiza que solo quien hizo el sorteo en esa sesión puede bloquear
- `index.php`: guarda el UUID en `localStorage` al momento de abrir el certificado (no antes)
- `certificate.php`: JS lee el localStorage, consulta `api.php?action=get_lock` y muestra el panel en estado activo o disponible; `location.reload()` al bloquear/desbloquear para actualizar el badge del PHP

### v2.5 — 2026-05-19 (Claude Code)

**Sistema de bloqueo de conjunto de videos (sorteo oficial):**
- `db.php`: tabla `sorteo_locks` (sorteo_id, video_set normalizado, locked_until, created_at) + funciones `get_active_lock()`, `create_lock()`, `delete_lock()`, `video_set_from_sorteo()`
- `api.php`: endpoint `get_lock` — devuelve estado del lock y si la sesión es propietaria; endpoint `lock` — crea el lock (1/3/7/30 días); endpoint `unlock` — elimina el lock del sorteo; en `create` — rechaza con HTTP 403 y `code: LOCKED` si el conjunto está bloqueado
- `index.php`: strings ES/EN para el panel de bloqueo (luego movidos al certificado en v2.6)
- `certificate.php`: badge verde "✓ Sorteo oficial — hasta [fecha]" si el lock activo pertenece a este sorteo; banner rojo si hay otro sorteo marcado como oficial para el mismo conjunto; el aviso de sorteo múltiple (#N) solo aparece cuando no hay lock activo
- `verificar.php`: badge "✓ oficial" en la lista de sorteos del mismo conjunto cuando hay lock activo

**Notas técnicas:**
- El video_set se normaliza ordenando los IDs alfabéticamente antes de guardar/comparar — sorteos con los mismos videos en distinto orden se consideran el mismo conjunto
- El lock vence automáticamente; al vencer, el comportamiento vuelve al neutro (lista sin juicio)
- El lock solo puede crearlo el propietario de la sesión (quien abrió el certificado desde el sorteador); cualquiera con el UUID podría llamar a la API directamente, pero el modelo de amenaza es suficiente para el uso real

### v2.4 — 2026-05-19 (Claude Code)

**Transparencia de sorteos múltiples:**
- `db.php`: `get_sorteos_mismo_conjunto()` — normaliza video_ids (orden alfabético) y devuelve todos los sorteos `done` del mismo conjunto ordenados cronológicamente
- `certificate.php`: muestra N.º de sorteo (#1, #2…) en los detalles; si N > 1, aviso amarillo visible
- `verificar.php`: lista todos los sorteos del mismo conjunto con fecha UTC y link al certificado de cada uno; el actual destacado en azul

### v2.3 — 2026-05-19 (Claude Code)

**Seguridad y certificados:**
- Certificados: hora del sorteo en UTC (`gmdate`) — antes variaba según la TZ del visitante
- Certificados: hash de verificación cambiado de MD5 (16 chars) a HMAC-SHA256 (32 chars), firmado con `SORTEO_HMAC_SECRET` — cubre id, video, fecha, comment_ids y autores; no recalculable sin la clave del servidor
- `verificar.php`: soporte dual — verifica HMAC nuevo (32 chars) o MD5 legacy (16 chars)
- `verificar.php`: datos del servidor visibles también en estado `tampered` para comparación visual
- `verificar.php`: mensaje cambiado de "Certificado auténtico" a "Certificado encontrado — verificá los ganadores" para no dar falsa sensación de integridad del documento impreso
- `config.example.php`: agrega `SORTEO_HMAC_SECRET`

**Notas técnicas:**
- `SORTEO_HMAC_SECRET` debe generarse con `openssl rand -hex 32` y agregarse manualmente al `config.php` del servidor
- Los certificados emitidos antes de v2.3 siguen siendo verificables (modo legacy MD5)

### v2.2 — 2026-05-18 (Claude Code)

**Funcionalidades nuevas:**
- Contador público de sorteos realizados en el header de `index.php` (PHP query en page load)
- Panel admin secreto `admin.php`: tabla completa con ID, canal, video, IP hash parcial, opciones, estado, comentarios descargados, fecha — paginado de 50, protegido por `?key=SORTEO_ADMIN_KEY`
- Notificación Telegram mejorada: incluye sorteo ID y link directo al panel admin
- Zona horaria dinámica: JS detecta `Intl.DateTimeFormat().resolvedOptions().timeZone` → cookie `sorteo_tz` → `date_default_timezone_set()` en `db.php`
- Footer de `stats.php`: agrega "Desarrollado por Carlos Mammoli"
- `SORTEO_ADMIN_KEY` en `config.php` (y `config.example.php`)

**Notas técnicas:**
- Admin key debe agregarse manualmente al `config.php` del servidor (excluido de git)
- `admin.php` incluye top IPs (últimas 24h) del rate limiter

### v2.1 — 2026-05-18 (Claude Code)

**Funcionalidades nuevas:**
- Soporte YouTube Shorts (`/shorts/VIDEO_ID` en `extract_video_id()`)
- Rate limiting: máx 10 sorteos/hora por IP (tabla `sorteo_rate` en SQLite, IP hasheada SHA256)
- Página pública `stats.php`: total sorteos, canales usados con conteo y última fecha, últimos 20 sorteos
- Notificación Telegram al admin al crear cada sorteo (canal, video, ganadores)
- Enlace a `stats.php` en el footer (ES: "Estadísticas" / EN: "Stats")
- Columnas nuevas en `sorteos`: `channel_title`, `ip_hash`

**Notas técnicas:**
- `rate_limit_ok()` en `db.php`: ventana deslizante por hora (floor(time/3600)), limpieza de ventanas > 2h en `_cleanup_old()`
- Telegram: `notify_telegram()` con `file_get_contents` no-blocking

### v2.0 — 2026-05-18 (Claude Code)

**Funcionalidades nuevas:**
- Multi-video (hasta 5 URLs)
- Filtro por fecha, likes mínimos, exclusión de usuarios
- Ganadores suplentes
- Certificado imprimible con hash MD5 de verificación
- Página `verificar.php` con estados ok/tampered/not_found/invalid
- QR de verificación en el certificado (api.qrserver.com)
- Selector ES/EN + claro/oscuro (localStorage)
- Header rediseñado con ícono SVG YouTube
- Botón permanente de Cafecito en el footer
- Enlace a verificar.php desde el footer del sorteador

**Bugs corregidos:**
- `certificate.php`: `function cs() use ($cs)` inválido en PHP named fn → `$GLOBALS`
- `fetch.php`: `$total` global bloqueaba el 2do video al alcanzar el límite del 1er video → `$video_total` por video
- Dados en tema claro: overlay siempre oscuro → `rgba(248,250,252,.97)` en light mode
- YouTube comment deep link: se usaba el ID completo de respuesta en `lc=` → split por `.` para tomar solo el ID base

### v1.0 — 2026-05-17 (Claude Code)

- Versión inicial: formulario → SSE download → sorteo → ganadores
- SQLite + PDO, SSE streaming, confetti, dados, URL compartible

## Deploy rápido

```bash
lftp -u "carlos@mammoli.ar,PASS" ftp://mammoli.ar -e "
  set ssl:verify-certificate no; set ftp:passive-mode yes;
  put web/index.php       -o /sorteo/index.php;
  put web/api.php         -o /sorteo/api.php;
  put web/fetch.php       -o /sorteo/fetch.php;
  put web/db.php          -o /sorteo/db.php;
  put web/certificate.php -o /sorteo/certificate.php;
  put web/verificar.php   -o /sorteo/verificar.php;
  put web/stats.php       -o /sorteo/stats.php;
  put web/admin.php       -o /sorteo/admin.php;
  bye"
```

## Pendientes / Ideas

- [ ] Verificación histórica: si el sorteo expiró, mostrar hash solo si coincide con un registro archivado
- [ ] Caché de metadatos de video para no re-pedir a la API en re-sorteos
- [ ] Exportar lista de ganadores como CSV/TXT
- [ ] Modo oscuro en certificado (actualmente siempre claro para impresión)
