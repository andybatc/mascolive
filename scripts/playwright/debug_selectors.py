"""Debug: DOM de /product/add/pet_product y login post-logout."""
from playwright.sync_api import sync_playwright

BASE = "http://mascolive.local:8080"
CHROME = "/usr/bin/google-chrome-stable"

with sync_playwright() as p:
    b = p.chromium.launch(headless=True, executable_path=CHROME)
    pg = b.new_page(viewport={"width": 1440, "height": 900})
    pg.set_default_timeout(15000)

    # login admin
    pg.goto(BASE + "/user/login", wait_until="networkidle")
    pg.fill("#edit-name", "admin")
    pg.fill("#edit-pass", "admin")
    pg.click("#edit-submit")
    pg.wait_for_load_state("networkidle")

    # producto
    pg.goto(BASE + "/product/add/pet_product", wait_until="networkidle")
    print("== PRODUCT ADD ==")
    print("inputs:", [i.get_attribute("id") for i in pg.locator("form input").all()])
    print("selects:", [s.get_attribute("id") for s in pg.locator("form select").all()])
    pg.screenshot(path="/tmp/opencode/prod_add.png", full_page=True)

    # logout
    pg.goto(BASE + "/user/logout", wait_until="networkidle")
    print("after logout url:", pg.url)
    pg.goto(BASE + "/user/login", wait_until="networkidle")
    print("== LOGIN POST-LOGOUT ==")
    print("url:", pg.url)
    print("inputs:", [i.get_attribute("id") for i in pg.locator("form input").all()])
    b.close()