"""Shared helpers for MascoLive E2E Playwright suites.

Correr: importado por los módulos e2e_*.
No ejecuta nada standalone.
"""
import os
import re
import subprocess
from playwright.sync_api import sync_playwright

# ── Constantes ───────────────────────────────────────────────────────────────
BASE = "http://mascolive.local:8080"
CHROME = "/usr/bin/google-chrome-stable"
REPO = os.path.join(os.path.dirname(__file__), "..", "..")

# rol → (usuario, password, portal_url)
USERS = {
    "veterinario": ("veterinario", "veterinario", "/es/portal-veterinario"),
    "vendedor": ("vendedor", "vendedor", "/es/portal-vendedor"),
    "admin_clinica": ("admin_clinica", "admin_clinica", "/es/portal-admin-de-clinica"),
    "admin": ("admin", "admin", "/es/panel-de-administracion"),
    "tutor": ("tutor", "tutor", "/es"),
    "conductor": ("conductor", "conductor", "/es"),
}

# Campos que usan ckeditor5: textarea oculto, hay que teclear en .ck-editor__editable
CKEDITOR_FIELDS = {"#edit-field-description-0-value", "#edit-body-0-value"}

# Regex para links del portal (admin, node/add, product)
LINK_RE = re.compile(r"^/es/(admin/|node/add/|product/)")

# ── Browser helpers ──────────────────────────────────────────────────────────

def open_browser(pw):
    """Launch chromium headed with the known binary."""
    return pw.chromium.launch(headless=False, executable_path=CHROME)


