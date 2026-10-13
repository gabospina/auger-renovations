"""
admin/scraper.py
Home Depot Canada Automated Price Sync (Store #7033 - Vaudreuil)
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
STORE_ID = '7033'

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
    # 1. Search for Vaudreuil (7033) specific pricing in the Next.js / React state
    # e.g., "7033":{"price":26.98} or "store_7033":{"price":26.98}
    store_matches = re.findall(rf'"{STORE_ID}"\s*:\s*\{{[^}}]*"(?:price|specialPrice|storePrice)"\s*:\s*([0-9]+\.[0-9]{{2}})', html_text)
    if store_matches:
        try:
            return float(store_matches[0]), None
        except ValueError:
            pass

    # 2. Check Next.js __NEXT_DATA__ JSON script payload directly
    next_data = re.search(r'<script id="__NEXT_DATA__" type="application/json">([^<]+)</script>', html_text)
    if next_data:
        try:
            payload = json.loads(next_data.group(1))
            props = payload.get('props', {}).get('pageProps', {})
            product = props.get('productData') or props.get('initialData') or props
            
            # Store-specific pricing map
            store_pricing = product.get('storePricing', {}).get(STORE_ID, {})
            if 'price' in store_pricing:
                return float(store_pricing['price']), None
            if 'specialPrice' in store_pricing:
                return float(store_pricing['specialPrice']), None
        except Exception:
            pass

    # 3. Clean spaces and check DOM formatted patterns
    clean = html_text.replace('\u00a0', ' ').replace('&nbsp;', ' ')
    
    # Rendered unit price pattern: $26.98 / each or 26,98 $ / chaque
    dom_m = re.search(r'(?:\$([0-9]{1,4}\.[0-9]{2})|([0-9]{1,4}),([0-9]{2})\s*\$)\s*(?:\*{1,3})?\s*/\s*(?:each|chaque)', clean, re.IGNORECASE)
    if dom_m:
        try:
            val = dom_m.group(1) if dom_m.group(1) else f"{dom_m.group(2)}.{dom_m.group(3)}"
            return float(val), None
        except ValueError:
            pass

    # 4. French standard format fallback
    fr_m = re.search(r'([0-9]{1,4}),([0-9]{2})\s*\$', clean)
    if fr_m:
        try:
            return float(f"{fr_m.group(1)}.{fr_m.group(2)}"), None
        except ValueError:
            pass

    # 5. Generic price fallback (only if store-specific price wasn't found)
    price_matches = re.findall(r'"(?:specialPrice|storePrice|price)":\s*"?([0-9]+\.[0-9]{2})"?', html_text)
    if price_matches:
        try:
            return float(price_matches[0]), None
        except ValueError:
            pass

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
    print(f"\nSynchronisation Home Depot (Magasin #{STORE_ID} - Vaudreuil) pour {len(keys)} article(s)...\n")

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