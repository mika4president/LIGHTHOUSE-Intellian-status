import socket
import re
import sys
import json
import time

# --- CONFIGURATIE ---
DEVICES = [
    {"name": "Dish_1", "ip": "10.15.2.180", "port": 4002},
    {"name": "Dish_2", "ip": "10.15.2.181", "port": 4002}
]

# Gebruik een absoluut pad voor cron! 
# Pas '/home/es/status.json' aan naar waar je IPTV server het nodig heeft.
JSON_FILE = "/var/log/lighthouse.json"
GLOBAL_TIMEOUT = 25  # Na 25 sec stopt het script sowieso (veilig binnen de minuut)

STATUS_CODES = {
    "0": "IDLE / UNLOCK",
    "1": "SEARCHING",
    "2": "TRACKING (LOCKED)",
    "3": "WRAPPING (CABLE)",
    "4": "ERROR / INITIALIZING"
}

def get_status(ip, port):
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(10) # 10 seconden per schotel
        s.connect((ip, port))
        
        data = s.recv(1024)
        if sys.version_info[0] >= 3:
            data = data.decode('utf-8', errors='ignore')
        
        s.close()
        
        matches = re.findall(r'\{Ni\s+(\d+)', data)
        if matches:
            code = matches[-1]
            return STATUS_CODES.get(code, "UNKNOWN (" + code + ")")
        return "NO DATA"
        
    except Exception as e:
        return "OFFLINE"

def main():
    current_status = {
        "last_update": time.strftime("%Y-%m-%d %H:%M:%S"),
        "devices": {}
    }
    
    for dev in DEVICES:
        status = get_status(dev["ip"], dev["port"])
        current_status["devices"][dev["name"]] = {
            "ip": dev["ip"],
            "status": status
        }
    
    # Schrijf naar JSON
    with open(JSON_FILE, 'w') as f:
        json.dump(current_status, f, indent=4)

if __name__ == "__main__":
    # Harde limiet: als het script na 30 seconden nog draait, stop het dan.
    # Dit voorkomt dat cron-jobs zich opstapelen als het netwerk traag is.
    import signal
    signal.signal(signal.SIGALRM, lambda sig, frame: sys.exit(0))
    signal.alarm(GLOBAL_TIMEOUT)
    
    main()
