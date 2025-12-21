# Uzupelniamy new_oryginal_prices na podstawie oryginal_url
# Jesli brak ceny lub URL jest bledny -> wpisujemy 'brak ceny'
# Zapisujemy wynik "w locie" do NEW_products_and_variants_prices.csv

import pandas as pd
import time
from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

# Pliki
input_csv_path = "old_products_and_variants_prices.csv"
output_csv_path = "NEW_products_and_variants_prices.csv"

# Wczytanie danych
df = pd.read_csv(input_csv_path, sep=None, engine='python')

# Dodaj kolumnę jeśli nie istnieje
if 'new_oryginal_prices' not in df.columns:
    df['new_oryginal_prices'] = pd.NA

# Przygotowanie Chrome + Selenium
chrome_options = Options()
chrome_options.add_argument("--headless")
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--disable-dev-shm-usage")

service = Service()
driver = webdriver.Chrome(service=service, options=chrome_options)

# Iterujemy tylko po wierszach bez nowej ceny
for index, row in df[df['new_oryginal_price'].isna()].iterrows():
    url = row.get('oryginal_url')

    if not isinstance(url, str) or not url.startswith("http"):
        print(f"❌ Brak poprawnego URL w wierszu {index}")
        df.at[index, 'new_oryginal_price'] = 'brak ceny'
        df.to_csv(output_csv_path, index=False, encoding='utf-8-sig')
        continue

    print(f"🔗 Otwieram: {url}")
    try:
        driver.get(url)

        # Czekamy na element ceny
        WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "span.mabp-current-price-value"))
        )
        time.sleep(1.5)

        soup = BeautifulSoup(driver.page_source, "html.parser")
        price_element = soup.select_one("span.mabp-current-price-value")

        if price_element:
            price = price_element.get_text()
            cleaned_price = price.replace("€", "").replace(".", "").replace(",", ".").strip()

            df.at[index, 'new_oryginal_price'] = cleaned_price
            print(f"✅ Wiersz {index}: cena {cleaned_price}")
        else:
            print(f"⚠️ Brak elementu ceny w wierszu {index}")
            df.at[index, 'new_oryginal_price'] = 'brak ceny'

    except Exception as e:
        print(f"❌ Błąd przy {url}: {e}")
        df.at[index, 'new_oryginal_price'] = 'brak ceny'

    # zapis w locie po kazdym wierszu
    df.to_csv(output_csv_path, index=False, encoding='utf-8-sig')

print("\n💾 Zaktualizowany plik zapisany:", output_csv_path)
driver.quit()
