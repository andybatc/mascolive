"""Flujos de prueba MascoLive — screenshots por paso.

Correr: uv run python scripts/playwright/flujos_mascolive.py
Salida: screenshots/<flujo>/NN_desc.png
Credenciales demo: password = username (local).
"""
import os
from playwright.sync_api import sync_playwright

BASE = "http://mascolive.local:8080"
OUT = os.path.join(os.path.dirname(__file__), "..", "..", "screenshots")
CHROME = "/usr/bin/google-chrome-stable"


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False, executable_path=CHROME)
        ctx = browser.new_context(viewport={"width": 1440, "height": 900})
        pg = ctx.new_page()
        pg.set_default_timeout(20000)

        def shot(flow, step, desc):
            d = os.path.join(OUT, flow)
            os.makedirs(d, exist_ok=True)
            pg.screenshot(path=os.path.join(d, f"{step:02d}_{desc}.png"), full_page=True)

        def goto(url, step=None, desc=None):
            pg.goto(BASE + url, wait_until="networkidle")
            if step is not None:
                shot(F, step, desc)

        def login(user, password, flow="A"):
            pg.goto(BASE + "/user/login", wait_until="networkidle")
            pg.fill("#edit-name", user)
            pg.fill("#edit-pass", password)
            pg.click("#edit-submit")
            pg.wait_for_load_state("networkidle")

        def new_signed_ctx(user, password):
            # contexto limpio (anónimo real) + login sin logout previo
            nonlocal ctx, pg
            ctx.close()
            ctx = browser.new_context(viewport={"width": 1440, "height": 900})
            pg = ctx.new_page()
            pg.set_default_timeout(20000)
            login(user, password, "anon")

        def fill_by_label(labels, value):
            # rellena el input cuyo label contiene el texto dado
            pg.locator(f"label:has-text('{labels}')").first.click()
            pg.fill("#edit-title-0-value", value)

        results = []

        def run_flow(name, fn):
            try:
                fn()
                results.append(f"OK   {name}")
            except Exception as e:
                results.append(f"FAIL {name}: {e}")

        # ---- Flujo A: Admin — estructura del sitio ----
        def flow_a():
            global F
            F = "A_admin"
            login("admin", "admin")
            shot(F, 1, "dashboard")
            goto("/admin/structure/types", 2, "content_types")
            goto("/admin/structure/types/manage/clinic/fields", 3, "campos_clinic")
            goto("/admin/structure/taxonomy", 4, "taxonomias")
            goto("/admin/structure/taxonomy/manage/provinces/overview", 5, "provincias")
            goto("/admin/structure/paragraphs_type", 6, "paragraph_types")
            goto("/admin/people/roles", 7, "roles")

        # ---- Flujo B: Página con componentes (público) ----
        def flow_b():
            global F
            F = "B_paginas"
            goto("/acerca-de-mascolive", 1, "acerca_de")
            goto("/inicio", 2, "inicio")

        # ---- Flujo C: Clínica con mapa Leaflet ----
        def flow_c():
            global F
            F = "C_clinica_mapa"
            goto("/clinicas/vetcentro-habana", 1, "ficha_clinica")
            goto("/node/1/edit", 2, "editar_clinica_widget_mapa")

        # ---- Flujo D: Commerce — crear producto demo ----
        def flow_d():
            global F
            F = "D_commerce"
            goto("/admin/commerce/stores", 1, "tiendas")
            goto("/admin/commerce/product-types", 2, "tipos_producto")
            goto("/admin/commerce/products", 3, "lista_productos_vacia")
            pg.goto(BASE + "/product/add/pet_product", wait_until="networkidle")
            pg.fill("#edit-title-0-value", "Alimento Premium para Perro (demo)")
            shot(F, 4, "form_producto")
            pg.click("#edit-variations-actions-ief-add", force=True)
            pg.wait_for_timeout(1000)  # AJAX expande widget
            shot(F, 5, "variacion_expandida")
            # campos de variación por label (IDs inestables)
            pg.locator("input[id*='variations-form-0-sku']").fill("DEMO-SKU-001")
            pg.locator("input[id*='variations-form-0-price']").fill("1500")
            pg.locator("input[id*='variations-form-0-actions-ief-add-save']").scroll_into_view_if_needed()
            pg.locator("input[id*='variations-form-0-actions-ief-add-save']").click(force=True)
            pg.wait_for_timeout(1000)
            shot(F, 6, "variacion_guardada")
            pg.locator("#edit-actions-submit").scroll_into_view_if_needed()
            pg.locator("#edit-actions-submit").click(force=True)
            pg.wait_for_load_state("networkidle")
            shot(F, 7, "producto_creado")

        # ---- Flujo E: Multi-idioma — traducir página ----
        def flow_e():
            global F
            F = "E_idiomas"
            goto("/admin/config/regional/language", 1, "idiomas")
            goto("/admin/config/regional/content-language", 2, "traduccion_por_tipo")
            goto("/node/12/translations", 3, "traducciones_pagina")
            goto("/node/12/translations/add/en/es", 4, "form_traduccion_en")

        # ---- Flujo F: Usuario demo — tutor crea una mascota ----
        def flow_f():
            nonlocal ctx, pg
            F = "F_usuario_tutor"
            # Nuevo contexto anónimo (sin logout problemático)
            ctx.close()
            ctx = browser.new_context(viewport={"width": 1440, "height": 900})
            pg = ctx.new_page()
            pg.set_default_timeout(20000)
            shot(F, 1, "login_form")
            login("tutor", "tutor", "F")
            shot(F, 2, "perfil_tutor")
            pg.goto(BASE + "/node/add/pet", wait_until="networkidle")
            shot(F, 3, "form_mascota")
            pg.fill("#edit-title-0-value", "Rocky (demo tutor)")
            pg.select_option("#edit-field-pet-type", "Perro", index=1)
            pg.fill("#edit-field-clinic-0-target-id", "VetCentro Habana")
            pg.wait_for_timeout(500)  # autocomplete
            pg.click("#edit-submit", force=True)
            pg.wait_for_load_state("networkidle")
            shot(F, 4, "mascota_creada")

        run_flow("A admin estructura", flow_a)
        run_flow("B paginas publicas", flow_b)
        run_flow("C clinica mapa", flow_c)
        run_flow("D commerce producto", flow_d)
        run_flow("E idiomas", flow_e)
        run_flow("F tutor crea mascota", flow_f)

        browser.close()
        print("\n".join(results))
        print(f"Screenshots en: {os.path.abspath(OUT)}")


if __name__ == "__main__":
    main()