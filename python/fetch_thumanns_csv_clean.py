from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.edge.options import Options
import time
import os
import requests

# --- CONFIG ---
THUMANNS_LOGIN_URL = 'https://dist.thumanns.com/'
USERNAME = 'miketrotta@gmail.com'  # TODO: Replace with your username
PASSWORD = 'Bingo123$'  # TODO: Replace with your password
CSV_DOWNLOAD_URL = 'https://dist.thumanns.com/pricingdata/thumasteritems-current.csv'
OUTPUT_PATH = os.path.join(os.path.dirname(__file__), '../data/thumasteritems-current.csv')

# --- SELENIUM SETUP ---

edge_options = Options()
# edge_options.add_argument('--headless')  # Uncomment for headless mode
driver = webdriver.Edge(options=edge_options)

try:
    driver.get(THUMANNS_LOGIN_URL)
    time.sleep(2)
    driver.find_element(By.ID, 'username-104').send_keys(USERNAME)
    driver.find_element(By.ID, 'user_password-104').send_keys(PASSWORD)
    driver.find_element(By.ID, 'um-submit-btn').click()
    print('Login submitted. Attempting to close password popup...')
    time.sleep(3)
    # Try to close popup with OK or Close button
    popup_closed = False
    for label in ['OK', 'Close', 'Ok', 'close']:
        try:
            btns = driver.find_elements(By.XPATH, f"//button[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{label.lower()}')]")
            if btns:
                print(f"Found popup button: {btns[0].text}")
                btns[0].click()
                popup_closed = True
                print('Popup closed.')
                time.sleep(2)
                break
        except Exception as e:
            print(f'Error trying to close popup: {e}')
    if not popup_closed:
        print('No popup button found. If popup is present, please close it manually.')
        time.sleep(10)

    body_class = driver.find_element(By.TAG_NAME, 'body').get_attribute('class')
    if 'logged-in' not in body_class:
        print('Login failed. Exiting.')
        driver.quit()
        exit(1)
    print('Login successful.')

    # Get cookies for requests
    cookies = {c['name']: c['value'] for c in driver.get_cookies()}
finally:
    driver.quit()

# --- DOWNLOAD CSV WITH REQUESTS ---
print('Downloading CSV...')
s = requests.Session()
s.cookies.update(cookies)
resp = s.get(CSV_DOWNLOAD_URL)
if resp.status_code == 200 and resp.content.strip():
    os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
    with open(OUTPUT_PATH, 'wb') as f:
        f.write(resp.content)
    print(f'CSV downloaded to {OUTPUT_PATH}')
else:
    print('Failed to download CSV. Status:', resp.status_code)
