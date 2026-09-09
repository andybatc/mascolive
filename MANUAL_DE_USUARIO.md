# MascoLive — Manual de Usuario (demo de prueba)

Plataforma "Bienestar Animal". Este manual te guía por TODA la interfaz del demo
como si fueras el cliente final, con el resultado esperado en cada paso.

## 0. Antes de empezar

| Dato | Valor |
|---|---|
| Sitio | http://mascolive.local:8080/ |
| Admin | `admin` / `admin` |
| Usuarios de prueba | `demo`, `apiuser`, `tutor`, `veterinario`, `conductor`, `vendedor` (password = username) |
| Login rápido (sin contraseña) | `ddev drush uli` |

Si el sitio no responde: `ddev start` en `/home/andy/Proyectos/Mascolive`.

---

## Escenario A — Visitante anónimo (el cliente llega a la web)

### A.1. Directorio de mascotas
1. Abre **http://mascolive.local:8080/pets**
2. **Resultado esperado**: tabla con 6 mascotas (Rex, Luna, Milo, Kiara, Toby, PawPaw).
   Cada fila muestra el nombre (enlazado), su clínica (enlazada) y la especialidad de esa clínica.

### A.2. Filtrar por clínica
1. En `/pets`, usa el desplegable **"Clínica"** → selecciona **VetCentro Habana** → **Apply**.
   El desplegable solo lista clínicas (no mascotas), en orden: VetCentro Habana, Clínica Mascotas Vedado, VetMiramar.
2. **Resultado esperado**: solo aparecen Rex, Luna y PawPaw (los pacientes de VetCentro).
3. Alternativa por URL: `http://mascolive.local:8080/pets/9` → el listado se filtra solo (es el perfil de esa clínica). Prueba también `/pets/10` y `/pets/11`.

### A.3. Filtrar por tipo de mascota
1. Usa el desplegable **"Tipo de mascota"** → **Perro** → **Apply**.
2. **Resultado esperado**: Rex, Luna, Toby y PawPaw. Con **Gato**: Milo y Kiara.

