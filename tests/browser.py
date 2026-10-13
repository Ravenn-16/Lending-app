"""Headless Chrome smoke test of the isolated fixture server."""
from pathlib import Path
import re
import subprocess
import tempfile

root = Path(__file__).resolve().parent.parent
profile = tempfile.mkdtemp(prefix='chrome-test-', dir=root / '.tmp')
chrome = Path(r'C:\Program Files\Google\Chrome\Application\chrome.exe')
result = subprocess.run([str(chrome), '--headless', '--disable-gpu', '--no-first-run',
    '--disable-background-networking', '--dump-dom', '--virtual-time-budget=20000',
    '--timeout=30000', '--user-data-dir=' + profile,
    'http://127.0.0.1:8765/__browser_check'], capture_output=True, text=True, encoding='utf-8', timeout=50)
match = re.search(r'<pre id="result">(.*?)</pre>', result.stdout, re.S)
message = match.group(1) if match else 'FAIL: browser produced no test result. ' + result.stderr[-1500:]
print(message)
if not message.startswith('PASS:'):
    raise SystemExit(1)
