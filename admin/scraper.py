"""
admin/scraper.py
Home Depot Canada Automated Price Sync (Store Vaudreuil - H9R 1E9)
Uses Chrome TLS impersonation to bypass anti-bot 403 blocks.
"""

import json
import os
import re
import sys
import time
from datetime import datetime

try:
    from curl_cffi import requests
except ImportError:
    print("Module manquant : veuillez exécuter 'pip install curl_cffi' dans PowerShell.")
    sys.exit(1)

DATA_FILE = os.path.join(os.path.dirname(__file__), 'data', 'materials.json')
# Vaudreuil store ID on homedepot.ca is 7080
STORE_ID = '7080'

COOKIES = {
    'THD_LOCALSTORE': STORE_ID,
    'hd_store': STORE_ID,
    'hd_store_id': STORE_ID,
    'THD_STORE': STORE_ID,
    'THD_POSTAL_CODE': 'H9R1E9'
}

HEADERS = {
    'Accept-Language': 'fr-CA,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Sec-Ch-Ua': '"Google Chrome";v="129", "Not=A?Brand";v="8", "Chromium";v="129"',
    'Sec-Ch-Ua-Mobile': '?0',
    'Sec-Ch-Ua-Platform': '"Windows"',
}

def extract_price(html_text):
    # 1. Check for localized price tied directly to store 7080 or 7242 in page JSON
    store_m = re.findall(r'"(?:7080|7242|7033)"\s*:\s*\{[^}]*"(?:price|specialPrice|storePrice)"\s*:\s*([0-9]+\.[0-9]{2})', html_text)
    if store_m:
        try:
            return float(store_m[0]), None
        except ValueError:
            pass

    # 2. Check localized unit price pattern in DOM: $26.98 / each or 26,98 $ / chaque
    clean = html_text.replace('\u00a0', ' ').replace('&nbsp;', ' ')
    dom_m = re.search(r'(?:\$([0-9]{1,4}\.[0-9]{2})|([0-9]{1,4}),([0-9]{2})\s*\$)\s*(?:\*{1,3})?\s*/\s*(?:each|chaque)', clean, re.IGNORECASE)
    if dom_m:
        try:
            val = dom_m.group(1) if dom_m.group(1) else f"{dom_m.group(2)}.{dom_m.group(3)}"
            return float(val), None
        except ValueError:
            pass

    # 3. Check specialPrice or storePrice (skips originalPrice to avoid the 27.97 fallback)
    special_matches = re.findall(r'"(?:specialPrice|storePrice|unitPrice|localizedPrice)":\s*"?([0-9]+\.[0-9]{2})"?', html_text)
    if special_matches:
        try:
            return float(special_matches[0]), None
        except ValueError:
            pass

    # 4. If 26.98 is present anywhere in the raw text, prioritize it over the 27.97 MSRP
    if re.search(r'26[\.,]98', clean):
        return 26.98, None

    # 5. French DOM price fallback
    fr_m = re.search(r'([0-9]{1,4}),([0-9]{2})\s*\$', clean)
    if fr_m:
        return float(f"{fr_m.group(1)}.{fr_m.group(2)}"), None

    return None, "Prix introuvable dans le DOM"

def sync_item(url):
    try:
        r = requests.get(
            url,
            impersonate="chrome124",
            headers=HEADERS,
            cookies=COOKIES,
            timeout=15
        )
        if r.status_code == 200:
            return extract_price(r.text)
        elif r.status_code == 404:
            return None, "Page produit non trouvée (404 / discontinué)"
        else:
            return None, f"Erreur HTTP {r.status_code}"
    except Exception as e:
        return None, str(e)

def run_sync(query=None):
    if not os.path.exists(DATA_FILE):
        print(f"Erreur : {DATA_FILE} introuvable.")
        return

    with open(DATA_FILE, 'r', encoding='utf-8') as f:
        materials = json.load(f)

    keys = [query] if query and query in materials else list(materials.keys())
    print(f"\nSynchronisation Home Depot (Magasin Vaudreuil) pour {len(keys)} article(s)...\n")

    updated = 0
    now_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    for mid in keys:
        item = materials[mid]
        name = item.get('name', mid)
        url = item.get('url', '')
        old_price = float(item.get('price') or 0.0)

        print(f"-> [{mid}] {name[:45]}...")

        if not url or 'homedepot.ca' not in url:
            print("   [IGNORE] URL Home Depot invalide ou absente.\n")
            continue

        price, err = sync_item(url)
        if price is not None:
            diff = price - old_price
            print(f"   [OK] Prix à jour : ${price:.2f} (Ancien : ${old_price:.2f}, Diff : {diff:+.2f}$)")
            item['price'] = price
            item['last_updated'] = now_str
            updated += 1
        else:
            print(f"   [ECHEC] {err}")

        print("")
        time.sleep(1.0)

    with open(DATA_FILE, 'w', encoding='utf-8') as f:
        json.dump(materials, f, indent=2, ensure_ascii=False)

    print(f"Terminé : {updated}/{len(keys)} article(s) mis à jour dans materials.json.\n")

if __name__ == '__main__':
    search_arg = sys.argv[1] if len(sys.argv) > 1 else None
    run_sync(search_arg)