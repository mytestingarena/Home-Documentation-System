#!/usr/bin/env python3
"""Tiny LAN HTTP service: Visio -> draw.io conversion for HDS upload hook.

POST /convert  JSON {"filename":"....vsdx"} or form filename=...
Returns: application/xml (mxfile) on success, or JSON error.

Only serves the LAN. Fetches the Visio via HDS designs-file.php (#U).
"""
from __future__ import annotations

import json
import os
import re
import tempfile
import traceback
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

# Reuse converter helpers
import importlib.util

ROOT = Path(__file__).resolve().parent
SPEC = importlib.util.spec_from_file_location("vsdx_to_drawio", ROOT / "vsdx-to-drawio.py")
mod = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(mod)

HOST = os.environ.get("HDS_VSDX_CONVERT_HOST", "0.0.0.0")
PORT = int(os.environ.get("HDS_VSDX_CONVERT_PORT", "8765"))
DRAWIO = os.environ.get("HDS_DRAWIO_URL_ABS", mod.DEFAULT_DRAWIO)
DESIGNS = os.environ.get("HDS_DESIGNS_FILE", mod.DEFAULT_DESIGNS_FILE)
TIMEOUT = int(os.environ.get("HDS_VSDX_CONVERT_TIMEOUT", "120"))
SAFE_NAME = re.compile(r"^[A-Za-z0-9._() \-]+$")
VISIO_EXT = re.compile(r"\.(vsdx|vsd|vsdm)$", re.I)


class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, fmt, *args):
        sys_stderr = __import__("sys").stderr
        print(f"[vsdx-convert] {self.address_string()} {fmt % args}", file=sys_stderr, flush=True)

    def _deny(self, code: int, msg: str):
        body = json.dumps({"ok": False, "error": msg}).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _client_ok(self) -> bool:
        # Allow LAN + localhost only
        ip = self.client_address[0]
        return (
            ip.startswith("192.168.")
            or ip.startswith("10.")
            or ip.startswith("127.")
            or ip == "::1"
        )

    def do_GET(self):
        if urlparse(self.path).path in ("/", "/health"):
            body = b'{"ok":true,"service":"hds-vsdx-to-drawio"}\n'
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        self._deny(404, "not found")

    def do_POST(self):
        if not self._client_ok():
            self._deny(403, "forbidden")
            return
        if urlparse(self.path).path != "/convert":
            self._deny(404, "not found")
            return

        length = int(self.headers.get("Content-Length") or 0)
        if length <= 0 or length > 1_000_000:
            self._deny(400, "bad body")
            return
        raw = self.rfile.read(length)
        ctype = (self.headers.get("Content-Type") or "").split(";")[0].strip()
        filename = None
        try:
            if ctype == "application/json":
                data = json.loads(raw.decode("utf-8"))
                filename = data.get("filename")
            else:
                qs = parse_qs(raw.decode("utf-8"))
                filename = (qs.get("filename") or [None])[0]
        except Exception:
            self._deny(400, "invalid body")
            return

        if not filename or not isinstance(filename, str):
            self._deny(400, "filename required")
            return
        filename = Path(filename).name
        if not SAFE_NAME.match(filename) or not VISIO_EXT.search(filename):
            self._deny(400, "invalid filename")
            return

        url = mod.file_url_for_design(filename, DESIGNS)
        try:
            with tempfile.TemporaryDirectory(prefix="hds-vsdx-") as td:
                out = str(Path(td) / (Path(filename).stem + ".drawio"))
                meta = mod.convert(url, DRAWIO, out, TIMEOUT)
                xml = Path(out).read_bytes()
        except Exception as e:
            traceback.print_exc()
            self._deny(500, str(e))
            return

        self.send_response(200)
        self.send_header("Content-Type", "application/vnd.jgraph.mxfile; charset=utf-8")
        self.send_header("Content-Length", str(len(xml)))
        self.send_header("X-HDS-Pages", str(meta.get("pages", "")))
        self.send_header("X-HDS-Bytes", str(meta.get("bytes", "")))
        self.end_headers()
        self.wfile.write(xml)


def main():
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"[vsdx-convert] listening on {HOST}:{PORT} drawio={DRAWIO}", flush=True)
    httpd.serve_forever()


if __name__ == "__main__":
    main()
