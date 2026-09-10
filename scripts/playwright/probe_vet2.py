"""Replica EXACTA del login del test funcional + diagnóstico de links."""
import os
import re
from playwright.sync_api import sync_playwright

BASE = "http://mascolive.local:8080"
CHROME = "/usr/bin/google-chrome-stable"


def func_login(pg, user, pwd):
    pg.goto(BASE + "/es/user/login", wait_until="load")
    pg.fill("#edit-name", user)
    pg.fill("#edit-pass", pwd)
    pg.click("#edit-submit")
    try:
        pg.wait_for_load_state("networkidle", timeout=15000)
    except Exception:
        pg.wait_for_load_state("load", timeout=15000)
    body = pg.locator("body").inner_text()
    print("  [login] url:", pg.url)
    print("  [login] snippet:", re.sub(r"\s+", " ", body)[:150])
    return "Log out" in body or "Cerrar sesión" in body or "Mi cuenta" in body


with sync_playwright() as p:
    browser = p.chromium.launch(headless=False, executable_path=CHROME)
    ctx = browser.new_context(viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    pg.set_default_timeout(20000)

    ok = func_login(pg, "veterinario", "veterinario")
    print("  [login] check:", ok)

    # ¿hay elemento de logout?
    print("  [login] logout links:", pg.locator('a[href*="logout"], a[href*="user/logout"]').count())

    for url in ["/es/admin/content?type=appointment", "/es/node/add/wallet", "/es/portal-veterinario"]:
        resp = pg.goto(BASE + url, wait_until="load")
        body = pg.locator("body").inner_text()
        clean = re.sub(r"\s+", " ", body)
        denied = "Access denied" in clean or "Page not found" in clean
        print(f"\n--- {url} status={resp.status if resp else '?'} denied_in_body={denied} ---")
        print(clean[:220])

    browser.close()