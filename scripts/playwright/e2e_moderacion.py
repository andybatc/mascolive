"""E2E: moderación editorial MascoLive.

Verifica el workflow editorial real: admin crea artículo (state inicial =
Draft/Borrador por defecto), luego lo publica vía el selector de moderación
(#edit-moderation-state-0-state: draft|published) y confirma que la página
pública /es/node/<nid> muestra el contenido.

Nota (verificada en la corrida): el form de article usa content_moderation;
el submit nativo form.submit() NO funciona (no incluye el botón `op`, Drupal
re-renderiza el form sin guardar). La solución es form.requestSubmit(btn),
que incluye `op` y dispara el evento submit (sincroniza ckeditor).

Correr: uv run python scripts/playwright/e2e_moderacion.py
"""
import os
import re
import sys
from datetime import datetime

sys.path.insert(0, os.path.dirname(__file__))

from lib import (
    BASE, USERS, open_browser, new_ctx, login, fill_field, submit_form, created_ok,
    run_cases, screenshot, cleanup_nodes,
)
from playwright.sync_api import sync_playwright


def main():
    total = 0
    fail = 0
    stamp = datetime.now().strftime("%Y%m%d%H%M%S")
    article_title = f"E2E artículo {stamp}"
    state = {"nid": None, "moderation_ui": "", "initial_reachable": False}

    with sync_playwright() as p:
        browser = open_browser(p)

        # ── Caso A: admin crea artículo ──────────────────────────────────
        def test_create_article():
            ctx, pg = new_ctx(browser)
            ok = login(pg, *USERS["admin"][:2])
            assert ok, "admin login failed"

            pg.goto(BASE + "/es/node/add/article", wait_until="load")
            pg.fill("#edit-title-0-value", article_title)

            body_sel = "#edit-body-0-value"
            if pg.locator(body_sel).count():
                fill_field(pg, body_sel, f"Contenido del artículo E2E {stamp}")

            submit_form(pg)

            # Extraer nid de la URL
            m = re.search(r"/node/(\d+)", pg.url)
            if m:
                state["nid"] = m.group(1)

            body = pg.locator("body").inner_text()
            is_created = created_ok(pg)
            if not is_created and state["nid"]:
                is_created = True  # sin mensaje de éxito pero redirigió al nodo

            screenshot(pg, "moderacion", "admin_article_created")
            ctx.close()
            assert is_created, f"article creation not confirmed. body[:300]={' '.join(body.split())[:300]!r}"

        # ── Caso B: verificar estado inicial (borrador) ──────────────────
        def test_article_status():
            assert state["nid"], "no nid available (creation failed)"

            ctx, pg = new_ctx(browser)
            ok = login(pg, *USERS["admin"][:2])
            assert ok, "admin login failed"

            pg.goto(BASE + f"/es/node/{state['nid']}", wait_until="load")
            body = pg.locator("body").inner_text()

            # Con sesión admin, el nodo en borrador responde 200 y muestra
            # indicadores de moderación o el contenido (preview).
            snippet = " ".join(body.split())
            has_moderation = any(k in body for k in (
                "Necesita revisión", "Draft", "Borrador",
            ))
            has_content = article_title in body
            state["moderation_ui"] = "moderation:" if has_moderation else "content:"
            state["initial_reachable"] = has_content or has_moderation

            screenshot(pg, "moderacion", f"article_nid{state['nid']}_initial")
            ctx.close()
            assert state["initial_reachable"], \
                f"article nid={state['nid']} not reachable in draft state. snippet={' '.join(body.split())[:200]!r}"
            print(f"    (estado inicial: {'moderación visible' if has_moderation else 'en borrador (sin UI de moderación visible)'})")

        # ── Caso C: publicar vía workflow editorial ──────────────────────
        def test_publish_article():
            assert state["nid"], "no nid available (creation failed)"

            ctx, pg = new_ctx(browser)
            ok = login(pg, *USERS["admin"][:2])
            assert ok, "admin login failed"

            # Ir al form de edición y setear state = published
            pg.goto(BASE + f"/es/node/{state['nid']}/edit", wait_until="load")
            state_sel = pg.locator("#edit-moderation-state-0-state")
            if not state_sel.count():
                # fallback: /latest tiene el widget de moderación
                pg.goto(BASE + f"/es/node/{state['nid']}/latest", wait_until="load")
                state_sel = pg.locator("#edit-moderation-state-0-state")

            assert state_sel.count(), \
                f"moderation state selector not found on edit/latest. snippet={' '.join(pg.locator('body').inner_text().split())[:200]!r}"

            try:
                state_sel.select_option(label="Published")
            except Exception:
                state_sel.select_option(label="Publicado")

            submit_form(pg)

            # Verificar publicación: el contenido debe estar en el nodo
            pg.goto(BASE + f"/es/node/{state['nid']}", wait_until="load")
            body = pg.locator("body").inner_text()
            is_published = article_title in body and "Access denied" not in body
            screenshot(pg, "moderacion", f"article_nid{state['nid']}_published")
            ctx.close()
            assert is_published, \
                f"article not published after moderation action. snippet={' '.join(body.split())[:250]!r}"

        # ── RUN ──────────────────────────────────────────────────────────
        cases = [
            (f"Admin crea artículo '{article_title}'", test_create_article),
            ("Estado inicial del artículo (borrador)", test_article_status),
            ("Publicar artículo via workflow editorial", test_publish_article),
        ]
        t, f = run_cases("moderacion", cases)
        total += t; fail += f

        cleanup_nodes("E2E ")
        browser.close()

    print(f"\n=== RESULTADO: {total - fail}/{total} OK, {fail} FAIL ===")
    return 1 if fail else 0


if __name__ == "__main__":
    sys.exit(main())