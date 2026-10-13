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
STORE_ID = '7080'  # Vaudreuil store ID on homedepot.ca
POSTAL_CODE = 'H9R1E9'

COOKIES = {
    'THD_LOCALSTORE': STORE_ID,
    'hd_store': STORE_ID,
    'hd_store_id': STORE_ID,
    'THD_STORE': STORE_ID,
    'THD_POSTAL_CODE': POSTAL_CODE,
    'postalCode': POSTAL_CODE,
}

HEADERS = {
    'Accept-Language': 'fr-CA,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Sec-Ch-Ua': '"Google Chrome";v="129", "Not=A?Brand";v="8", "Chromium";v="129"',
    'Sec-Ch-Ua-Mobile': '?0',
    'Sec-Ch-Ua-Platform': '"Windows"',
}

def get_live_store_price(sku):
    """
    Direct query to Home Depot's pricing endpoint for the localized Vaudreuil store.
    """
    url = f"https://www.homedepot.ca/api/v1/pricing/products/{sku}?storeId={STORE_ID}"
    try:
        r = requests.get(
            url,
            impersonate="chrome124",
            headers={
                **HEADERS,
                'Accept': 'application/json, text/plain, */*',
                'Referer': f'https://www.homedepot.ca/product/{sku}'
            },
            cookies=COOKIES,
            timeout=8
        )
        if r.status_code == 200:
            data = r.json()
            # Extracts the specific localized store price ($26.98)
            val = data.get('price') or data.get('unitPrice') or data.get('storePrice')
            if val:
                return float(val)
    except Exception:
        pass
    return None

def extract_price(html_text):
    clean = html_text.replace('\u00a0', ' ').replace('&nbsp;', ' ')

    # 1. Look for localized price in Next.js state for Vaudreuil
    m_store = re.search(rf'"{STORE_ID}"\s*:\s*\{{[^}}]*"(?:price|specialPrice|storePrice)"\s*:\s*([0-9]+\.[0-9]{{2}})', html_text)
    if m_store:
        try:
            return float(m_store.group(1)), None
        except ValueError:
            pass

    # 2. Look for unit price in DOM text: $26.98 / each or 26,98 $ / chaque
    dom_m = re.search(r'(?:\$([0-9]{1,4}\.[0-9]{2})|([0-9]{1,4}),([0-9]{2})\s*\$)\s*(?:\*{1,3})?\s*/\s*(?:each|chaque)', clean, re.IGNORECASE)
    if dom_m:
        try:
            val = dom_m.group(1) if dom_m.group(1) else f"{dom_m.group(2)}.{dom_m.group(3)}"
            return float(val), None
        except ValueError:
            pass

    # 3. Check JSON state (excluding originalPrice to avoid 27.97 fallback)
    price_matches = re.findall(r'"(?:specialPrice|storePrice|unitPrice|localizedPrice)":\s*"?([0-9]+\.[0-9]{2})"?', html_text)
    if price_matches:
        try:
            return float(price_matches[0]), None
        except ValueError:
            pass

    # 4. Standard price pattern
    fr_m = re.search(r'([0-9]{1,4}),([0-9]{2})\s*\$', clean)
    if fr_m:
        return float(f"{fr_m.group(1)}.{fr_m.group(2)}"), None

    en_m = re.search(r'\$([0-9]{1,4}\.[0-9]{2})', clean)
    if en_m:
        return float(en_m.group(1)), None

    return None, "Prix introuvable dans le DOM"

def sync_item(url):
    # Extract SKU from URL
    sku_match = re.search(r'(\d{10})', url)
    if sku_match:
        sku = sku_match.group(1)
        api_price = get_live_store_price(sku)
        if api_price:
            return api_price, None

    # Fallback to fetching HTML with store query attached
    target_url = url
    if 'store=' not in target_url:
        sep = '&' if '?' in target_url else '?'
        target_url = f"{target_url}{sep}store={STORE_ID}"

    try:
        r = requests.get(
            target_url,
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