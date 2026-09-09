# MascoLive — Demo Sprint 0 (estudio Drupal)

Plataforma "Bienestar Animal": ecosistema veterinario cubano (turnos, historia clínica,
traslados, tienda; pagos CUP/Transfermóvil/Enzona). Stack objetivo: **Frontend Astro +
Backend Drupal** (cerebro/API). Este demo cubre el plan de estudio Drupal del Sprint 0.

## Entorno local (DDEV)

| Dato | Valor |
|---|---|
| URL | http://mascolive.local:8080/ |
| Admin | `admin` / `admin` |
| Login rápido | `ddev drush uli` |
| Config | `/home/andy/Proyectos/Mascolive` (docroot `web/`) |

Levantar: `ddev start`. Comandos útiles: `ddev drush cr` (cache), `ddev drush cex/cim`
(config → `config/sync/` en la raíz del proyecto, según `settings.php`).

## Qué hay (Definition of Done del Sprint 0)

1. **Content types interconectados** — `clinic` y `pet`; `pet.field_clinic`
   (entity reference → clínica). Creados con nodos demo via `scripts/setup_content.php`.
2. **Vista compleja** — `pet_directory` (/pets): tabla de mascotas con filtros
   **expuestos** (clínica + tipo de mascota) y **relación** a la entidad clínica
   (muestra su especialidad). Además, **filtro contextual** por clínica: `/pets/NID`
   (ej. `/pets/9` → solo mascotas de VetCentro). Config en `scripts/views/`;
   importable con `drush cim --partial`.
3. **Sub-theme** — `web/themes/custom/mascolive` (base Olivero), librería global
   `mascolive/global` → `css/mascolive.css` (identidad verde). Activo por defecto.
4. **Módulo custom** — `web/modules/custom/mascolive_utils`: `hook_node_view` agrega badge
   "🐾 Bienestar Animal" solo en nodos publicados de mascota.
5. **Taxonomía** — vocabulario `pet_type` ("Tipo de mascota", términos Perro/Gato),
   campo `pet.field_pet_type` en las mascotas demo; consumida por el filtro expuesto
   de la vista.
6. **Paragraphs (contrib)** — content type `page` con campo ilimitado `field_components`
   (entity_reference_revisions → `text_block`). Página demo "Acerca de MascoLive"
   (/node/17) con dos párrafos. Setup en `scripts/setup_pages.php`.
7. **Bloques / Layout Builder** — Layout Builder habilitado en `page` (allow custom).
   El nodo 17 lleva **override** con dos bloques: bloque custom "Misión"
   (block_content) y field block de `field_components` (renderiza los párrafos).
   Canvas editable: `/node/17/layout`.
8. **JSON:API (core)** — habilitado; lectura anónima de contenido. Endpoints:
   - `/jsonapi/node/pet` — todas las mascotas.
   - `/jsonapi/node/pet?filter[field_clinic.drupal_internal__nid]=9` — por clínica.
   - `/jsonapi/node/pet?filter[field_pet_type.name]=Perro` — por tipo de mascota.
9. **Usuarios** — `admin`/`admin` (administrador) y `demo`/`demo` (rol
   `content_editor`: crear/editar/borrar page, pet y clinic + editar layouts
   "configure any layout").
10. **GraphQL (contrib)** — `drupal/graphql` v5 + server `default` en
    `/graphql` (POST; permiso de ejecución otorgado a anónimos/autenticados).
    Schema composable + extensión custom `mascolive_graphql`
    (`web/modules/custom/`): queries `pets`, `clinics`, `pet(id)`, `clinic(id)`,
    con resolución de la referencia `pet.clinic`. Ejemplo:
    `{"query":"{ pets { title clinic { title } } }"}`. Explorer de admin:
    `/admin/config/graphql/servers/manage/default/explorer`.
11. **Redis (performance)** — add-on `ddev-redis` (servicio `redis` en el
    contenedor) + módulo contrib `redis`; caché por defecto en Redis
    (backend `cache.backend.redis`, cliente Predis; wiring automático en
    `settings.ddev.redis.php` generado por el add-on). Verificar con
    `redis-cli DBSIZE` (los cache bins viven ahí).
12. **Formularios (contrib)** — `drupal/token` y `drupal/webform` instalados
    (base para el flujo de turnos).
