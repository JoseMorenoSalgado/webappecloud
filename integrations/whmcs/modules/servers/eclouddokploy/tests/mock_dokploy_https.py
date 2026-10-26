#!/usr/bin/env python3
"""Local-only HTTPS Dokploy API mock. Writes modify MEMORY only, never real VPS."""
import json
import os
import ssl
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import parse_qs, urlparse

KEY = "test-key-very-long"
STAGING_APP_ID = "stagingapp123"
projects = {}
environments = {}
applications = {}
counter = {"project": 0, "env": 0, "app": 0}


def new_id(kind):
    counter[kind] += 1
    return f"{kind}-{counter[kind]}-mock"


class Handler(BaseHTTPRequestHandler):
    def log_message(self, fmt, *args):
        return  # Never log auth headers or response bodies.

    def response(self, code, body):
        content = json.dumps(body).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(content)))
        self.end_headers()
        self.wfile.write(content)

    def authorized(self):
        if self.headers.get("x-api-key") != KEY:
            self.response(401, {"error": "unauthorized"})
            return False
        return True

    def do_GET(self):
        if not self.authorized():
            return
        url = urlparse(self.path)
        query = parse_qs(url.query)
        if url.path == "/api/project.search":
            # Official Dokploy router returns only compact project fields
            # {items, total}, unlike project.all with nested service secrets.
            needle = query.get("name", [""])[0].lower()
            if not needle:
                return self.response(400, {"error": "project name required"})
            filtered = [
                dict(item) for item in projects.values()
                if needle in str(item.get("name", "")).lower()
            ]
            try:
                limit = int(query.get("limit", ["20"])[0])
                offset = int(query.get("offset", ["0"])[0])
            except ValueError:
                return self.response(400, {"error": "invalid search bounds"})
            if limit < 1 or limit > 100 or offset < 0:
                return self.response(400, {"error": "invalid search limit"})
            return self.response(200, {
                "items": filtered[offset:offset + limit],
                "total": len(filtered),
            })
        if url.path == "/api/project.one":
            pid = query.get("projectId", [""])[0]
            item = projects.get(pid)
            if item is None:
                return self.response(404, {"error": "project not found"})
            return self.response(200, dict(item))
        if url.path == "/api/project.all":
            return self.response(
                200, [{"projectId": "stage", "name": "test-project"}] + list(projects.values())
            )
        if url.path == "/api/environment.byProjectId":
            pid = query.get("projectId", [""])[0]
            if pid not in projects:
                return self.response(404, {"error": "unknown project"})
            return self.response(200, [e for e in environments.values() if e["projectId"] == pid])
        if url.path == "/api/application.one":
            app = query.get("applicationId", [""])[0]
            if app == STAGING_APP_ID:
                return self.response(200, {"applicationId": app, "applicationStatus": "idle"})
            found = applications.get(app)
            if not found:
                return self.response(404, {"error": "unknown app"})
            return self.response(200, found)
        if url.path == "/api/deployment.all":
            app = query.get("applicationId", [""])[0]
            if app != STAGING_APP_ID and app not in applications:
                return self.response(404, {"error": "unknown app"})
            return self.response(200, [{"status": "done", "createdAt": "2026-10-24T16:00:00Z"}])
        return self.response(404, {"error": "unknown endpoint"})

    def do_POST(self):
        if not self.authorized():
            return
        length = int(self.headers.get("Content-Length") or "0")
        if length < 1 or length > 8192:
            return self.response(400, {"error": "invalid request size"})
        try:
            data = json.loads(self.rfile.read(length))
        except (ValueError, TypeError):
            return self.response(400, {"error": "invalid json"})
        path = urlparse(self.path).path
        if path == "/api/project.remove":
            pid = data.get("projectId")
            if pid not in projects:
                return self.response(404, {"error": "unknown project"})
            related = [e["environmentId"] for e in environments.values()
                       if e["projectId"] == pid]
            if any(app.get("environmentId") in related for app in applications.values()):
                return self.response(409, {"error": "project still contains applications"})
            for eid in related:
                del environments[eid]
            del projects[pid]
            return self.response(200, {"success": True})
        if path == "/api/mock.renameProject":
            pid = data.get("projectId")
            if pid not in projects or not data.get("name"):
                return self.response(404, {"error": "unknown project"})
            projects[pid]["name"] = data["name"]
            return self.response(200, {"success": True})
        if path == "/api/project.create":
            name = data.get("name")
            if not isinstance(name, str) or not name.startswith("ec-client-"):
                return self.response(400, {"error": "invalid project name"})
            if any(p["name"] == name for p in projects.values()):
                return self.response(409, {"error": "duplicate project name"})
            pid = new_id("project")
            projects[pid] = {"projectId": pid, "name": name}
            # Simulate both Dokploy behaviors: auto-generated production or
            # environment creation required, depending on the test account.
            if not name.endswith(("service-311", "service-411", "service-415")):
                eid = new_id("env")
                environments[eid] = {
                    "projectId": pid, "environmentId": eid, "name": "production"
                }
            return self.response(200, {"projectId": pid})
        if path == "/api/environment.create":
            pid = data.get("projectId")
            if pid not in projects or data.get("name") != "production":
                return self.response(400, {"error": "invalid environment"})
            if any(
                env["projectId"] == pid and env["name"] == "production"
                for env in environments.values()
            ):
                return self.response(409, {"error": "production already exists"})
            eid = new_id("env")
            environments[eid] = {"environmentId": eid, "projectId": pid, "name": "production"}
            return self.response(200, {"environmentId": eid})
        if path == "/api/application.create":
            env_id = data.get("environmentId")
            if env_id not in environments or data.get("sourceType") != "git":
                return self.response(400, {"error": "application missing private environment"})
            app_id = new_id("app")
            applications[app_id] = {
                "applicationId": app_id,
                "environmentId": env_id,
                "applicationStatus": "idle",
            }
            return self.response(200, {"applicationId": app_id})
        if path == "/api/application.saveGitProvider":
            app = applications.get(data.get("applicationId"))
            if not app or not str(data.get("customGitUrl", "")).startswith("https://github.com/"):
                return self.response(400, {"error": "invalid Git repository"})
            if not data.get("customGitBranch") or not data.get("customGitBuildPath"):
                return self.response(400, {"error": "missing Git configuration"})
            app["gitConfigured"] = True
            app["gitBranch"] = data["customGitBranch"]
            app["gitBuildPath"] = data["customGitBuildPath"]
            return self.response(200, {"success": True})
        if path == "/api/application.saveBuildType":
            app = applications.get(data.get("applicationId"))
            if not app or data.get("buildType") not in ("nixpacks", "dockerfile"):
                return self.response(400, {"error": "unknown build type"})
            if data["buildType"] == "dockerfile" and data.get("dockerfile") != "Dockerfile":
                return self.response(400, {"error": "missing Dockerfile"})
            app["buildType"] = data["buildType"]
            return self.response(200, {"success": True})
        if path == "/api/application.saveEnvironment":
            app = applications.get(data.get("applicationId"))
            env = data.get("env")
            if not app or not isinstance(env, str) or "NODE_ENV=production" not in env:
                return self.response(400, {"error": "missing environment"})
            app["environmentConfigured"] = True
            return self.response(200, {"success": True})
        if path == "/api/domain.create":
            app = applications.get(data.get("applicationId"))
            domain = data.get("host")
            if not app or not isinstance(domain, str) or not domain.endswith(".test"):
                return self.response(400, {"error": "unverified domain"})
            app["domainConfigured"] = True
            return self.response(200, {"domainId": "domain-" + app["applicationId"]})
        if path == "/api/application.deploy":
            app = applications.get(data.get("applicationId"))
            if not app or not all(app.get(key) for key in [
                "gitConfigured", "buildType", "environmentConfigured", "domainConfigured"
            ]):
                return self.response(400, {"error": "incomplete setup"})
            app["applicationStatus"] = "running"
            return self.response(200, {"success": True})
        return self.response(405, {"error": "unsupported operation in mock"})


server = HTTPServer(("127.0.0.1", 443), Handler)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(os.environ["MOCK_CERT"], os.environ["MOCK_KEY"])
server.socket = context.wrap_socket(server.socket, server_side=True)
server.serve_forever()
