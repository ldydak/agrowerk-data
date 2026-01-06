import time
import pandas as pd
from bs4 import BeautifulSoup

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import (
    TimeoutException,
    StaleElementReferenceException,
    ElementClickInterceptedException,
    WebDriverException
)

# =========================
# KONFIGURACJA
# =========================
INPUT_CSV = "input_aktualizacja_danych_2026_products.csv"      # <- tu wpisz nazwę pliku wejściowego
OUTPUT_CSV = "output_aktualizacja_danych_2026_products.csv"

URL_COL = "oryginal_url"

# Jak brzmią etykiety w tabeli informacji o produkcie?
# Dostosuj jeśli trzeba.
SKU_LABEL = "Artikel-Nr:"
EAN_LABEL = "EAN:"

# Timeouty
PAGE_LOAD_TIMEOUT = 30
WAIT_TIMEOUT = 20


# --- helper do czytania EAN/SKU z tabeli informacji o produkcie ---
def extract_label_value(soup, label_text):
    try:
        label = soup.find(
            'th',
            class_='product-information-label',
            string=lambda text: text and label_text in text
        )
        if label:
            content = label.find_next_sibling('td', class_='product-information-content')
            return content.text.strip() if content else ''
    except:
        pass
    return ''


def build_driver() -> webdriver.Chrome:
    chrome_options = Options()
    # Jeśli chcesz bez okna, odkomentuj:
    # chrome_options.add_argument("--headless=new")

    chrome_options.add_argument("--start-maximized")
    chrome_options.add_argument("--disable-gpu")
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")

    driver = webdriver.Chrome(options=chrome_options)
    driver.set_page_load_timeout(PAGE_LOAD_TIMEOUT)
    return driver


def wait_for_main_content(driver: webdriver.Chrome):
    # Czekamy aż strona ma podstawowy content (price zwykle jest dobrym sygnałem)
    WebDriverWait(driver, WAIT_TIMEOUT).until(
        EC.presence_of_element_located((By.CSS_SELECTOR, "body"))
    )


def parse_product_data(driver: webdriver.Chrome) -> dict:
    """
    Zwraca dict: new_sku, ean, oryginal_price, oryginal_url (aktualny URL z przeglądarki).
    """
    html = driver.page_source
    soup = BeautifulSoup(html, "html.parser")

    # SKU/EAN z tabeli informacji o produkcie
    sku = extract_label_value(soup, SKU_LABEL)
    ean = extract_label_value(soup, EAN_LABEL)

    # --- fragment obowiązkowy z Twojego kodu (cena) ---
    price_element = soup.select_one("span.mabp-current-price-value")

    if price_element:
        price = price_element.get_text()
        cleaned_price = price.replace("€", "").replace(".", "").replace(",", ".").strip()
        print(f"✅ Cena: {cleaned_price}")
    else:
        print(f"⚠️ Brak elementu ceny")
        cleaned_price = "brak ceny"
    # --- koniec fragmentu ---

    current_url = driver.current_url

    return {
        "sku": 'HGS' + sku,
        "ean": ean,
        "oryginal_price": cleaned_price,
        "oryginal_url": current_url
    }


def has_variants(driver: webdriver.Chrome) -> bool:
    soup = BeautifulSoup(driver.page_source, "html.parser")
    container = soup.select_one(".product-variant-content")
    if not container:
        return False
    opts = container.select("div.product-detail-configurator-option")
    return len(opts) > 0


