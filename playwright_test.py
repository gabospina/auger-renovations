from playwright.sync_api import sync_playwright

with sync_playwright() as p:

    browser = p.chromium.launch(
        executable_path=r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
        headless=False
    )

    page = browser.new_page()

    page.goto(
        "https://www.homedepot.ca/produit/southwire-14-2-nmd90-150m-romex-simpull-fil-electrique-blanc/1000788662",
        wait_until="domcontentloaded"
    )

    print("Page loaded")
    print("Title:", page.title())

    input("Press ENTER to close browser...")

    browser.close()