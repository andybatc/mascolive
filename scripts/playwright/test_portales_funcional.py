"""Test funcional portales MascoLive: desde cada portal, cumplir cada función.

Por portal (rol):
  1. Login como el usuario del rol (password = username)
  2. Abrir el portal y verificar que renderiza
  3. Click en CADA link del portal -> 200 OK, sin "Access denied"
  4. Crear UNA entidad real desde el portal ("Crear nuevo") -> mensaje de éxito

Correr: uv run python scripts/playwright/test_portales_funcional.py
Salida: screenshots/portales_funcional/ + reporte PASS/FAIL.
"""
import os
import re
from datetime import datetime
from playwright.sync_api import sync_playwright

BASE = "http://mascolive.local:8080"
OUT = os.path.join(os.path.dirname(__file__), "..", "..", "screenshots", "portales_funcional")
CHROME = "/usr/bin/google-chrome-stable"

USERS = {
    "veterinario": ("veterinario", "/es/portal-veterinario"),
    "vendedor": ("vendedor", "/es/portal-vendedor"),
    "admin_clinica": ("admin_clinica", "/es/portal-clinica"),
    "admin": ("admin", "/es/portal-admin"),
}

# Campos requeridos extra por tipo al crear (fuera de title)
REQUIRED_BY_TYPE = {
    "service": {
        "#edit-field-description-0-value": "Servicio de prueba funcional",
        "#edit-field-duration-0-value": "30 min",
        "#edit-field-price-0-value": "500",
    },
}

# Campos ckeditor5: el textarea está oculto y su valor se pierde en el submit
# (ckeditor escribe desde el editor visible). Hay que teclear en .ck-editor__editable.
CKEDITOR_FIELDS = {"#edit-field-description-0-value"}

LINK_RE = re.compile(r"^/es/(admin/|node/add/|product/)")


def login(pg, user, pwd):
    """Login con retry — Drupal a veces rechaza el 1er POST (race/flood)."""
    for attempt in range(3):
        pg.goto(BASE + "/es/user/login", wait_until="load")
        pg.fill("#edit-name", user)
        pg.fill("#edit-pass", pwd)
        pg.click("#edit-submit")
        try:
            pg.wait_for_load_state("networkidle", timeout=10000)
        except Exception:
            pass
        pg.wait_for_timeout(800)
        body = pg.locator("body").inner_text()
        if "Unrecognized username or password" not in body and "user/login" not in pg.url:
            return True
        print(f"    (intento {attempt + 1} falló, reintentando)")
        pg.wait_for_timeout(1200)
    return False


def portal_links(pg, portal_url):
    """Links del portal (UI elements) que llevan a funciones del rol."""
    pg.goto(BASE + portal_url, wait_until="load")
    hrefs = set()
    for a in pg.locator("main a[href], .region-content a[href]").all():
        href = a.get_attribute("href") or ""
        if LINK_RE.match(href):
            hrefs.add(href)
    # fallback: buscar en todo el documento si el portal no usa main/.region-content
    if not hrefs:
        for a in pg.locator("a[href]").all():
            href = a.get_attribute("href") or ""
            if LINK_RE.match(href):
                hrefs.add(href)
    return sorted(hrefs)


def main():
    total = fail = 0
    results = []

    def report(ok, label, extra=""):
        nonlocal total, fail
        total += 1
        if not ok:
            fail += 1
        results.append((ok, label, extra))
        print(f"  [{'OK' if ok else 'FAIL'}] {label} {extra}")

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False, executable_path=CHROME)
        os.makedirs(OUT, exist_ok=True)

        for i, (user, (pwd, portal_url)) in enumerate(USERS.items(), 1):
            ctx = browser.new_context(viewport={"width": 1440, "height": 900})
            pg = ctx.new_page()
            pg.set_default_timeout(20000)

            print(f"\n=== {user} -> {portal_url} ===")

            # 1. Login
            try:
                ok_login = login(pg, user, pwd)
                report(ok_login, "login", f"({user})")
            except Exception as e:
                report(False, "login", f"({user}) {e}")
                ctx.close()
                continue

            # 2. Portal renderiza
            pg.goto(BASE + portal_url, wait_until="load")
            body = pg.locator("body").inner_text()
            h1 = pg.locator("h1").first
            h1_txt = h1.inner_text() if h1.count() else "(sin h1)"
            ok_portal = "Access denied" not in body and "Page not found" not in body
            report(ok_portal, "portal renderiza", f"h1={h1_txt!r}")
            pg.screenshot(path=os.path.join(OUT, f"{i:02d}_{user}_portal.png"), full_page=True)

            # 3. Cada link del portal -> 200 OK
            links = portal_links(pg, portal_url)
            if not links:
                report(False, "links encontrados", "(0 links)")
            for href in links:
                resp = pg.goto(BASE + href, wait_until="load")
                body = pg.locator("body").inner_text()
                code = resp.status if resp else "?"
                ok = code == 200 and "Access denied" not in body and "Page not found" not in body
                report(ok, f"link {href}", f"-> {code}")
                pg.go_back(wait_until="load")

            # 4. Crear una entidad real desde el portal
            create_links = [l for l in links if "/node/add/" in l]
            if create_links:
                href = create_links[0]
                mtype = href.split("/node/add/")[-1]
                stamp = datetime.now().strftime("%H%M%S")
                title = f"Prueba {user} {stamp}"
                try:
                    resp = pg.goto(BASE + href, wait_until="load")
                    code = resp.status if resp else "?"
                    if code != 200:
                        report(False, f"crear {mtype}", f"-> {code} (form no accesible)")
                    else:
                        pg.fill("#edit-title-0-value", title)
                        for sel, val in REQUIRED_BY_TYPE.get(mtype, {}).items():
                            if not pg.locator(sel).count():
                                continue
                            if sel in CKEDITOR_FIELDS:
                                editor = pg.locator(".ck-editor__editable").first
                                if editor.count():
                                    editor.click()
                                    pg.keyboard.type(val, delay=10)
                                    continue
                            try:
                                pg.fill(sel, val)
                            except Exception:
                                # textarea oculto -> setear vía JS
                                pg.locator(sel).evaluate(
                                    "(el, v) => { el.value = v; el.dispatchEvent(new Event('input', {bubbles:true})); }",
                                    val,
                                )
                        # Submit nativo del form (Gin overlay intercepta pg.click y navega a /es/node/add)
                        form = pg.locator("form[data-drupal-selector]").first
                        if form.count():
                            form.evaluate("f => f.submit()")
                        else:
                            pg.click("#edit-submit", force=True)
                        pg.wait_for_timeout(2500)
                        pg.wait_for_load_state("load")
                        body = pg.locator("body").inner_text()
                        ok = ("ha sido creado" in body or "has been created" in body
                              or "ha sido guardado" in body or "has been updated" in body)
                        report(ok, f"crear {mtype}", f"title={title!r}")
                        pg.screenshot(path=os.path.join(OUT, f"{i:02d}_{user}_create_{mtype}.png"), full_page=False)
                except Exception as e:
                    report(False, f"crear {mtype}", f"ERROR {e}")
            else:
                report(False, "link crear", "(ningún /node/add/ en el portal)")

            ctx.close()

        browser.close()

    print(f"\n=== RESULTADO: {total - fail}/{total} OK, {fail} FAIL ===")
    for ok, label, extra in results:
        if not ok:
            print(f"  FAIL -> {label} {extra}")


if __name__ == "__main__":
    main()