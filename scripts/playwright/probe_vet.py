"""Probe: qué ve veterinario en admin/content y node/add/appointment."""
import os
import re
from playwright.sync_api import sync_playwright

BASE = "http://mascolive.local:8080"
CHROME = "/usr/bin/google-chrome-stable"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=False, executable_path=CHROME)
    ctx = browser.new_context(viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    pg.set_default_timeout(15000)

    pg.goto(BASE + "/es/user/login", wait_until="load")
    pg.fill("#edit-name", "veterinario")
    pg.fill("#edit-pass", "veterinario")
    pg.click("#edit-submit")
    pg.wait_for_load_state("networkidle")

    print("after login url:", pg.url)
    body = pg.locator("body").inner_text()
    print("after login body snippet:", re.sub(r"\s+", " ", body)[:200])

    for url in ["/es/admin/content?type=appointment", "/es/node/add/appointment"]:
        resp = pg.goto(BASE + url, wait_until="load")
        body = pg.locator("body").inner_text()
        clean = re.sub(r"\s+", " ", body)
        print(f"\n--- {url} status={resp.status if resp else '?'} ---")
        print(clean[:400])
        # inputs visibles
        inputs = pg.locator("input, select, textarea").all()
        names = [el.get_attribute("id") or el.get_attribute("name") for el in inputs[:25]]
        print("inputs:", [n for n in names if n])

    browser.close()