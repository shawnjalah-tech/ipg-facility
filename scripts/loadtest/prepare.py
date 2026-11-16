#!/usr/bin/env python3
"""Create a private load-test configuration and SQLite file without using the app key."""
import base64
import json
import os
from pathlib import Path
import secrets
import sys

root = Path(__file__).resolve().parents[2]
config = root / '.env.loadtest'
database = root / 'database/loadtest.sqlite'

if config.is_symlink() or database.is_symlink():
    sys.exit('Refusing a linked load-test configuration or database.')
if database.exists() and (not database.is_file() or database.stat().st_nlink != 1):
    sys.exit('database/loadtest.sqlite must be an independent regular file.')

if not config.exists():
    key = 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode('ascii')
    env = f'''APP_NAME="IPG Facility Load Test"
APP_ENV=loadtest
APP_KEY={key}
APP_DEBUG=false
APP_URL=http://127.0.0.1:8001
APP_TIMEZONE=Asia/Kuching
DB_CONNECTION=sqlite
DB_DATABASE={json.dumps(str(database), ensure_ascii=False)}
SESSION_DRIVER=file
SESSION_COOKIE=ipg_loadtest_session
SESSION_SECURE_COOKIE=false
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=array
LOG_CHANNEL=daily
LOG_LEVEL=warning
'''
    with os.fdopen(os.open(config, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600), 'w', encoding='utf-8') as file:
        file.write(env)
    print('Created .env.loadtest with a separate application key.')
else:
    print('Kept existing .env.loadtest.')
if not database.exists():
    os.close(os.open(database, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600))
    print('Created database/loadtest.sqlite.')
