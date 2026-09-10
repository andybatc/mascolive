"""Probe: login admin en mascolive + captura de pantalla."""
from playwright.sync_api import sync_playwright

with sync_playwright() as p:
    b = p.chromium.launch(headless=True, executable_path="/usr/bin/google-chrome-stable")
    pg = b.new_page(viewport={"width": 1440, "height": 900})
    pg.goto("http://mascolive.local:8080/user/login", timeout=60000)
    pg.wait_for_load_state("networkidle")
    print("title:", pg.title())
    print("inputs:", [i.get_attribute("id") for i in pg.locator("input").all()])
    print("buttons:", [i.get_attribute("id") for i in pg.locator("button").all()])
    pg.screenshot(path="/tmp/opencode/login.png")
    b.close()