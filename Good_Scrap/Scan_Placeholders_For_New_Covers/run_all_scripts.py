import subprocess
import logging

# Setup logging for the main script.
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

def run_script(script_name):
    """Runs a script via subprocess and returns its exit code."""
    logging.info(f"Running {script_name}...")
    try:
        # Run the script and capture output (optional)
        result = subprocess.run(["python", script_name], capture_output=True, text=True)
        if result.returncode != 0:
            logging.error(f"{script_name} failed with error: {result.stderr.strip()}")
        else:
            logging.info(f"{script_name} completed successfully.")
            logging.info(result.stdout.strip())
        return result.returncode
    except Exception as e:
        logging.error(f"Error running {script_name}: {e}")
        return 1

def main():
    # Run Script A: Looks for new covers.
    if run_script("scriptA(looks_for_new_covers).py") != 0:
        logging.error("Script A failed. Aborting the process.")
        return

    # Run Script B: Get and clean data.
    if run_script("scriptB(Get_Cleans_Data).py") != 0:
        logging.error("Script B failed. Aborting the process.")
        return

    # Run Script C: Clean and send data to Step_2 folder.
    if run_script("scriptC(Cleans_Sends_To_Step2).py") != 0:
        logging.error("Script C failed. Aborting the process.")
        return

    logging.info("All scripts ran successfully.")

if __name__ == "__main__":
    main()
