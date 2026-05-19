# Sorteador de YouTube

Sorteá comentarios de videos de YouTube de forma transparente y verificable. Genera un certificado firmado con HMAC-SHA256 que cualquiera puede verificar en el servidor.

**[→ Usalo en mammoli.ar/sorteo](https://mammoli.ar/sorteo/)**

---

## Características

- **Multi-video** — hasta 5 videos combinados en un mismo sorteo
- **Filtros** — por palabra clave, fecha desde/hasta, mínimo de likes
- **Control de entradas** — máximo de participaciones por usuario (1, 2, 3, 5 o sin límite)
- **Ganadores suplentes** — hasta 20 suplentes ordenados
- **Exclusión de usuarios** — el dueño del canal se auto-detecta; podés agregar más
- **Certificado verificable** — hash HMAC-SHA256, QR apunta a la página de verificación pública
- **Sorteo oficial** — el organizador puede bloquear el conjunto de videos para evitar sorteos paralelos (1, 3, 7 o 30 días)
- **Historial transparente** — si se hacen múltiples sorteos del mismo conjunto de videos, todos aparecen listados en la verificación
- **Idioma** — interfaz en español e inglés (persiste en localStorage)
- **Tema** — claro u oscuro

## Stack

`PHP 8` · `SQLite` (PDO, WAL mode) · `YouTube Data API v3` · `Server-Sent Events` · sin frameworks ni dependencias externas

## Cómo funciona

1. Ingresás la URL de uno o más videos de YouTube
2. Los comentarios se descargan en tiempo real vía SSE (streaming)
3. Se aplican los filtros configurados
4. Se realiza el sorteo con `shuffle()` aleatorio (Mersenne Twister)
5. Se genera un certificado con hash HMAC-SHA256 y un QR de verificación

## Auto-hospedaje

### Requisitos

- PHP 8.0+
- Extensión PDO SQLite
- Extensión fileinfo
- Clave de YouTube Data API v3 ([console.cloud.google.com](https://console.cloud.google.com))

### Instalación

```bash
git clone https://github.com/camammoli/sorteo.git
cd sorteo/web
cp config.example.php config.php
```

Editá `config.php` con tus claves:

```php
define('YT_API_KEY',        'tu_clave_youtube');
define('SORTEO_ADMIN_KEY',  'clave_admin_generada');
define('SORTEO_HMAC_SECRET','clave_hmac_generada');
```

Generá las claves locales:

```bash
# HMAC secret (firma de certificados)
openssl rand -hex 32

# Admin key (panel /admin.php?key=...)
openssl rand -hex 16
```

El directorio `web/data/` necesita permisos de escritura para SQLite:

```bash
chmod 750 web/data/
```

### Panel de administración

Accesible en `/sorteo/admin.php?key=SORTEO_ADMIN_KEY` — tabla de todos los sorteos con canal, IP hasheada, opciones y estado.

## Verificación de certificados

La página `/sorteo/verificar.php` muestra los datos del servidor para comparación con el documento presentado. El hash cubre: UUID del sorteo, ID del video, fecha UTC, IDs y autores de los ganadores.

Los certificados emitidos antes de v2.3 usan hash MD5 (16 caracteres) y siguen siendo verificables.

## Licencia

MIT · [Carlos Ariel Mammoli](https://mammoli.ar)
