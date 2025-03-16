import time
import logging
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service  # For Selenium 4
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException
from webdriver_manager.chrome import ChromeDriverManager

# Set up logging to console
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s: %(message)s"
)

def setup_driver():
    options = webdriver.ChromeOptions()
    # Remove headless so you can see the browser window
    # options.add_argument("--headless")
    options.add_argument("--disable-gpu")
    options.add_argument("--no-sandbox")
    options.add_argument("--enable-unsafe-swiftshader")
    options.add_argument("--ignore-certificate-errors")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--log-level=3")
    options.add_experimental_option("excludeSwitches", ["enable-logging"])
    
    # Use your original user-agent (Chrome/133)
    options.add_argument(
        "user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36"
    )
    
    service = Service()  # Uses system's default ChromeDriver
    driver = webdriver.Chrome(service=service, options=options)
    return driver

def login_test():
    driver = setup_driver()
    try:
        driver.get("https://www.comicspriceguide.com/")
        time.sleep(2)

        # Click the login button to open the popup
        login_button = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.XPATH, "//span[contains(text(),'Login')]"))
        )
        login_button.click()
        logging.info("Clicked Login button")
        time.sleep(2)

        # Wait for the username field in the popup
        username_field = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.XPATH, "//div[@id='dvUser']//input"))
        )
        username_field.clear()
        username_field.send_keys("2xd")
        logging.info("Entered Username")

        # Wait for the password field in the popup
        password_field = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.XPATH, "//div[@id='dvPassword']//input"))
        )
        password_field.clear()
        password_field.send_keys("19731973")
        logging.info("Entered Password")

        # Click the login button inside the popup
        submit_button = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.XPATH, "//div[@id='btnLgn']"))
        )
        submit_button.click()
        logging.info("Clicked Login Submit button")
        time.sleep(5)  # Allow time for the page to process

        # Confirm login was successful by checking that the login button disappears.
        try:
            WebDriverWait(driver, 20).until(
                EC.invisibility_of_element_located((By.ID, "btnLgn"))
            )
            logging.info("Login successful!")
            print("Login Successful!")
        except TimeoutException:
            logging.error("Login failed. Login popup did not disappear.")
            print("Login failed due to timeout.")
    except TimeoutException as te:
        logging.error("Login failed due to timeout: " + str(te))
        print("Login failed due to timeout.")
    except Exception as e:
        logging.error("Login failed: " + str(e))
        print("Login failed.")
    finally:
        time.sleep(5)  # Keep browser open briefly for observation
        driver.quit()

if __name__ == "__main__":
    print("Test script started...")
    login_test()
    print("Test script completed.")
