#!/usr/bin/env python3
"""Serve the NexTradeAI marketing website locally (HTML/CSS/JS/assets only)."""
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import os

ROOT = Path(__file__).resolve().parents[1] / "website"
PORT = int(os.environ.get("WEBSITE_PORT", "8080"))
ALLOWED = {
    ".html", ".css", ".js", ".png", ".jpg", ".jpeg", ".gif", ".svg",
    ".webp", ".ico", ".woff", ".woff2", ".ttf", ".mp4", ".webm", ".json",
    ".map", ".txt", ".apk",
}


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def log_message(self, fmt, *args):
        print("[%s] %s" % (self.log_date_time_string(), fmt % args))

    def do_GET(self):
        path = self.translate_path(self.path)
        rel = Path(path)
        if rel.is_dir():
            if (rel / "index.html").is_file():
                return super().do_GET()
            self.send_error(404, "Not found")
            return
        suffix = rel.suffix.lower()
        if suffix == ".php" or suffix not in ALLOWED:
            self.send_error(404, "Not found")
            return
        return super().do_GET()


if __name__ == "__main__":
    os.chdir(ROOT)
    httpd = ThreadingHTTPServer(("127.0.0.1", PORT), Handler)
    print(f"NexTradeAI website → http://localhost:{PORT}")
    print(f"Serving {ROOT}")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nStopped.")
