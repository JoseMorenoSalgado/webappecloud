#!/usr/bin/env python3
"""Local-only mock for read-only Dokploy smoke tests. NOT a deployment server."""
import json
import os
import ssl
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlparse, parse_qs

KEY = "test-key-very-long"
APP_ID = "stagingapp123"

class Handler(BaseHTTPRequestHandler):
    def log_message(self, fmt, *args):
        return  # Do not log headers/tokens.

    def response(self, code, body):
        content = json.dumps(body).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(content)))
        self.end_headers()
        self.wfile.write(content)

    def do_GET(self):
        if self.headers.get("x-api-key") != KEY:
            return self.response(401, {"error": "unauthorized"})
        route = urlparse(self.path)
        if route.path == "/api/project.all":
            return self.response(200, [{"projectId": "stage", "name": "test-project"}])
        if route.path == "/api/application.one":
            app = parse_qs(route.query).get("applicationId", [""])[0]
            if app != APP_ID:
                return self.response(404, {"error": "not found"})
            return self.response(200, {"applicationId": APP_ID, "applicationStatus": "idle"})
        if route.path == "/api/deployment.all":
            app = parse_qs(route.query).get("applicationId", [""])[0]
            if app != APP_ID:
                return self.response(404, {"error": "not found"})
            return self.response(200, [{"status": "done", "createdAt": "2026-10-24T16:00:00Z"}])
        return self.response(404, {"error": "unknown endpoint"})

    def do_POST(self):
        return self.response(405, {"error": "read-only mock; no writes permitted"})

server = HTTPServer(("127.0.0.1", 443), Handler)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(os.environ["MOCK_CERT"], os.environ["MOCK_KEY"])
server.socket = context.wrap_socket(server.socket, server_side=True)
server.serve_forever()
