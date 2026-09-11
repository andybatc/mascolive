"""E2E: registro público MascoLive.

Verifica selector de rol en /es/user/register, registro de tutor y vendedor.
El form de registro NO tiene campo de password (Drupal lo envía por email).
Usamos drush user:password para setear la password tras registro.

Correr: uv run python scripts/playwright/e2e_registro.py
"""
import os
import subprocess
import sys
from datetime import datetime

sys.path.insert(0, os.path.dirname(__file__))

from lib import BASE, REPO, open_browser, new_ctx, login, run_cases, screenshot, cleanup_users
from playwright.sync_api import sync_playwright


def _set_password(name, pwd):
    """Set user password via ddev drush."""
    subprocess.run(
        ["ddev", "drush", "user:password", name, pwd],
        cwd=REPO, capture_output=True, text=True, timeout=30,
    )


def main():
    total = 0
    fail = 0
    stamp = datetime.now().strftime("%Y%m%d%H%M%S")

    with sync_playwright() as p:
        browser = open_browser(p)

        # ── Caso A: selector de rol presente ──────────────────────────────
        def test_role_selector():
            ctx, pg = new_ctx(browser)
            resp = pg.goto(BASE + "/es/user/register", wait_until="load")
            assert resp and resp.status == 200, f"status={resp.status if resp else '?'}"
            sel = pg.locator("#edit-mascolive-role")
            assert sel.count(), "role selector #edit-mascolive-role not found"
            options = pg.locator("#edit-mascolive-role option").all()
            opt_values = [o.get_attribute("value") or "" for o in options]
            opt_values = [v for v in opt_values if v]  # remove empty
            expected = {"tutor", "veterinario", "conductor", "vendedor", "clinica_admin"}
            found = set(opt_values)
            assert expected.issubset(found), f"missing roles: {expected - found}, got: {found}"
            ctx.close()

        # ── Caso B: registrar tutor ───────────────────────────────────────
        def test_register_tutor():
            ctx, pg = new_ctx(browser)
            pg.goto(BASE + "/es/user/register", wait_until="load")

            uname = f"e2e_tutor_{stamp}"
            pg.fill("#edit-name", uname)
            pg.fill("#edit-mail", f"{uname}@e2e.test")
            pg.select_option("#edit-mascolive-role", value="tutor")
            pg.click("#edit-submit")
            try:
                pg.wait_for_load_state("networkidle", timeout=10000)
            except Exception:
                pass
            pg.wait_for_timeout(800)

            body = pg.locator("body").inner_text()
            registered = any(msg in body for msg in (
                "ha sido creado", "has been created",
                "ha sido creado correctamente", "has been created successfully",
                "ha sido enviado", "has been emailed",
                "sent to your email address",
            ))

            screenshot(pg, "registro", "tutor_register_result")
            ctx.close()

            assert registered, f"registration not confirmed. body[:400]={body[:400]!r}"

            # Set password via drush, then login
            _set_password(uname, uname)
            ctx2, pg2 = new_ctx(browser)
            ok = login(pg2, uname, uname)
            assert ok, f"login failed after registration for {uname}"

            final_url = pg2.url
            path = final_url.replace(BASE, "").split("?")[0]
            assert path.startswith("/es"), f"expected /es redirect, got {final_url}"

            screenshot(pg2, "registro", "tutor_after_login")
            ctx2.close()

        # ── Caso C: registrar vendedor ────────────────────────────────────
        def test_register_vendedor():
            ctx, pg = new_ctx(browser)
            pg.goto(BASE + "/es/user/register", wait_until="load")

            uname = f"e2e_vendedor_{stamp}"
            pg.fill("#edit-name", uname)
            pg.fill("#edit-mail", f"{uname}@e2e.test")
            pg.select_option("#edit-mascolive-role", value="vendedor")
            pg.click("#edit-submit")
            try:
                pg.wait_for_load_state("networkidle", timeout=10000)
            except Exception:
                pass
            pg.wait_for_timeout(800)

            body = pg.locator("body").inner_text()
            registered = any(msg in body for msg in (
                "ha sido creado", "has been created",
                "ha sido creado correctamente", "has been created successfully",
                "ha sido enviado", "has been emailed",
                "sent to your email address",
            ))

            screenshot(pg, "registro", "vendedor_register_result")
            ctx.close()

            assert registered, f"registration not confirmed. body[:400]={body[:400]!r}"

            # Set password via drush, then login
            _set_password(uname, uname)
            ctx2, pg2 = new_ctx(browser)
            ok = login(pg2, uname, uname)
            assert ok, f"login failed after registration for {uname}"

            final_url = pg2.url
            path = final_url.replace(BASE, "").split("?")[0].rstrip("/")
            assert path == "/es/portal-vendedor", \
                f"expected /es/portal-vendedor, got {final_url}"

            screenshot(pg2, "registro", "vendedor_after_login")
            ctx2.close()

        # ── RUN ──────────────────────────────────────────────────────────
        cases = [
            ("Selector de rol en /es/user/register", test_role_selector),
            ("Registrar tutor + login → redirige a /es", test_register_tutor),
            ("Registrar vendedor + login → redirige a /es/portal-vendedor", test_register_vendedor),
        ]
        t, f = run_cases("registro", cases)
        total += t; fail += f

        cleanup_users("e2e_")
        browser.close()

    print(f"\n=== RESULTADO: {total - fail}/{total} OK, {fail} FAIL ===")
    return 1 if fail else 0


if __name__ == "__main__":
    sys.exit(main())
