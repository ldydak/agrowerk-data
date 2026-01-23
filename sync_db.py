#!/usr/bin/env python3
import os
import subprocess
import shutil
from dotenv import dotenv_values
import sys
import tempfile

# README MacOs
# python3 -m venv venv
# source venv/bin/activate
# pip install python-dotenv
# python sync_db.py download-from-prod
# python sync_db.py send-to-prod

#README Windows
# W Visual Studio Code używaj Command Promopt a nie Power Shell!!!
# python3 -m venv venv (można bez wirtualnego środowiska)
# pip install python-dotenv
# (tylko raz za 1 razem w systemie Windows, Command Prompt, nie Power Shell): setx PATH "%PATH%;C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin"
# python sync_db.py download-from-prod
# python sync_db.py send-to-prod

TEMP_DIR = './temp'
DUMP_FILE = os.path.join(TEMP_DIR, 'dump.sql')

def load_env(file_path):
    return dotenv_values(file_path)

def run_command(command, hide_output=False):
    result = subprocess.run(
        command,
        shell=True,
        capture_output=True,
        text=True
    )
    if result.returncode != 0:
        print("Komenda:", command)
        print("Błąd:", result.stderr)
        print("Stdout:", result.stdout)
        sys.exit(1)

def replace_links_in_file(filepath, old, new):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    content = content.replace(old, new)
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

def create_mysql_config(env, config_file_path):
    """Tworzy tymczasowy plik konfiguracyjny MySQL"""
    config_content = f"""[client]
host={env['DB_DATA_DATA_HOST']}
user={env['DB_DATA_USERNAME']}
password={env['DB_DATA_PASSWORD']}
port={env.get('DB_PORT', '3306')}
"""
    with open(config_file_path, 'w') as f:
        f.write(config_content)
    
    # Ustaw uprawnienia tylko dla właściciela (Unix) lub ukryj plik (Windows)
    if os.name != 'nt':  # Nie Windows
        os.chmod(config_file_path, 0o600)

def dump_database(env, output_file):
    print(f"📤 Dumping {env['DB_DATA_DATABASE']} ...")
    
    # Tworzenie tymczasowego pliku konfiguracyjnego
    config_file = os.path.join(TEMP_DIR, 'mysql_config.cnf')
    create_mysql_config(env, config_file)
    
    try:
        command = (
            f"mysqldump --defaults-file=\"{config_file}\" "
            f"--single-transaction --quick --lock-tables=false "
            f"\"{env['DB_DATA_DATABASE']}\" > \"{output_file}\""
        )
        run_command(command)
    finally:
        # Bezpieczne usunięcie pliku konfiguracyjnego
        try:
            os.remove(config_file)
        except:
            pass

def restore_database(env, input_file):
    print(f"♻️  Podmienianie bazy danych: {env['DB_DATA_DATABASE']} ...")
    
    # Tworzenie tymczasowego pliku konfiguracyjnego
    config_file = os.path.join(TEMP_DIR, 'mysql_config.cnf')
    create_mysql_config(env, config_file)
    
    # Tworzenie pliku z komendami SQL (najbezpieczniejsze rozwiązanie)
    sql_commands_file = os.path.join(TEMP_DIR, 'create_db.sql')
    with open(sql_commands_file, 'w', encoding='utf-8') as f:
        f.write(f"DROP DATABASE IF EXISTS `{env['DB_DATA_DATABASE']}`;\n")
        f.write(f"CREATE DATABASE `{env['DB_DATA_DATABASE']}`;\n")
    
    try:
        # Wykonaj komendy SQL z pliku (bez problemów z escapeowaniem)
        drop_command = (
            f"mysql --defaults-file=\"{config_file}\" "
            f"< \"{sql_commands_file}\""
        )
        run_command(drop_command)

        # Import dump
        import_command = (
            f"mysql --defaults-file=\"{config_file}\" "
            f"\"{env['DB_DATA_DATABASE']}\" < \"{input_file}\""
        )
        run_command(import_command)
    finally:
        # Bezpieczne usunięcie plików tymczasowych
        try:
            os.remove(config_file)
            os.remove(sql_commands_file)
        except:
            pass

def download_from_prod():
    prod_env = load_env('.env.prod')
    local_env = load_env('.env')

    # Sprawdź czy wymagane zmienne środowiskowe istnieją
    required_vars = ['DB_DATA_HOST', 'DB_DATA_USERNAME', 'DB_DATA_PASSWORD', 'DB_DATA_DATABASE']
    for var in required_vars:
        if var not in prod_env or not prod_env[var]:
            print(f"❌ Brak wymaganej zmiennej {var} w .env.prod")
            sys.exit(1)
        if var not in local_env or not local_env[var]:
            print(f"❌ Brak wymaganej zmiennej {var} w .env")
            sys.exit(1)

    os.makedirs(TEMP_DIR, exist_ok=True)

    dump_database(prod_env, DUMP_FILE)

    print("🔁 Zamiana linków z produkcji na lokalne...")
    replace_links_in_file(DUMP_FILE, 'https://data.sanipro.pl', 'http://sanipro-data.xyz')

    restore_database(local_env, DUMP_FILE)

    print("🧹 Czyszczenie tymczasowych plików...")
    try:
        os.remove(DUMP_FILE)
    except:
        pass

    print("✅ Baza została pobrana z produkcji i załadowana lokalnie.")

def send_to_prod():
    prod_env = load_env('.env.prod')
    local_env = load_env('.env')

    # Sprawdź czy wymagane zmienne środowiskowe istnieją
    required_vars = ['DB_DATA_HOST', 'DB_DATA_USERNAME', 'DB_DATA_PASSWORD', 'DB_DATA_DATABASE']
    for var in required_vars:
        if var not in prod_env or not prod_env[var]:
            print(f"❌ Brak wymaganej zmiennej {var} w .env.prod")
            sys.exit(1)
        if var not in local_env or not local_env[var]:
            print(f"❌ Brak wymaganej zmiennej {var} w .env")
            sys.exit(1)

    os.makedirs(TEMP_DIR, exist_ok=True)

    dump_database(local_env, DUMP_FILE)

    print("🔁 Zamiana linków z lokalnych na produkcyjne...")
    replace_links_in_file(DUMP_FILE, 'http://sanipro-data.xyz', 'https://data.sanipro.pl')

    restore_database(prod_env, DUMP_FILE)

    print("🧹 Czyszczenie tymczasowych plików...")
    try:
        os.remove(DUMP_FILE)
    except:
        pass

    print("✅ Baza została wysłana na produkcję.")

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print("Użycie: python sync_db.py [download-from-prod|send-to-prod]")
        sys.exit(1)

    command = sys.argv[1]

    if command == 'download-from-prod':
        download_from_prod()
    elif command == 'send-to-prod':
        send_to_prod()
    else:
        print("Nieznane polecenie:", command)
        sys.exit(1)