"""E2E: login + redirect por rol MascoLive.

Verifica que cada usuario es redirigido a su portal/URL esperada tras login.

Correr: uv run python scripts/playwright/e2e_login_redirect.py
"""
import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

from lib import BASE, USERS, open_browser, new_ctx, login, run_cases
from playwright.sync_api import sync_playwright


# (rol, portal_path) — omitimos tutor y conductor que van a /es (front)
ROLES = [
    ("veterinario", "/es/portal-veterinario"),
    ("vendedor", "/es/portal-vendedor"),
    ("admin_clinica", "/es/portal-admin-de-clinica"),
    ("admin", "/es/panel-de-administracion"),
    ("tutor", "/es"),
    ("conductor", "/es"),
]


def main():
    total = 0
    fail = 0

    with sync_playwright() as p:
        browser = open_browser(p)

        for role, expected_path in ROLES:
            user, pwd, _ = USERS[role]

            def _make_test(r=role, u=user, pw=pwd, ep=expected_path):
                def test_login_redirect():
                    ctx, pg = new_ctx(browser)
                    ok = login(pg, u, pw)
                    assert ok, f"login failed for {r}"

                    final_url = pg.url
                    # Normalize: strip query string and trailing slash for comparison
                    path = final_url.replace(BASE, "").split("?")[0].rstrip("/") or "/"
                    expected = ep.rstrip("/") or "/"

                    # For /es (front pages), accept /es, /, or /es/acerca-de-mascolive
                    if expected == "/es":
                        ok_url = path in ("/es", "/", "") or path.startswith("/es/")
                    else:
                        ok_url = path == expected or final_url.startswith(BASE + ep)

                    assert ok_url, f"expected {expected_path}, got {final_url}"

                    # Extra check for admin: no "Access denied"
                    if r == "admin":
                        body = pg.locator("body").inner_text()
                        assert "Access denied" not in body, f"Access denied after admin login"

                    ctx.close()
                return test_login_redirect

            total += 1
            try:
                _make_test()()
                print(f"  [OK] login {role} → {expected_path}")
            except Exception as e:
                fail += 1
                print(f"  [FAIL] login {role} → {expected_path}: {e}")

        browser.close()

    print(f"\n=== RESULTADO: {total - fail}/{total} OK, {fail} FAIL ===")
    return 1 if fail else 0


if __name__ == "__main__":
    sys.exit(main())
