"""E2E: portales MascoLive.

Test funcional por portal: login, renderiza, links OK, crear entidad.

Correr: uv run python scripts/playwright/e2e_portales.py
"""
import os
import sys
from datetime import datetime

sys.path.insert(0, os.path.dirname(__file__))

from lib import (
    BASE, USERS, REQUIRED_BY_TYPE, CKEDITOR_FIELDS,
    open_browser, new_ctx, login, fill_field, submit_form, created_ok,
    portal_links, run_cases, screenshot, screenshot_full, cleanup_nodes,
)
from playwright.sync_api import sync_playwright


def make_portal_cases(browser):
    """Generate test cases for each portal role."""
    cases = []

    PORTAL_ROLES = ["veterinario", "vendedor", "admin_clinica", "admin"]

    for role in PORTAL_ROLES:
        user, pwd, portal_url = USERS[role]

        # ── Login ────────────────────────────────────────────────────
        def _login(r=role, u=user, pw=pwd):
            def test():
                ctx, pg = new_ctx(browser)
                ok = login(pg, u, pw)
                assert ok, f"login failed for {r}"
                ctx.close()
            return test
        cases.append((f"{role}: login OK", _login()))

        # ── Portal renderiza ─────────────────────────────────────────
        def _render(r=role, u=user, pw=pwd, pu=portal_url):
            def test():
                ctx, pg = new_ctx(browser)
                ok = login(pg, u, pw)
                assert ok, f"login failed for {r}"
                pg.goto(BASE + pu, wait_until="load")
                body = pg.locator("body").inner_text()
                assert "Access denied" not in body, f"Access denied on portal"
                assert "Page not found" not in body, f"Page not found on portal"
                h1 = pg.locator("h1").first
                h1_txt = h1.inner_text() if h1.count() else "(sin h1)"
                assert h1_txt not in ("Access denied", "Page not found", "Not Found", ""), \
                    f"bad h1: {h1_txt!r}"
                screenshot(pg, "portales", f"{r}_portal_render")
                ctx.close()
            return test
        cases.append((f"{role}: portal renderiza", _render()))

        # ── Links del portal → 200 OK ───────────────────────────────
        def _links(r=role, u=user, pw=pwd, pu=portal_url):
            def test():
                ctx, pg = new_ctx(browser)
                ok = login(pg, u, pw)
                assert ok, f"login failed for {r}"
                links = portal_links(pg, pu)
                assert links, f"no portal links found at {pu}"
                fail_links = []
                for href in links:
                    resp = pg.goto(BASE + href, wait_until="load")
                    body = pg.locator("body").inner_text()
                    code = resp.status if resp else "?"
                    if code != 200 or "Access denied" in body or "Page not found" in body:
                        fail_links.append(f"{href} -> {code}")
                        screenshot(pg, "portales", f"{r}_link_fail_{href.replace('/', '_')}")
                        break  # stop on first failure, screenshot it
                ctx.close()
                assert not fail_links, f"links with errors: {fail_links}"
            return test
        cases.append((f"{role}: links del portal → 200", _links()))

        # ── Crear entidad desde portal ──────────────────────────────
        def _create(r=role, u=user, pw=pwd, pu=portal_url):
            def test():
                ctx, pg = new_ctx(browser)
                ok = login(pg, u, pw)
                assert ok, f"login failed for {r}"

                links = portal_links(pg, pu)
                create_links = [l for l in links if "/node/add/" in l]
                assert create_links, f"no /node/add/ links in portal"

                href = create_links[0]
                mtype = href.split("/node/add/")[-1]
                stamp = datetime.now().strftime("%H%M%S")
                title = f"E2E {u} {stamp}"

                resp = pg.goto(BASE + href, wait_until="load")
                code = resp.status if resp else "?"
                assert code == 200, f"form not accessible: {code}"

                pg.fill("#edit-title-0-value", title)

                for sel, val in REQUIRED_BY_TYPE.get(mtype, {}).items():
                    if not pg.locator(sel).count():
                        continue
                    fill_field(pg, sel, val)

                submit_form(pg)
                assert created_ok(pg), f"creation not confirmed for {mtype}"
                screenshot(pg, "portales", f"{r}_created_{mtype}")
                ctx.close()
            return test
        cases.append((f"{role}: crear entidad desde portal", _create()))

    return cases


def main():
    total = 0
    fail = 0

    with sync_playwright() as p:
        browser = open_browser(p)

        cases = make_portal_cases(browser)
        t, f = run_cases("portales", cases)
        total += t; fail += f

        cleanup_nodes("E2E ")
        browser.close()

    print(f"\n=== RESULTADO: {total - fail}/{total} OK, {fail} FAIL ===")
    return 1 if fail else 0


if __name__ == "__main__":
    sys.exit(main())