13. **Frontend Astro** — `frontend/` (Astro, SSG): `npm run build` arma una
    página **por rol** (`/publico`, `/tutor`, `/veterinario`, `/admin`)
    consultando `/graphql` en build-time (`pets`→clínica); `npm run dev` para
    desarrollo, `npm run preview` para servir la build. La web de Drupal es la
    que valida los permisos reales; Astro simula el punto de entrada de cada
    actor y enlaza al flujo correspondiente. Build resiliente: si el backend
    cae, compila igual con mensaje de error.
14. **Usuarios por nivel de acceso** — creados con `scripts/setup_users.php`
    (password = username, mail `user@mascolive.local`): `apiuser` (rol
    `api_consumer`: solo ejecuta GraphQL), `tutor` (crea/edita/borra mascotas
    propias), `veterinario` (crea/edita mascotas y clínicas, edita cualquiera),
    `conductor` y `vendedor` (lectura, sin permisos extra). Matriz resumida:

    | Usuario | Crear pet | Editar pet ajeno | Crear clínica | /admin/config | GraphQL/JSON:API |
    |---|---|---|---|---|---|
    | admin | ✅ | ✅ | ✅ | ✅ | ✅ |
    | demo (content_editor) | ✅ | ✅ | ✅ | ✅ | ✅ |
    | veterinario | ✅ | ✅ | ✅ | ❌ | ✅ |
    | tutor | ✅ | ❌ (solo propias) | ❌ | ❌ | ✅ |
    | apiuser | ❌ | ❌ | ❌ | ❌ | ✅ |
    | conductor / vendedor / anónimo | ❌ | ❌ | ❌ | ❌ | ✅ (lectura) |

## Verificación manual (sesión demo)

- **Anónimo**: `/pets` (filtros "Clínica" y "Tipo de mascota"), `/pets/9`
  (contextual: solo VetCentro), /node/12 (badge 🐾), /node/17 (página con layout),
  `/jsonapi/node/pet?filter[field_pet_type.name]=Perro` (JSON).
- **Editor** (`demo`/`demo`): `/node/add/pet` crea mascota; `/node/17/layout` edita
  el layout de la página.
- **Por rol** (todos password = username): `tutor` crea sus mascotas en
  `/node/add/pet` pero no edita las ajenas (403 en `/node/12/edit`); `veterinario`
  además crea clínicas y edita cualquier mascota; `apiuser`, `conductor`,
  `vendedor` solo leen APIs (GraphQL/JSON:API) y reciben 403 en los formularios.
- **Admin**: `/admin/structure` (content types, taxonomía), `/admin/structure/types/manage/page/display/default/layout` (defaults del layout), `/admin/config` etc.

## Concurrencia (medido en local)

- Caché anónima: `page_cache` + `dynamic_page_cache` activos; opcache On;
  caché por defecto en **Redis** (backend `cache.backend.redis`).
- Smoke test: 40 GET paralelos anónimos a `/pets` → **40×200 en ~190 ms wall**
  (antes de Redis/tuning: ~380 ms); 40 GET a `/` → 40×200 en ~440 ms.
- La vista `pet_directory` usa caché por tags (default de Views, coherente con
  Redis); si el listado crece, considerar time-based caching (1 h) en el YAML.

## Pendiente / recomendado (próximos Sprints)

- Webform aún sin contenido de turnos real (instalado; diseñar el formulario
  en el sprint de turnos).
- Nota: los field storage de campos multi-valor deben crearse con `cardinality: -1`
  (el default es 1; `field_components` se corrigió en `setup_pages.php`).

## Gotchas (debug de este Sprint)

- **Tempstore de Layout Builder**: si el canvas de un override 500ea después de
  cambiar el override por API/script con el usuario logueado, el motivo suele ser una
  entrada **stale en `key_value_expire`**
  (`collection='tempstore.shared.layout_builder.section_storage.overrides'`,
  `name='node.17.default.en'`). `drush cr` NO la limpia: borrarla con
  `DELETE FROM key_value_expire WHERE ...` y recargar.
- **Field blocks en overrides**: dentro del canvas de un override los field blocks
  necesitan `context_mapping: {entity: layout_builder.entity}` (el storage de
  overrides renombra el contexto `entity`). Los bloques normales (block_content) no.