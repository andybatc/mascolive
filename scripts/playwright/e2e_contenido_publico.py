"""E2E: contenido público MascoLive.

Verifica páginas públicas sin autenticación.

Correr: uv run python scripts/playwright/e2e_contenido_publico.py
"""
import os
import sys
sys.path.insert(0, os.path.dirname(__file__))

from lib import BASE, open_browser, new_ctx, run_cases, screenshot, screenshot_full
from playwright.sync_api import sync_playwright


def main():
    total = 0
    fail = 0

    with sync_playwright() as p:
        browser = open_browser(p)

        # ── /es/acerca-de-mascolive ──────────────────────────────────────
        def test_acerca():
            ctx, pg = new_ctx(browser)
            resp = pg.goto(BASE + "/es/acerca-de-mascolive", wait_until="load")
            assert resp and resp.status == 200, f"status={resp.status if resp else '?'}"
            h1 = pg.locator("h1").first
            assert h1.count(), "no h1 found"
            txt = h1.inner_text()
            assert txt, f"h1 empty"
            screenshot(pg, "contenido_publico", "acerca_de_mascolive")
            ctx.close()

        # ── /es (front) ─────────────────────────────────────────────────
        def test_front():
            # ponytail: /es/inicio no existe (404, sin alias ni nodo); la front real es
            # /es (system.site page.front = /acerca-de-mascolive). Se testea la raíz.
            ctx, pg = new_ctx(browser)
            resp = pg.goto(BASE + "/es", wait_until="load")
            assert resp and resp.status == 200, f"status={resp.status if resp else '?'}"
            h1 = pg.locator("h1").first
            assert h1.count(), "no h1 found"
            assert h1.inner_text(), "h1 empty"
            screenshot(pg, "contenido_publico", "front_es")
            ctx.close()

        # ── /es/clinicas/vetcentro-habana ────────────────────────────────
        def test_clinica():
            ctx, pg = new_ctx(browser)
            resp = pg.goto(BASE + "/es/clinicas/vetcentro-habana", wait_until="load")
            assert resp and resp.status == 200, f"status={resp.status if resp else '?'}"
            body = pg.locator("body").inner_text()
            has_leaflet = pg.locator(".leaflet").count() > 0 or pg.locator("iframe").count() > 0
            title_present = "VetCentro" in body or "vetcentro" in body.lower()
            assert title_present or has_leaflet, f"no clinic content found: body[:200]={body[:200]!r}"
            screenshot(pg, "contenido_publico", "vetcentro_habana")
            ctx.close()

        # ── /es/user/login (form) ───────────────────────────────────────
        def test_login_form():
            ctx, pg = new_ctx(browser)
            resp = pg.goto(BASE + "/es/user/login", wait_until="load")
            assert resp and resp.status == 200, f"status={resp.status if resp else '?'}"
            assert pg.locator("#edit-name").count(), "no #edit-name"
            assert pg.locator("#edit-pass").count(), "no #edit-pass"
            ctx.close()

        # ── /es/user/register (selector de rol) ─────────────────────────
        def test_register_form():
            ctx, pg = new_ctx(browser)
            resp = pg.goto(BASE + "/es/user/register", wait_until="load")
            assert resp and resp.status == 200, f"status={resp.status if resp else '?'}"
            assert pg.locator("#edit-mascolive-role").count(), "no role selector #edit-mascolive-role"
            ctx.close()

        # ── RUN ──────────────────────────────────────────────────────────
        cases = [
            ("Página /es/acerca-de-mascolive (200 + h1)", test_acerca),
            ("Página /es (front, 200 + h1)", test_front),
            ("Ficha clínica /es/clinicas/vetcentro-habana (200 + contenido)", test_clinica),
            ("Login form /es/user/login (200 + campos)", test_login_form),
            ("Register form /es/user/register (200 + role selector)", test_register_form),
        ]
        t, f = run_cases("contenido_publico", cases)
        total += t; fail += f

        browser.close()

    print(f"\n=== RESULTADO: {total - fail}/{total} OK, {fail} FAIL ===")
    return 1 if fail else 0


if __name__ == "__main__":
    sys.exit(main())
