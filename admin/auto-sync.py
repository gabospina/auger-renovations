# admin/auto-sync.py
import json
import os
import sys
import subprocess
from datetime import datetime

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
SETTINGS_FILE = os.path.join(CURRENT_DIR, 'data', 'settings.json')
SCRAPER_SCRIPT = os.path.join(CURRENT_DIR, 'scraper.py')
LOG_FILE = os.path.join(CURRENT_DIR, 'data', 'scraper.log')

def log(msg):
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    entry = f"[{timestamp}] {msg}\n"
    print(entry, end='')
    with open(LOG_FILE, 'a', encoding='utf-8') as f:
        f.write(entry)

def run():
    if not os.path.exists(SETTINGS_FILE):
        log("Paramètres introuvables. Fin.")
        return

    with open(SETTINGS_FILE, 'r', encoding='utf-8') as f:
        settings = json.load(f)

    if not settings.get('auto_sync_enabled', False):
        log("Synchronisation automatique désactivée par le gestionnaire. Fin.")
        return

    today_name = datetime.now().strftime('%A').lower()  # 'monday', 'sunday', etc.
    target_day = settings.get('sync_day', 'monday').lower()

    if today_name != target_day:
        log(f"Aujourd'hui ({today_name}) ne correspond pas au jour planifié ({target_day}). Fin.")
        return

    log(f"Lancement de la synchronisation automatique ({today_name})...")
    python_exe = sys.executable

    res = subprocess.run([python_exe, SCRAPER_SCRIPT], capture_output=True, text=True)
    if res.returncode == 0:
        log("Succès de la synchronisation.")
        settings['last_auto_sync'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        with open(SETTINGS_FILE, 'w', encoding='utf-8') as f:
            json.dump(settings, f, indent=2, ensure_ascii=False)
    else:
        log(f"Erreur du scraper: {res.stderr}")

if __name__ == '__main__':
    run()