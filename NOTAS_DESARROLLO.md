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
| `web/config.php` | `YT_API_KEY` — excluido de git (`.gitignore`) |
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

El hash de verificación es:
```
strtoupper(substr(md5($id . implode(',', array_column($winners, 'comment_id'))), 0, 16))
```

- Se calcula en `certificate.php` y se muestra como texto + QR
- El QR apunta a `verificar.php?v=UUID&h=HASH&lang=…`
- `verificar.php` recalcula el hash desde la DB y compara
- Si el sorteo expiró (7 días), devuelve estado `not_found`
- Si el hash no coincide, devuelve estado `tampered`

## Historial de versiones

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
  bye"
```

## Pendientes / Ideas

- [ ] Verificación histórica: si el sorteo expiró, mostrar hash solo si coincide con un registro archivado
- [ ] Rate limiting básico en `api.php` (por IP, por día)
- [ ] Caché de metadatos de video para no re-pedir a la API en re-sorteos
- [ ] Exportar lista de ganadores como CSV/TXT
- [ ] Modo oscuro en certificado (actualmente siempre claro para impresión)
