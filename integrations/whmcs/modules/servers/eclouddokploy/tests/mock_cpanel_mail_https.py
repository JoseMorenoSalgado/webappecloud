#!/usr/bin/env python3
"""Ephemeral cPanel UAPI HTTPS fake; mail actions affect memory only."""
import json
import os
import ssl
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import parse_qs, urlparse

TOKEN = "cpanel-staging-token-123456789"
USER = "eclient"
DOMAIN = "empresa.test"
MAILBOXES = {
    "admin": {"email": "admin@empresa.test", "diskquota": 512},
}


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args):
        return  # No URLs, passwords or API tokens in CI logs.

    def reply(self, status, success=True, data=None):
        body = json.dumps({
            "apiversion": 3,
            "module": "Email",
            "result": {
                "status": 1 if success else 0,
                "data": data,
                "errors": None if success else ["Denied"],
            },
        }).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def allowed(self):
        if self.headers.get("Authorization") != f"cpanel {USER}:{TOKEN}":
            self.reply(401, False)
            return False
        return True

    def do_GET(self):
        if not self.allowed():
            return
        path = urlparse(self.path)
        args = parse_qs(path.query)
        if path.path != "/execute/Email/list_pops_with_disk" or "password" in args:
            return self.reply(405, False)
        if args.get("domain", [""])[0] != DOMAIN:
            return self.reply(200, False)
        return self.reply(200, True, list(MAILBOXES.values()))

    def do_POST(self):
        if not self.allowed():
            return
        path = urlparse(self.path)
        if path.query:
            return self.reply(400, False)
        length = int(self.headers.get("Content-Length") or "0")
        if length < 1 or length > 4096:
            return self.reply(400, False)
        data = parse_qs(self.rfile.read(length).decode("utf-8"))
        take = lambda key: data.get(key, [""])[0]
        if take("domain") != DOMAIN:
            return self.reply(200, False)
        local = take("email")
        if not local or "@" in local:
            return self.reply(200, False)

        if path.path == "/execute/Email/add_pop":
            if local in MAILBOXES or not take("password"):
                return self.reply(200, False)
            MAILBOXES[local] = {
                "email": f"{local}@{DOMAIN}", "diskquota": int(take("quota"))
            }
            return self.reply(200, True, "created")
        if path.path == "/execute/Email/passwd_pop":
            if local not in MAILBOXES or not take("password"):
                return self.reply(200, False)
            return self.reply(200, True, 1)
        if path.path == "/execute/Email/edit_pop_quota":
            if local not in MAILBOXES:
                return self.reply(200, False)
            MAILBOXES[local]["diskquota"] = int(take("quota"))
            return self.reply(200, True, 1)
        if path.path == "/execute/Email/delete_pop":
            if local not in MAILBOXES:
                return self.reply(200, False)
            del MAILBOXES[local]
            return self.reply(200, True, 1)
        return self.reply(404, False)


server = HTTPServer(("127.0.0.1", 2083), Handler)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(os.environ["MOCK_CERT"], os.environ["MOCK_KEY"])
server.socket = context.wrap_socket(server.socket, server_side=True)
server.serve_forever()