### A.4. Ficha de una mascota
1. Clic en **Rex** (o abre http://mascolive.local:8080/node/12).
2. **Resultado esperado**: página de la mascota con el distintivo **"🐾 Bienestar Animal"**.
3. Abre una clínica (http://mascolive.local:8080/node/9) → **sin distintivo** (solo las mascotas lo llevan).

### A.5. Página institucional
1. Abre **http://mascolive.local:8080/node/17** ("Acerca de MascoLive").
2. **Resultado esperado**: página compuesta — bloque **"Misión"** ("Every pet deserves a healthy life...") y dos secciones de contenido: **"Bienestar Animal"** y **"Upcoming modules"**. La página se armó con bloques arrastrados (si entras como admin, `/node/17/layout` te deja editarlos).

### A.6. El sitio completo en Drupal
1. Abre **http://mascolive.local:8080/** — la portada "Inicio MascoLive" con tarjetas: Directorio de mascotas, Nuestras clínicas, Acerca de y Cómo probar cada rol.
2. El menú superior tiene: **Inicio**, **Directorio** (/pets), **Clínicas** (/clinicas) y **Acerca de**.
3. La tarjeta **Nuestras clínicas** ofrece **"Ver todas las clínicas →"** (lleva al listado) además de los accesos directos a cada clínica.
4. La tarjeta **Cómo probar cada rol** resume el flujo de esta guía:
   - **Tutor**: entra y crea su mascota (solo edita las suyas).
   - **Veterinario**: entra y crea una clínica o edita cualquier mascota.
   - **Conductor / Vendedor**: acceso de solo lectura (ver `/pets` y `/clinicas`, sin formularios).
   - **Admin**: entra y administra desde `/admin`.
5. Prueba también **/clinicas** — el listado de las 3 clínicas con dirección y especialidad.

---

## Escenario B — Tutor (cliente registrado)

### B.1. Entrar
1. **User log in** → `tutor` / `tutor`.
2. **Resultado esperado**: apareces logueado (bloque de "User account").

### B.2. Registrar una mascota
1. Ve a **Add content → Mascota** (o http://mascolive.local:8080/node/add/pet).
2. Rellena: Título (ej. "Firulais"), Clínica de referencia (elige una), Tipo de mascota (Perro) → **Save**.
3. **Resultado esperado**: el nodo se crea y aparece en el directorio `/pets` con el distintivo 🐾.
4. Prueba de límites: intenta editar una mascota de otro (http://mascolive.local:8080/node/12/edit) → **403 Access denied** (un tutor solo edita las suyas).

---

## Escenario C — Veterinario (personal de la clínica)

1. Login como `veterinario` / `veterinario`.
2. **Add content → Clínica**: crea "VetHabana Sur" con especialidad → Save.
3. **Resultado esperado**: la clínica aparece en `/admin/content` y puede editar **cualquier** mascota (http://mascolive.local:8080/node/12/edit → 200, formulario editable).
4. Límite: http://mascolive.local:8080/admin/config → **403** (no administra configuraciones).

---

## Escenario D — Editor y administrador

### D.1. Editor de layouts
1. Login como `demo` / `demo`.
2. Abre **http://mascolive.local:8080/node/17/layout**.
3. **Resultado esperado**: canvas de edición con la sección "Section 1" (layout one column) y dos bloques: **Misión** y el bloque de campos con los párrafos. Prueba **Add block** (agrega uno, luego **Save layout** y recarga `/node/17` para verlo).
4. **Discard changes** descarta sin guardar.

### D.2. Administración
1. Login como `admin` / `admin`.
2. **/admin/people** → los 6 usuarios de prueba con sus roles.
3. **/admin/structure** → content types (Mascota, Clínica, Página), taxonomía "Tipo de mascota".
4. **/admin/reports/status** → todo verde; verás la caché activa (Redis) y PHP con opcache.
5. **/admin/config/graphql/servers/manage/default/explorer** → probador visual de GraphQL (escribe `{ pets { title clinic { title } } }` y pulsa ▶; debería devolver las 5 mascotas con su clínica).

---

## Escenario E — APIs (consulta del cliente a la plataforma)

Desde cualquier terminal (sin login), el mismo contenido via API:

```bash
# GraphQL: mascotas con su clínica
curl -s http://mascolive.local:8080/graphql -H "Content-Type: application/json" \
  -d '{"query":"{ pets { title clinic { title } } }"}'

# JSON:API: solo perros
curl -s "http://mascolive.local:8080/jsonapi/node/pet?filter[field_pet_type.name]=Perro"
```

**Resultado esperado**: JSON con los mismos datos que ves en la UI. Con `apiuser` puedes hacer estas llamadas; no puedes crear contenido ni entrar a administración.

### E.1. JSON:API con escritura (CRUD)

El módulo core **JSON:API** expone mascotas y clínicas con todo el CRUD (GET, POST, PATCH, DELETE), reutilizando los permisos de Drupal por rol. Autenticación: HTTP Basic (usuario demo, password = username).

```bash
AUTH="Authorization: Basic $(echo -n 'tutor:tutor' | base64)"
JS="Accept: application/vnd.api+json"
CT="Content-Type: application/vnd.api+json"

# CREAR una mascota (tutor: permiso "create pet content")
curl -s -X POST http://mascolive.local:8080/jsonapi/node/pet -H "$AUTH" -H "$JS" -H "$CT" \
  -d '{"data":{"type":"node--pet","attributes":{"title":"Firulais"}}}'

# Leer la lista (anónimo)
curl -s http://mascolive.local:8080/jsonapi/node/pet -H "$JS"

# ACTUALIZAR: PATCH sobre el UUID que devuelve el POST anterior
curl -s -X PATCH http://mascolive.local:8080/jsonapi/node/pet/<UUID> -H "$AUTH" -H "$JS" -H "$CT" \
  -d '{"data":{"type":"node--pet","id":"<UUID>","attributes":{"title":"Firulais v2"}}}'

# BORRAR
curl -s -X DELETE http://mascolive.local:8080/jsonapi/node/pet/<UUID> -H "$AUTH" -H "$JS"
```

Chequeo de permisos (igual que en la UI):
- `tutor` crea/edita/borra **sus** mascotas; una mascota ajena → 403.
- `veterinario` crea/edita/borra clínicas y cualquier mascota.
- `apiuser`, `conductor`, `vendedor` y anónimo → solo lectura (GET).

---

## Resumen de lo que debe cumplir el demo

| Prueba | Resultado correcto |
|---|---|
| `/pets` | 6 mascotas en tabla con clínica + especialidad |
| Filtros clínica / tipo | Desplegables — Clínica lista solo clínicas; el listado se filtra correctamente |
| `/pets/9`, `/pets/10`, `/pets/11` | Filtrado contextual por clínica |
| `/node/12` | Mascota con distintivo 🐾 Bienestar Animal |
| `/node/9` | Clínica sin distintivo |
| `/node/17` | Página con Misión + 2 párrafos (Layout Builder) |
| `/node/17/layout` (editor/admin) | Canvas de edición funcional |
| `/graphql` y `/jsonapi` | JSON con los datos del demo (JSON:API con CRUD por rol) |
| Tutor | Crea mascotas propias; 403 en ajenas |
| Veterinario | Crea clínicas; edita cualquier mascota; 403 en admin |
| apiuser / conductor / vendedor / anónimo | Solo lectura de APIs/contenido público |
| `/admin/reports/status` | Todo verde, caché Redis activa |

## Soporte

- Olvidaste contraseñas: `ddev drush uli --name=<usuario>` te da un link de login directo.
- El sitio no carga: `ddev start` y recarga.
- Los datos se siembran con los scripts de `scripts/` (setup_content, setup_pages, setup_users) — reejecutar no duplica.
- Config y detalle técnico: ver `README.md` del proyecto.