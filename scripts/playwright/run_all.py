"""E2E: runner — ejecuta todas las suites de Playwright MascoLive.

Correr: uv run python scripts/playwright/run_all.py
"""
import contextlib
import io
import sys
import os

sys.path.insert(0, os.path.dirname(__file__))

# (nombre, módulo)
SUITES = [
    ("contenido_publico", "e2e_contenido_publico"),
    ("login_redirect", "e2e_login_redirect"),
    ("portales", "e2e_portales"),
    ("registro", "e2e_registro"),
    ("comercio", "e2e_comercio"),
    ("moderacion", "e2e_moderacion"),
]


def run_suite(name, module_name):
    """Run one suite in-process; capture stdout; count [OK]/[FAIL] lines."""
    buf = io.StringIO()
    try:
        with contextlib.redirect_stdout(buf):
            mod = __import__(module_name)
            exit_code = mod.main()
    except Exception as e:
        return 1, 1, f"EXCEPTION: {e}\n{buf.getvalue()}", True

    out = buf.getvalue()
    ok = out.count("  [OK]")
    fail = out.count("  [FAIL]")
    return ok, fail, out, exit_code != 0 or fail > 0


def main():
    results = []
    for name, module_name in SUITES:
        print(f"\n{'='*60}\n  SUITE: {name}\n{'='*60}")
        ok, fail, out, failed = run_suite(name, module_name)
        print(out)
        results.append((name, ok, fail, failed))
        if failed:
            print(f"  >>> suite {name}: {ok} OK / {fail} FAIL")

    # Resumen global
    print(f"\n{'='*60}")
    print("  RESUMEN GLOBAL")
    print(f"{'='*60}")
    grand_total = sum(ok + f for _, ok, f, _ in results)
    grand_fail = sum(f for _, _, f, _ in results)
    failed_suites = sum(1 for _, _, _, f in results if f)
    for name, ok, fail, failed in results:
        status = "FAIL" if failed else "OK"
        print(f"  [{status}] {name}: {ok} OK / {fail} FAIL")
    print(f"\n  Total casos: {grand_total - grand_fail}/{grand_total} OK, {grand_fail} FAIL")
    print(f"  Suites: {len(results) - failed_suites}/{len(results)} OK, {failed_suites} FAIL")
    print(f"{'='*60}")

    return 1 if failed_suites else 0


if __name__ == "__main__":
    sys.exit(main())