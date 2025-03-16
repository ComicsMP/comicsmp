import subprocess
import sys
import time
from datetime import datetime

XAMPP_PATH = r"C:\xampp6\xampp_start.exe"     # Update if needed
XAMPP_STOP_PATH = r"C:\xampp6\xampp_stop.exe" # Update if needed

def is_mysql_running():
    """
    Check if MySQL (mysqld.exe) is running by looking for 'mysqld.exe' in the task list.
    Returns True if found, otherwise False.
    """
    try:
        result = subprocess.run(["tasklist"], capture_output=True, text=True)
        return "mysqld.exe" in result.stdout
    except Exception as e:
        print(f"⚠️ Error checking MySQL status: {e}")
        return False

def start_mysql():
    """
    Start MySQL via XAMPP if it's not running.
    Return True if successfully running (or already was), False otherwise.
    """
    print("🔄 Checking MySQL status...")

    if is_mysql_running():
        print("✅ MySQL is already running.")
        return True

    print("⚠️ MySQL is not running. Attempting to start MySQL service...")

    try:
        # Launch XAMPP start (which includes MySQL)
        subprocess.Popen([XAMPP_PATH], shell=True)
        time.sleep(10)  # Give MySQL time to start

        if is_mysql_running():
            print("✅ MySQL started successfully.")
            return True
        else:
            print("❌ Failed to start MySQL. Please start it manually in XAMPP.")
            return False
    except Exception as e:
        print(f"❌ Error starting MySQL: {e}")
        return False

def stop_mysql():
    """
    Stop MySQL properly using SQL shutdown instead of xampp_stop.exe.
    """
    print("🔄 Stopping MySQL safely...")

    try:
        # Send a proper shutdown command to MySQL
        subprocess.run(["mysqladmin", "-u", "root", "shutdown"], check=True)
        time.sleep(5)  # Allow MySQL to shut down properly

        if not is_mysql_running():
            print("✅ MySQL stopped successfully.")
        else:
            print("⚠️ MySQL is still running. Try stopping it manually.")
    except Exception as e:
        print(f"❌ Error stopping MySQL: {e}")

def run_script(script_name, step_name, working_dir):
    """
    Run a Python script (script_name) from within working_dir.
    Print detailed messages before and after execution.
    If the script fails (non-zero exit code), the main script exits.
    """
    start_time = datetime.now()
    print(f"\n=== Starting {step_name} at {start_time.strftime('%Y-%m-%d %H:%M:%S')} ===")
    print(f"Running script: {script_name}\n{'-'*50}")

    try:
        subprocess.run(["python", script_name], check=True, cwd=working_dir)
    except subprocess.CalledProcessError as e:
        print(f"\n*** Error: {step_name} failed with return code {e.returncode}. Exiting. ***")
        sys.exit(e.returncode)

    end_time = datetime.now()
    duration = (end_time - start_time).total_seconds()
    print(f"\n=== {step_name} completed successfully at {end_time.strftime('%Y-%m-%d %H:%M:%S')} "
          f"(Duration: {duration:.2f} seconds) ===\n")
    print("=" * 70)

def main():
    # Step 1: Web Crawling (run step1.py inside Good_Scrap/Step_1)
    run_script("step1.py", "Step 1: Web Crawling", "Good_Scrap/Step_1")

    # Step 2: Downloading Photos and Organizing Data for Step 3
    run_script("step2.py", "Step 2: Downloading Photos and Organizing Data for Step 3", "Good_Scrap/Step_2")

    # Step 3: Uploading Data to the Database (Ensure MySQL is Running)
    if start_mysql():
        run_script("step3.py", "Step 3: Uploading Data to the Database", "Good_Scrap/Step_3")
    else:
        print("❌ MySQL is not running. Skipping Step 3.")
        sys.exit(1)

    # Step 4: Checking for Updated UPC Codes (DB Check Only)
    run_script("step4.py", "Step 4: Checking for Updated UPC Codes", "Good_Scrap/Step_4")

    # Step 5: Organizing Volume Numbers (DB Check Only)
    run_script("step5.py", "Step 5: Organizing Volume Numbers", "Good_Scrap/Step_5")

    # Step 6: Indexing New Covers for Mobile Scanning
    run_script("step6.py", "Step 6: Indexing New Covers for Mobile Scanning", "Faiss_Mobile_Matching")

    print("\n✅ All steps completed successfully!")

    # Stop MySQL after all steps are done
    stop_mysql()

if __name__ == "__main__":
    main()