def new_ctx(browser):
    """New context + page with standard viewport and timeout."""
    ctx = browser.new_context(viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    pg.set_default_timeout(20000)
    return ctx, pg


# ── Login ────────────────────────────────────────────────────────────────────

def login(pg, user, pwd):
    """Login with retry. Drupal sometimes rejects the 1st POST (race/flood).

    Returns True on success, False after 3 failed attempts.
    """
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
        if ("Unrecognized username or password" not in body
                and "user/login" not in pg.url):
            return True
        print(f"    (intento {attempt + 1} falló, reintentando)")
        pg.wait_for_timeout(1200)
    return False


# ── Form helpers ─────────────────────────────────────────────────────────────

def fill_field(pg, sel, val):
    """Fill a field, handling ckeditor5 and hidden textareas.

    Gotcha 3: ckeditor fields need typing into .ck-editor__editable;
    hidden textareas need JS value setter + input event dispatch.
    """
    if sel in CKEDITOR_FIELDS:
        editor = pg.locator(".ck-editor__editable").first
        if editor.count():
            editor.click()
            pg.keyboard.type(val, delay=10)
            return
    try:
        pg.fill(sel, val)
    except Exception:
        # textarea oculto → setear vía JS
        pg.locator(sel).evaluate(
            "(el, v) => { el.value = v; el.dispatchEvent(new Event('input', {bubbles:true})); }",
            val,
        )


def submit_form(pg):
    """Submit node/entity form, bypassing Gin overlay.

    Gotcha 2: Gin overlay intercepts pg.click("#edit-submit") on node/add forms.
    Solution: form.requestSubmit(btn) — incluye el botón `op` (requerido por
    forms con content_moderation) y dispara el evento submit (sincroniza ckeditor).
    Fallback: form.submit() nativo (funciona en forms de nodo sin moderación) o
    click force en #edit-submit si no hay form.
    """
    form = pg.locator("form[data-drupal-selector]").first
    if form.count():
        handled = form.evaluate("""f => {
            try {
                if (typeof f.requestSubmit === 'function') {
                    const btn = f.querySelector('input[type=submit][name=op], button[name=op]');
                    if (btn) { f.requestSubmit(btn); return true; }
                }
            } catch (e) {}
            try { f.submit(); } catch (e) {}
            return false;
        }""")
    else:
        pg.click("#edit-submit", force=True)
    pg.wait_for_timeout(2500)
    try:
        pg.wait_for_load_state("load")
    except Exception:
        pass


def created_ok(pg):
    """Check if body contains Drupal success messages.

    Detects both Spanish and English variants.
    """
    body = pg.locator("body").inner_text()
    return any(msg in body for msg in (
        "ha sido creado", "has been created",
        "ha sido guardado", "has been updated",
    ))


# ── Portal helpers ───────────────────────────────────────────────────────────

def portal_links(pg, portal_url):
    """Collect links from portal page matching admin/node/add/product patterns."""
    pg.goto(BASE + portal_url, wait_until="load")
    hrefs = set()
    for a in pg.locator("main a[href], .region-content a[href]").all():
        href = a.get_attribute("href") or ""
        if LINK_RE.match(href):
            hrefs.add(href)
    # fallback: search entire document if portal doesn't use main/.region-content
    if not hrefs:
        for a in pg.locator("a[href]").all():
            href = a.get_attribute("href") or ""
            if LINK_RE.match(href):
                hrefs.add(href)
    return sorted(hrefs)


# ── Required fields by content type ──────────────────────────────────────────

REQUIRED_BY_TYPE = {
    "service": {
        "#edit-field-description-0-value": "Servicio E2E generado",
        "#edit-field-duration-0-value": "30 min",
        "#edit-field-price-0-value": "500",
    },
}


# ── Runner ───────────────────────────────────────────────────────────────────

def run_cases(suite_name, cases):
    """Execute a list of (description, callable) cases.

    Returns (total, fail_count).
    Each callable receives no args (use closures for page/browser access).
    Exceptions → FAIL with error detail.
    """
    total = fail = 0
    results = []

    for desc, fn in cases:
        total += 1
        try:
            fn()
            results.append((True, desc, ""))
            print(f"  [OK] {desc}")
        except Exception as e:
            fail += 1
            results.append((False, desc, str(e)))
            print(f"  [FAIL] {desc}: {e}")

    print(f"\n=== {suite_name}: {total - fail}/{total} OK, {fail} FAIL ===")
    for ok, label, extra in results:
        if not ok:
            print(f"  FAIL → {label}: {extra}")

    return total, fail


# ── Cleanup ──────────────────────────────────────────────────────────────────

def cleanup_nodes(prefix):
    """Delete nodes whose title starts with prefix via drush SQL.

    Uses ddev drush sql:query. Prefix recommended: "E2E " (with space).
    """
    # Escape single quotes in prefix for SQL
    safe_prefix = prefix.replace("'", "''")
    cmd = f"DELETE FROM node_field_data WHERE title LIKE '{safe_prefix}%'"
    try:
        subprocess.run(
            ["ddev", "drush", "sql:query", cmd],
            cwd=REPO, capture_output=True, text=True, timeout=30,
        )
        subprocess.run(
            ["ddev", "drush", "cr"],
            cwd=REPO, capture_output=True, text=True, timeout=30,
        )
    except Exception as e:
        print(f"  (cleanup_nodes falló: {e})")


def cleanup_users(prefix):
    """Delete users whose name starts with prefix.

    Prefix recommended: "e2e_".
    """
    try:
        # Get matching usernames (sql:query imprime un nombre por línea)
        cmd = f"SELECT name FROM users_field_data WHERE name LIKE '{prefix}%'"
        result = subprocess.run(
            ["ddev", "drush", "sql:query", cmd],
            cwd=REPO, capture_output=True, text=True, timeout=30,
        )
        names = [line.strip() for line in result.stdout.split("\n")
                 if line.strip() and line.strip() != "name"]
        for name in names:
            subprocess.run(
                ["ddev", "drush", "user:cancel", name, "--delete-content", "-y"],
                cwd=REPO, capture_output=True, text=True, timeout=30,
            )
        if names:
            subprocess.run(
                ["ddev", "drush", "cr"],
                cwd=REPO, capture_output=True, text=True, timeout=30,
            )
    except Exception as e:
        print(f"  (cleanup_users falló: {e})")


def cleanup_commerce_products(prefix):
    """Delete commerce products and orphan variations whose title/sku starts with prefix."""
    safe_prefix = prefix.replace("'", "''")
    try:
        subprocess.run(
            ["ddev", "drush", "sql:query",
             f"DELETE FROM commerce_product_field_data WHERE title LIKE '{safe_prefix}%'"],
            cwd=REPO, capture_output=True, text=True, timeout=30,
        )
        subprocess.run(
            ["ddev", "drush", "sql:query",
             f"DELETE FROM commerce_product_variation_field_data WHERE sku LIKE '{safe_prefix}%'"],
            cwd=REPO, capture_output=True, text=True, timeout=30,
        )
        subprocess.run(
            ["ddev", "drush", "cr"],
            cwd=REPO, capture_output=True, text=True, timeout=30,
        )
    except Exception as e:
        print(f"  (cleanup_commerce_products falló: {e})")


_SHOTS_ROOT = os.path.join(os.path.dirname(__file__), "..", "..", "screenshots")


def screenshot(pg, suite, name):
    """Save a screenshot to screenshots/e2e_<suite>/<name>.png."""
    d = os.path.join(_SHOTS_ROOT, f"e2e_{suite}")
    os.makedirs(d, exist_ok=True)
    pg.screenshot(path=os.path.join(d, f"{name}.png"), full_page=False)


def screenshot_full(pg, suite, name):
    """Save a full-page screenshot."""
    d = os.path.join(_SHOTS_ROOT, f"e2e_{suite}")
    os.makedirs(d, exist_ok=True)
    pg.screenshot(path=os.path.join(d, f"{name}.png"), full_page=True)
