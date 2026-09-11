"""E2E: comercio MascoLive.

Test anónimo: producto canónico /es/product/<nid> → add-to-cart sin 500.
Test admin: crear producto pet_product con variación (helpers gotcha 4).
Test vendedor: acceso a /es/admin/commerce/products.

Correr: uv run python scripts/playwright/e2e_comercio.py
"""
import subprocess
import sys
import os
from datetime import datetime

sys.path.insert(0, os.path.dirname(__file__))

from lib import (
    BASE, REPO, USERS, open_browser, new_ctx, login, submit_form,
    run_cases, screenshot, cleanup_nodes, cleanup_commerce_products,
)
from playwright.sync_api import sync_playwright


def _first_published_product_id():
    """Find first published product nid via drush. Returns int or None."""
    try:
        result = subprocess.run(
            ["ddev", "drush", "sql:query",
             "SELECT product_id FROM commerce_product_field_data WHERE status=1 ORDER BY product_id LIMIT 5"],
            cwd=REPO, capture_output=True, text=True, timeout=30,
        )
        ids = [int(tok) for tok in result.stdout.split()
               if tok.strip().isdigit()]
        return ids[0] if ids else None
    except Exception:
        return None


def main():
    total = 0
    fail = 0
    stamp = datetime.now().strftime("%Y%m%d%H%M%S")
    product_title = f"E2E Producto {stamp}"
    creation_state = {"ok": False, "detail": ""}

    with sync_playwright() as p:
        browser = open_browser(p)

        # ── Caso anónimo: add-to-cart sin 500 ────────────────────────────
        def test_anon_add_to_cart():
            pid = _first_published_product_id()
            if pid is None:
                print("    (SKIP: no hay productos publicados /es/product/<nid>)")
                return

            ctx, pg = new_ctx(browser)
            resp = pg.goto(BASE + f"/es/product/{pid}", wait_until="load")
            assert resp and resp.status == 200, f"product page status={resp.status if resp else '?'}"

            btn = pg.locator("#edit-submit")
            assert btn.count(), "add-to-cart button (#edit-submit) not present"

            ctx2_before = pg.url
            btn.first.click(force=True)
            pg.wait_for_timeout(2500)
            try:
                pg.wait_for_load_state("load", timeout=8000)
            except Exception:
                pass

            body = pg.locator("body").inner_text()
            assert "Internal Server Error" not in body, "500 Internal Server Error on add-to-cart"
            assert "500" not in body[:300], f"500 code detected. body[:300]={body[:300]!r}"

            print(f"    (producto /es/product/{pid} add-to-cart OK, url={pg.url[:80]})")
            screenshot(pg, "comercio", "anon_add_to_cart_ok")
            ctx.close()

        # ── Caso admin: crear producto pet_product ────────────────────────
        def test_admin_create_product():
            ctx, pg = new_ctx(browser)
            ok = login(pg, *USERS["admin"][:2])
            assert ok, "admin login failed"

            pg.goto(BASE + "/es/product/add/pet_product", wait_until="load")
            # Con variationTypes corregido el widget IEF abre variación 0 directo.

            # Fill TODOS los campos requeridos de la variación: title, sku y
            # AMBOS precios (commerce_price + price). Faltar uno → la validación
            # HTML5 aborta requestSubmit en silencio (descubierto en probes).
            pg.locator("#edit-variations-form-0-title-0-value").fill(f"E2E-VAR-{stamp}")
            pg.locator("#edit-variations-form-0-sku-0-value").fill(f"E2E-SKU-{stamp}")
            pg.locator("#edit-variations-form-0-commerce-price-0-number").fill("100")
            pg.locator("#edit-variations-form-0-price-0-number").fill("100")

            # Save variación inline (AJAX rebuild — puede limpiar campos padre)
            pg.locator("#edit-variations-form-0-actions-ief-add-save").scroll_into_view_if_needed()
            pg.locator("#edit-variations-form-0-actions-ief-add-save").click(force=True)
            pg.wait_for_timeout(4000)

            # Title AFTER variation save — el rebuild AJAX limpia campos padre.
            pg.locator("#edit-title-0-value").fill(product_title)
            pg.wait_for_timeout(300)

            # Submit con el botón "Save" EXACTO (hay 4 input[name=op]: Save y
            # "Save and add variations" en sticky bar + acciones; submit_form elige
            # el primero y puede ser el -continue → va a otra pantalla).
            submit_ok = pg.evaluate("""() => {
                const f = document.querySelector('form[data-drupal-selector]');
                if (!f) return false;
                const btn = f.querySelector('input[name=op][value="Save"], input[id="edit-actions-submit"]');
                if (!btn) return false;
                f.requestSubmit(btn);
                return true;
            }""")
            assert submit_ok, "no se encontró botón Save en el form de producto"
            pg.wait_for_timeout(6000)

            # Leer body con retry (la navegación puede interrumpir la lectura)
            body = ""
            for _ in range(3):
                try:
                    body = pg.locator("body").inner_text(timeout=3000)
                    break
                except Exception:
                    pg.wait_for_timeout(1000)
            # El flujo real: la variación se guarda inline (ief-add-save) y luego
            # se guarda el producto padre. Si falla, Drupal muestra el error aquí.
            ok_created = any(msg in body for msg in (
                "ha sido creado", "has been created",
                "ha sido guardado", "has been updated",
                # Commerce guarda con "has been successfully saved" (EN)
                "has been successfully saved", "se ha guardado correctamente",
            ))
            if ok_created:
                creation_state["ok"] = True
                screenshot(pg, "comercio", "admin_product_created")
            else:
                # Extraer el mensaje de error real de Drupal (sección "Error message")
                err_hint = ""
                if "cannot be referenced" in body:
                    err_hint = "This entity (commerce_product_variation:) cannot be referenced."
                else:
                    import re
                    m = re.search(r"Error message\s*\n(.+?)\n", body)
                    snippet = m.group(1).strip() if m else " ".join(body.split())[:160]
                    err_hint = snippet
                creation_state["detail"] = err_hint
                if not body:
                    err_hint = f"no se pudo leer body tras submit (race AJAX). {err_hint}"
                try:
                    screenshot(pg, "comercio", "admin_product_form_error")
                except Exception:
                    pass
                ctx.close()
                assert ok_created, (
                    f"creación de producto falló: {err_hint}. "
                )

            ctx.close()

        # ── Caso admin: producto en listing (depende del caso anterior) ──
        def test_admin_product_listed():
            if not creation_state["ok"]:
                print(f"    (SKIP: creación de producto falló — caso anterior; by design: {creation_state['detail'][:120]})")
                return

            ctx, pg = new_ctx(browser)
            ok = login(pg, *USERS["admin"][:2])
            assert ok, "admin login failed"

            pg.goto(BASE + "/es/admin/commerce/products", wait_until="load")
            body = pg.locator("body").inner_text()
            assert product_title in body, \
                f"product {product_title!r} not found in /es/admin/commerce/products"
            screenshot(pg, "comercio", "admin_product_listed")
            ctx.close()

        # ── Caso vendedor: acceso a commerce products ─────────────────────
        def test_vendedor_commerce_access():
            ctx, pg = new_ctx(browser)
            ok = login(pg, *USERS["vendedor"][:2])
            assert ok, "vendedor login failed"

            resp = pg.goto(BASE + "/es/admin/commerce/products", wait_until="load")
            assert resp and resp.status == 200, f"status={resp.status if resp else '?'}"
            body = pg.locator("body").inner_text()
            assert "Access denied" not in body, "vendedor got Access denied on commerce products"
            screenshot(pg, "comercio", "vendedor_commerce_access")
            ctx.close()

        # ── RUN ──────────────────────────────────────────────────────────
        cases = [
            ("Anónimo: add-to-cart en /es/product/<nid> (sin 500)", test_anon_add_to_cart),
            ("Admin: crear producto pet_product con variación", test_admin_create_product),
            ("Admin: producto en /es/admin/commerce/products", test_admin_product_listed),
            ("Vendedor: acceso /es/admin/commerce/products", test_vendedor_commerce_access),
        ]
        t, f = run_cases("comercio", cases)
        total += t; fail += f

        cleanup_commerce_products("E2E ")
        cleanup_nodes("E2E ")
        browser.close()

    print(f"\n=== RESULTADO: {total - fail}/{total} OK, {fail} FAIL ===")
    return 1 if fail else 0


if __name__ == "__main__":
    sys.exit(main())