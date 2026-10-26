#!/usr/bin/env python3
"""Static Lagom Smarty UX/UI pre-delivery checks (no external dependencies).

The native WHMCS template contains Smarty conditionals; this verifies its
physical HTML nesting plus all essential names, accessible hooks and JS
syntax. Runtime end-to-end browser tests on actual WHMCS/Lagom are separate.
"""
from __future__ import annotations

import re
import subprocess
import sys
import tempfile
from html.parser import HTMLParser
from pathlib import Path

TEMPLATE = Path(__file__).resolve().parents[1] / "templates" / "clientarea.tpl"
CODE = TEMPLATE.read_text(encoding="utf-8")

class Structure(HTMLParser):
    VOID = {
        "area", "base", "br", "col", "embed", "hr", "img", "input", "link",
        "meta", "param", "source", "track", "wbr",
    }
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack: list[str] = []
        self.problems: list[str] = []
    def handle_starttag(self, tag: str, attrs):
        if tag not in self.VOID:
            self.stack.append(tag)
    def handle_endtag(self, tag: str):
        if tag in self.VOID:
            self.problems.append(f"Unexpected void closing tag: {tag}")
            return
        if not self.stack or self.stack[-1] != tag:
            self.problems.append(
                f"Bad nesting: closing {tag}, expected {self.stack[-1] if self.stack else 'none'}"
            )
            return
        self.stack.pop()

parser = Structure()
parser.feed(CODE)
assert not parser.problems, "\n".join(parser.problems)
assert not parser.stack, "Unclosed HTML: " + ", ".join(parser.stack)
assert len(re.findall(r"\{if\b", CODE)) == CODE.count("{/if}"), "Unbalanced Smarty if"
assert len(re.findall(r"\{foreach\b", CODE)) == CODE.count("{/foreach}"), "Unbalanced foreach"
assert CODE.count("{literal}") == CODE.count("{/literal}") == 2, "CSS/JS Smarty escapes"

# Semantic UX, accessible journey, keyboard and mobile controls.
required = [
    'id="ecd-status"',
    'id="ecd-instalar"',
    'id="correo-corporativo"',
    'aria-label="Secciones del servicio"',
    'aria-label="Etapas de instalación"',
    '$ecDokployPhase >= 1',
    '$ecDokployPhase >= 2',
    '$ecDokployPhase >= 3',
    'id="ecd-repository"',
    'aria-describedby="ecd-repo-help ecd-repo-error"',
    'data-ecd-form-error',
    'data-ecd-repo-error',
    'data-ecd-advanced',
    'data-ecd-submit',
    'data-ecd-submit-notice',
    'role="alert"',
    'prefers-reduced-motion:reduce',
    ':focus-visible',
    '@media(max-width:720px)',
    'min-height:46px',
    'min-height:48px',
    'ec_setup_csrf', # ensure no loose wildcard token
]
# real form token is ec_setup_token (not ec_setup_csrf).
required[-1] = 'name="ec_setup_token"'
for token in required:
    assert token in CODE, f"Missing UX, form or security hook: {token}"

assert 'name="ec_setup_git"' in CODE, "GitHub input absent"
assert 'name="ec_setup_confirm"' in CODE, "Customer's explicit confirmation absent"
assert 'name="ec_setup_env"' in CODE, "Advanced encrypted environment fields absent"
assert 'name="a" value="InstallApp"' in CODE, "Correct module action absent"
assert 'name="token" value="{$token|escape:' in CODE, "WHMCS CSRF missing"
assert 'type="submit"' in CODE, "Native submit button missing"
assert "aria-invalid" in CODE and "repoError.textContent" in CODE, "Inline Git error missing"
assert "submitted = true" in CODE and "sendButton.disabled = true" in CODE, "Submit feedback missing"
assert "repo.value.trim()" in CODE, "Trimmed source URL missing"
assert "La disponibilidad pública" in CODE or "no una comprobación" in CODE.lower(), \
    "Requested deploy incorrectly marked as published"
assert "overflow:hidden!important" not in CODE, "Avoid clipping keyboard focus"

# Inspect actual JavaScript syntax using Node, available on ubuntu-latest.
js_match = re.search(r"<script>\s*\{literal\}([\s\S]*?)\{/literal\}\s*</script>", CODE)
assert js_match, "No client enhancement script found"
with tempfile.TemporaryDirectory(prefix="ecd-ux-") as tmp:
    script = Path(tmp) / "clientarea.js"
    script.write_text(js_match.group(1), encoding="utf-8")
    outcome = subprocess.run(
        ["node", "--check", str(script)], capture_output=True, text=True
    )
    assert outcome.returncode == 0, "JavaScript syntax check failed: " + outcome.stderr

print("WHMCS / Lagom UI-UX Pro Max template contracts passed")
