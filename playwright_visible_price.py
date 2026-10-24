from playwright.sync_api import sync_playwright

URL = "https://www.homedepot.ca/produit/southwire-14-2-nmd90-150m-romex-simpull-fil-electrique-blanc/1000788662"

with sync_playwright() as p:

    browser = p.chromium.launch(
        executable_path=r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
        headless=False
    )

    page = browser.new_page()

    page.goto(URL, wait_until="domcontentloaded")

    print("Page opened.")
    print("Please manually accept cookies/store prompts.")

    input("Press ENTER when you can see the final Home Depot price...")

    text = page.locator("body").inner_text()

    with open("visible_text.txt", "w", encoding="utf-8") as f:
        f.write(text)

    print("Saved visible_text.txt")

    browser.close()