def click_variant_by_index(driver: webdriver.Chrome, idx: int):
    """
    Klika wariant o indeksie idx (0..n-1).
    Po kliknięciu strona się przeładowuje.
    """
    # Pobieramy elementy na świeżo (po każdym reload mogą być "stare")
    options = driver.find_elements(By.CSS_SELECTOR, ".product-variant-content div.product-detail-configurator-option")
    if idx >= len(options):
        raise IndexError(f"Variant index {idx} out of range (len={len(options)})")

    opt = options[idx]

    # Klikamy input jeśli jest, bo bywa stabilniejsze niż div
    try:
        input_el = opt.find_element(By.CSS_SELECTOR, "input")
    except Exception:
        input_el = None

    target = input_el if input_el else opt

    # Scroll do elementu
    driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", target)
    time.sleep(0.2)

    old_url = driver.current_url
    try:
        try:
            target.click()
        except ElementClickInterceptedException:
            driver.execute_script("arguments[0].click();", target)

        # Czekamy aż URL się zmieni (często tak jest) albo aż odświeży się DOM
        # Jeśli URL się nie zmienia na tej stronie, i tak wymusimy wait na obecność body + małe opóźnienie.
        try:
            WebDriverWait(driver, WAIT_TIMEOUT).until(lambda d: d.current_url != old_url)
        except TimeoutException:
            pass

        wait_for_main_content(driver)
        time.sleep(0.3)

    except StaleElementReferenceException:
        # Gdy DOM się przestawi podczas kliknięcia — spróbuj raz jeszcze re-fetch
        time.sleep(0.5)
        options = driver.find_elements(By.CSS_SELECTOR, ".product-variant-content div.product-detail-configurator-option")
        opt = options[idx]
        try:
            input_el = opt.find_element(By.CSS_SELECTOR, "input")
        except Exception:
            input_el = None
        target = input_el if input_el else opt
        driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", target)
        driver.execute_script("arguments[0].click();", target)
        wait_for_main_content(driver)
        time.sleep(0.3)


def process_url(driver: webdriver.Chrome, url: str) -> list[dict]:
    """
    Zwraca listę rekordów (dict) — 1 rekord jeśli brak wariantów,
    lub wiele rekordów jeśli są warianty.
    """
    print(f"\n➡️ Otwieram: {url}")
    try:
        driver.get(url)
        wait_for_main_content(driver)
        time.sleep(0.5)
    except (TimeoutException, WebDriverException) as e:
        print(f"❌ Nie udało się otworzyć URL: {url} | {e}")
        return []

    results = []

    if not has_variants(driver):
        print("ℹ️ Brak wariantów — pobieram dane z głównego produktu")
        results.append(parse_product_data(driver))
        return results

    print("✅ Wykryto warianty — klikam każdy po kolei")

    # Liczymy warianty z Selenium (nie z BS4), bo będziemy klikać
    variant_count = len(driver.find_elements(By.CSS_SELECTOR, ".product-variant-content div.product-detail-configurator-option"))
    print(f"🔢 Liczba wariantów: {variant_count}")

    for i in range(variant_count):
        try:
            print(f"🖱️ Klik wariantu {i+1}/{variant_count}")
            click_variant_by_index(driver, i)
            results.append(parse_product_data(driver))
        except Exception as e:
            print(f"❌ Błąd przy wariancie {i+1}: {e}")

    return results


def main():
    df = pd.read_csv(INPUT_CSV)

    if URL_COL not in df.columns:
        raise ValueError(f"Brak kolumny '{URL_COL}' w pliku {INPUT_CSV}")

    driver = build_driver()

    output_rows = []
    try:
        for index, row in df.iterrows():
            url = str(row.get(URL_COL, "")).strip()
            if not url or url.lower() == "nan":
                print(f"⚠️ Wiersz {index}: pusty URL — pomijam")
                continue

            records = process_url(driver, url)

            # Każdy rekord to osobny wariant (lub produkt bez wariantów)
            for rec in records:
                # Jeśli chcesz zachować też oryginalny URL z wejścia w osobnej kolumnie:
                rec["input_url"] = url
                rec["input_row_index"] = index
                output_rows.append(rec)

    finally:
        driver.quit()

    out_df = pd.DataFrame(output_rows, columns=["input_row_index", "input_url", "sku", "ean", "oryginal_price", "oryginal_url"])
    out_df.to_csv(OUTPUT_CSV, index=False)
    print(f"\n✅ Zapisano: {OUTPUT_CSV} | wierszy: {len(out_df)}")


if __name__ == "__main__":
    main()
