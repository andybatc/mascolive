#!/usr/bin/env bash
# MascoLive endpoint/node smoke test — anonymous + admin (v2, rutas reales)
BASE="http://mascolive.local:8080"
COOKIE="/tmp/opencode/mc_cookies.txt"
PASS=0; FAIL=0; FAILED=()

code() { curl -s -b "$COOKIE" -L -o /dev/null -w "%{http_code}" "$BASE$1"; }
code_anon() { curl -s -L -o /dev/null -w "%{http_code}" "$BASE$1"; }

check() { # check <label> <url>
  local anon admin
  anon=$(code_anon "$2"); admin=$(code "$2")
  if { [ "$anon" = "200" ] || [ "$anon" = "404" ] || [ "$anon" = "403" ]; } && { [ "$admin" = "200" ] || [ "$admin" = "404" ] || [ "$admin" = "403" ]; }; then
    PASS=$((PASS+1)); printf "%-46s anon:%s admin:%s OK\n" "$1" "$anon" "$admin"
  else
    FAIL=$((FAIL+1)); FAILED+=("$1 anon=$anon admin=$admin")
    printf "%-46s anon:%s admin:%s FAIL\n" "$1" "$anon" "$admin"
  fi
}

body_has() { # body_has <label> <url> <needle>
  local body; body=$(curl -s -b "$COOKIE" -L "$BASE$2")
  if echo "$body" | grep -qi "$3"; then PASS=$((PASS+1)); printf "%-46s OK\n" "$1"; else FAIL=$((FAIL+1)); FAILED+=("$1: missing '$3'"); printf "%-46s FAIL (missing '%s')\n" "$1" "$3"; fi
}

echo "=== NODOS (aliases es) ==="
check "clinic 1"          "/clinicas/vetcentro-habana"
check "clinic 2"          "/clinicas/clinica-mascotas-vedado"
check "clinic 3"          "/clinicas/vetmiramar"
check "pet Rex"           "/mascotas/rex"
check "pet Luna"          "/mascotas/luna"
check "pet Milo"          "/mascotas/milo"
check "pet Kiara"         "/mascotas/kiara"
check "pet Toby"          "/mascotas/toby"
check "page Acerca de"    "/acerca-de-mascolive"
check "contacto"          "/form/contact"

echo "=== NODOS canonical ==="
for n in 1 2 3 4 5 6 7 8 9 10 11 12; do check "node/$n" "/node/$n"; done

echo "=== FRONT ==="
check "frontpage /" "/"
check "es raiz" "/es"
check "en raiz" "/en"

echo "=== API ==="
check "jsonapi raiz" "/jsonapi"
check "jsonapi clinic" "/jsonapi/node/clinic"
check "jsonapi pet" "/jsonapi/node/pet"
check "jsonapi veterinario" "/jsonapi/node/veterinario"
check "jsonapi vendor" "/jsonapi/node/vendor"
check "jsonapi transfer" "/jsonapi/node/transfer"
check "jsonapi appointment" "/jsonapi/node/appointment"
check "jsonapi clinical_history" "/jsonapi/node/clinical_history"
check "jsonapi wallet" "/jsonapi/node/wallet"
check "jsonapi evaluation" "/jsonapi/node/evaluation"
check "jsonapi pet_type" "/jsonapi/taxonomy_term/pet_type"
check "jsonapi provinces" "/jsonapi/taxonomy_term/provinces"
body_has "jsonapi clinic contenido" "/jsonapi/node/clinic" "VetCentro"
body_has "jsonapi geolocation field" "/jsonapi/node/clinic?fields%5Bnode--clinic%5D=field_geolocation,title" "field_geolocation"

echo "=== GraphQL ==="
GQL=$(curl -s -X POST "$BASE/graphql" -H "Content-Type: application/json" -d '{"query":"{ __schema { queryType { name } } }"}')
if echo "$GQL" | grep -qi "error"; then FAIL=$((FAIL+1)); FAILED+=("graphql schema error"); echo "graphql: FAIL"; else PASS=$((PASS+1)); echo "graphql: OK"; fi

echo "=== ROUTER NodeHive ==="
body_has "router translate clinic" "/router/translate-path?path=/clinicas/vetcentro-habana" "vetcentro"

echo "=== COMERCIO ==="
check "admin stores"      "/admin/commerce/config/stores"
check "admin store-types" "/admin/commerce/config/store-types"
check "admin products"    "/admin/commerce/products"
check "admin product-types" "/admin/commerce/config/product-types"
check "admin orders"      "/admin/commerce/orders"
check "product 1 page"    "/product/1"
check "cart"              "/cart"
body_has "product title"  "/product/1" "Comida Premium"
body_has "cart block"     "/product/1" "cart"

echo "=== ADMIN core ==="
check "admin content"     "/admin/content"
check "admin types"       "/admin/structure/types"
check "admin taxonomy"    "/admin/structure/taxonomy"
check "admin people"      "/admin/people"
check "admin language"    "/admin/config/regional/language"

echo "=== NODEHIVE CE ==="
check "area add"          "/area/add"
check "admin areas"       "/admin/content/area"

echo ""
echo "PASS=$PASS FAIL=$FAIL"
if [ ${#FAILED[@]} -gt 0 ]; then printf '%s\n' "--- FAILURES ---" "${FAILED[@]}"; fi