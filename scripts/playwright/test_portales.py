"""Test portales MascoLive: cada portal con su usuario + permisos.

Correr: uv run python scripts/playwright/test_portales.py
Salida: screenshots/portales/NN_*.png + reporte PASS/FAIL en stdout.
Credenciales demo: password = username (local).
"""
import os
from playwright.sync_api import sync_playwright

BASE = "http://mascolive.local:8080"
OUT = os.path.join(os.path.dirname(__file__), "..", "..", "screenshots", "portales")
CHROME = "/usr/bin/google-chrome-stable"

USERS = {
    "veterinario": ("veterinario", "/portal-veterinario"),
    "vendedor": ("vendedor", "/portal-vendedor"),
    "admin_clinica": ("admin_clinica", "/portal-clinica"),
    "admin": ("admin", "/portal-admin"),
}

# user -> (allowed [200], denied [403])
CHECKS = {
    "veterinario": (
        ["/es/admin/content", "/es/node/add/clinical_history", "/es/node/add/wallet"],
        ["/es/node/add/clinic", "/es/admin/commerce/products"],
    ),
    "vendedor": (
        ["/es/admin/commerce/products", "/es/node/add/wallet"],
        ["/es/node/add/clinic", "/es/admin/commerce/config/stores", "/es/node/add/vendor"],
    ),
    "admin_clinica": (
        ["/es/node/add/service", "/es/admin/content"],
        ["/es/node/add/clinic", "/es/admin/commerce/products"],
    ),
    "admin": (
        ["/es/admin/content", "/es/admin/commerce/products", "/es/admin/commerce/config/stores", "/es/node/add/clinic"],
        [],
    ),
}


def main():
    total = fail = 0
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False, executable_path=CHROME)
        os.makedirs(OUT, exist_ok=True)

        for i, (user, (pwd, portal)) in enumerate(USERS.items(), 1):
            ctx = browser.new_context(viewport={"width": 1440, "height": 900})
            pg = ctx.new_page()
            pg.set_default_timeout(20000)

            print(f"\n=== {user} -> {portal} ===")

            # 1. Login: debe redirigir a la home pública (fix redirect)
            pg.goto(BASE + "/es/user/login", wait_until="networkidle")
            pg.fill("#edit-name", user)
            pg.fill("#edit-pass", pwd)
            pg.click("#edit-submit")
            pg.wait_for_load_state("networkidle")
            ok_redirect = "acerca-de-mascolive" in pg.url
            total += 1; fail += 0 if ok_redirect else 1
            print(f"  [{'OK' if ok_redirect else 'FAIL'}] login redirige a home pública ({pg.url})")

            # 2. Portal page renders (Drupal normaliza a la alias canónica, por eso
            #    verificamos el h1 y no el sufijo de URL)
            pg.goto(BASE + portal, wait_until="load")
            h1 = pg.locator("h1").first
            h1_txt = h1.inner_text() if h1.count() else "(sin h1)"
            ok_portal = (h1_txt != "Access denied" and "(sin h1)" not in h1_txt
                         and h1_txt not in ("Page not found", "Not Found"))
            total += 1; fail += 0 if ok_portal else 1
            print(f"  [{'OK' if ok_portal else 'FAIL'}] portal renderiza ({h1_txt})")
            pg.screenshot(path=os.path.join(OUT, f"{i:02d}_{user}_portal.png"), full_page=True)

            # 3. Checks de permiso (navegación directa)
            for url in CHECKS[user][0]:
                resp = pg.goto(BASE + url, wait_until="load")
                code = resp.status if resp else "?"
                ok = code == 200
                total += 1; fail += 0 if ok else 1
                print(f"  [{'OK' if ok else 'FAIL'}] ALLOWED {code} {url}")

            for url in CHECKS[user][1]:
                resp = pg.goto(BASE + url, wait_until="load")
                code = resp.status if resp else "?"
                ok = code in (403, 404)
                total += 1; fail += 0 if ok else 1
                print(f"  [{'OK' if ok else 'FAIL'}] DENIED  {code} {url} (esperado 403)")

            shot_allowed = os.path.join(OUT, f"{i:02d}_{user}_allowed.png")
            if CHECKS[user][0]:
                pg.screenshot(path=shot_allowed, full_page=False)

            ctx.close()

        browser.close()

    print(f"\n=== RESULTADO: {total - fail}/{total} OK, {fail} FAIL ===")


if __name__ == "__main__":
    main()