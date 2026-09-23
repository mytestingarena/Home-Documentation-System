#!/usr/bin/env python3
"""Convert Visio (.vsdx/.vsd/.vsdm) -> draw.io XML via headless Chromium.

Uses the LAN draw.io UI (same import quality as manual open). Prefer the
HDS same-origin proxy (/incur/drawio/) so #U can fetch designs-file.php
without draw.io's cross-origin /proxy servlet (which blocks LAN URLs).
"""
from __future__ import annotations

import argparse
import re
import sys
import time
import urllib.parse
from pathlib import Path

from playwright.sync_api import sync_playwright

HOOK_PATH = Path(__file__).with_name("vsdx-to-drawio-hook.js")
# Same-origin HDS proxy — required for #U loads of designs-file.php
DEFAULT_DRAWIO = "http://192.168.1.110/incur/drawio"
DEFAULT_DESIGNS_FILE = "http://192.168.1.110/incur/designs-file.php"


def build_viewer_url(file_url: str, drawio_base: str, title: str) -> str:
    base = drawio_base.rstrip("/")
    return (
        f"{base}/?ui=min&splash=0&nav=1&layers=1&pages=1"
        f"&title={urllib.parse.quote(title)}"
        f"#U{urllib.parse.quote(file_url, safe='')}"
    )


def file_url_for_design(filename: str, designs_file: str = DEFAULT_DESIGNS_FILE) -> str:
    return designs_file.rstrip("?") + "?f=" + urllib.parse.quote(filename)


def convert(vsdx_url: str, drawio_base: str, out_path: str, timeout_s: int = 120) -> dict:
    hook = HOOK_PATH.read_text(encoding="utf-8")
    title = Path(urllib.parse.urlparse(vsdx_url).path).name or "convert.vsdx"
    # Prefer query filename when present
    qs = urllib.parse.parse_qs(urllib.parse.urlparse(vsdx_url).query)
    if qs.get("f"):
        title = qs["f"][0]
    viewer = build_viewer_url(vsdx_url, drawio_base, title)
    print("OPEN", viewer, flush=True)

    xml = None
    meta: dict = {}
    with sync_playwright() as p:
        browser = p.chromium.launch(
            headless=True,
            args=["--disable-dev-shm-usage", "--no-sandbox", "--disable-gpu"],
        )
        page = browser.new_page()
        page.set_default_timeout(timeout_s * 1000)
        page.add_init_script(hook)
        page.goto(viewer, wait_until="domcontentloaded")

        deadline = time.time() + timeout_s
        while time.time() < deadline:
            state = page.evaluate(
                """() => {
                  const ui = window.__hdsUi;
                  if (!ui) {
                    return {
                      ready: false,
                      hooked: !!window.__hdsHookInstalled,
                      patched: !!(window.EditorUi && window.EditorUi.__hdsPatched)
                    };
                  }
                  const f = ui.getCurrentFile && ui.getCurrentFile();
                  const title = f && f.getTitle && f.getTitle();
                  const pages = (ui.pages && ui.pages.length) || 0;
                  const spinner = !!(ui.spinner && ui.spinner.active);
                  const geInfo = document.getElementById('geInfo');
                  const loading = !!(geInfo && geInfo.offsetParent !== null);
                  const dialogs = [...document.querySelectorAll('.geDialog')]
                    .map(e => (e.innerText || '').slice(0, 80));
                  return {
                    ready: true,
                    pages,
                    title,
                    spinner,
                    loading,
                    dialogs,
                    fileLoadedAt: window.__hdsLastFileLoaded || null
                  };
                }"""
            )
            print("state", state, flush=True)

            # Dismiss error dialogs (e.g. transient) so they don't block
            if state.get("dialogs"):
                try:
                    page.keyboard.press("Escape")
                except Exception:
                    pass

            if (
                state.get("ready")
                and not state.get("spinner")
                and not state.get("loading")
                and (state.get("pages") or 0) >= 1
                and state.get("fileLoadedAt")
            ):
                xml = page.evaluate(
                    """() => {
                      const ui = window.__hdsUi;
                      if (!ui || typeof ui.getFileData !== 'function') return null;
                      return ui.getFileData(true, null, null, null, true, false);
                    }"""
                )
                if xml and "<mxfile" in xml and "<diagram" in xml and len(xml) > 500:
                    meta = {
                        "pages": len(re.findall(r"<diagram\b", xml)),
                        "title": state.get("title"),
                    }
                    break
                xml = None
            time.sleep(1.0)

        shot = str(out_path) + ".png"
        try:
            page.screenshot(path=shot, full_page=True)
        except Exception:
            pass
        browser.close()

    if not xml:
        raise RuntimeError("draw.io export timed out or returned no mxfile")
    Path(out_path).write_text(xml, encoding="utf-8")
    meta["pages"] = len(re.findall(r"<diagram\b", xml))
    meta["bytes"] = len(xml)
    meta["out"] = out_path
    return meta


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--url", help="HTTP URL to Visio file (designs-file.php?f=...)")
    ap.add_argument("--filename", help="Designs filename; builds designs-file.php URL")
    ap.add_argument("--out", required=True, help="Output .drawio path")
    ap.add_argument("--drawio", default=DEFAULT_DRAWIO)
    ap.add_argument("--designs-file", default=DEFAULT_DESIGNS_FILE)
    ap.add_argument("--timeout", type=int, default=120)
    args = ap.parse_args()

    if args.filename:
        url = file_url_for_design(args.filename, args.designs_file)
    elif args.url:
        url = args.url
    else:
        ap.error("Provide --url or --filename")

    try:
        meta = convert(url, args.drawio, args.out, args.timeout)
    except Exception as e:
        print("FAIL", e, file=sys.stderr)
        return 1
    print("OK", meta)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
