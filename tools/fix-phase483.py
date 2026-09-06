from pathlib import Path

p = Path('tests/phase483-suspended-placeholder.php')
t = p.read_text()
old = '''c483(str_contains($vhost,"style-src 'self'")&&!str_contains($vhost,"'unsafe-inline'"),'placeholder-CSP staat uitsluitend eigen stylesheet toe zonder unsafe-inline');'''
new = '''c483(str_contains($vhost,"style-src \\'self\\'")&&!str_contains($vhost,"unsafe-inline"),'placeholder-CSP staat uitsluitend eigen stylesheet toe zonder unsafe-inline');'''
if old not in t:
    raise SystemExit('phase483 CSP assertion not found')
p.write_text(t.replace(old, new, 1))
