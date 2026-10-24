"""
admin/scraper.py
Home Depot Canada Automated Price Sync (Store Vaudreuil - #7187)
Queries Home Depot's pricing API directly to ensure store-accurate pricing.
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

# Vaudreuil store ID on homedepot.ca is 7187
STORE_ID = '7187'

HEADERS = {
    'Accept': 'application/json, text/plain, */*',
    'Accept-Language': 'fr-CA,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Sec-Ch-Ua': '"Google Chrome";v="129", "Not=A?Brand";v="8", "Chromium";v="129"',
    'Sec-Ch-Ua-Mobile': '?0',
    'Sec-Ch-Ua-Platform': '"Windows"',
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'
}

def extract_sku_from_url(url):
    # Extracts the 9 or 10-digit SKU from homedepot.ca URLs (e.g. /1000788662)
    m = re.search(r'/([0-9]{8,10})(?:\?|$|/)', url)
    if m:
        return m.group(1)
    
    # Fallback search for any 8-10 digit sequence in URL
    m2 = re.search(r'([0-9]{8,10})', url)
    if m2:
        return m2.group(1)
        
    return None

def fetch_store_price(sku):
    api_url = f"https://www.homedepot.ca/api/productsvc/v1/products/{sku}/store/{STORE_ID}?fields=BASIC_SPA&lang=fr"
    
    try:
        r = requests.get(
            api_url,
            impersonate="chrome124",
            headers=HEADERS,
            timeout=15
        )
        
        if r.status_code == 200:
            data = r.json()
            # Extract price value from optimizedPrice -> displayPrice -> value
            price_val = data.get('optimizedPrice', {}).get('displayPrice', {}).get('value')
            if price_val is not None:
                return float(price_val), None
            return None, "Clé 'value' introuvable dans la réponse API"
        elif r.status_code == 404:
            return None, "Produit introuvable dans l'API (404)"
        else:
            return None, f"Erreur API HTTP {r.status_code}"
            
    except Exception as e:
        return None, str(e)

def sync_item(url):
    sku = extract_sku_from_url(url)
    if not sku:
        return None, "SKU introuvable dans l'URL"
        
    return fetch_store_price(sku)

def run_sync(query=None):
    if not os.path.exists(DATA_FILE):
        print(f"Erreur : {DATA_FILE} introuvable.")
        return

    with open(DATA_FILE, 'r', encoding='utf-8') as f:
        materials = json.load(f)

    if query:
        keys = [k for k in materials.keys() if query in k or query in str(materials[k].get('id', ''))]
    else:
        keys = list(materials.keys())

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
            print(f"   [OK] Prix à jour (Store #{STORE_ID}) : ${price:.2f} (Ancien : ${old_price:.2f}, Diff : {diff:+.2f}$)")
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