import socket
import re
import sys
import json
import time
import os

# --- CONFIGURATIE ---
# Scheepsnaam: herkenbaar op het dashboard van alle schepen/schotels
SHIP_NAME = "AmaStella"

DEVICES = [
    {"name": "Dish_1", "ip": "10.15.2.180", "port": 4002},
    {"name": "Dish_2", "ip": "10.15.2.181", "port": 4002}
]

# Lokaal JSON-bestand. Gebruik een pad waar gebruiker 'es' mag schrijven (geen /var/log zonder root).
# Voor cron: absoluut pad, bijv. /home/es/lighthouse.json
JSON_FILE = "/home/es/lighthouse.json"
GLOBAL_TIMEOUT = 25  # Na 25 sec stopt het script sowieso (veilig binnen de minuut)

# Online backend: URL waar de status naartoe wordt gepost (lege string = uit).
# De backend ontvangt een POST met JSON: {"ship": "...", "last_update": "...", "devices": {...}}
BACKEND_URL = "https://www.escs-portal.nl/lighthouse/post-status.php"  # bijv. "https://jouw-backend.example.com/lighthouse/post-status.php"

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


def push_to_backend(url, data):
    """POST de status naar de online backend (timeout 5 sec)."""
    try:
        if sys.version_info[0] >= 3:
            from urllib.request import Request, urlopen
            from urllib.error import HTTPError
            payload = json.dumps(data).encode('utf-8')
            req = Request(url, data=payload, method='POST')
            req.add_header('Content-Type', 'application/json')
            urlopen(req, timeout=5)
        else:
            from urllib2 import Request, urlopen, HTTPError
            payload = json.dumps(data)
            req = Request(url, data=payload)
            req.add_header('Content-Type', 'application/json')
            urlopen(req)
    except HTTPError as e:
        body = e.read() if hasattr(e, 'read') else b''
        msg = ''
        try:
            text = body.decode('utf-8', errors='ignore') if body else ''
            if text:
                err = json.loads(text)
                if isinstance(err, dict) and 'error' in err:
                    msg = err['error']
                    if err.get('path'):
                        msg += ' | pad: ' + err['path']
                    if err.get('hint'):
                        msg += ' | ' + err['hint']
                else:
                    msg = text[:300]
        except Exception:
            msg = (body[:200].decode('utf-8', errors='ignore') if body else '') or str(e)
        sys.stderr.write("Backend POST mislukt: {} {}\n".format(e.code, msg))
    except Exception as e:
        sys.stderr.write("Backend POST mislukt: {}\n".format(e))


def main():
    current_status = {
        "ship": SHIP_NAME,
        "last_update": time.strftime("%Y-%m-%d %H:%M:%S"),
        "devices": {}
    }

    for dev in DEVICES:
        status = get_status(dev["ip"], dev["port"])
        current_status["devices"][dev["name"]] = {
            "ip": dev["ip"],
            "status": status
        }

    # Schrijf naar lokaal JSON (fallback naar home bij permission denied)
    json_path = JSON_FILE
    try:
        with open(json_path, 'w') as f:
            json.dump(current_status, f, indent=4)
    except IOError as e:
        if e.errno == 13:  # Permission denied
            fallback = os.path.expanduser("~/lighthouse.json")
            try:
                with open(fallback, 'w') as f:
                    json.dump(current_status, f, indent=4)
                sys.stderr.write("Lokaal bestand weggeschreven naar {} (geen rechten op {})\n".format(fallback, json_path))
            except Exception:
                sys.stderr.write("Kon niet schrijven naar {} noch {}: {}\n".format(json_path, fallback, e))
        else:
            raise

    # Naar online backend indien geconfigureerd
    if BACKEND_URL.strip():
        push_to_backend(BACKEND_URL.strip(), current_status)

if __name__ == "__main__":
    # Harde limiet: als het script na 30 seconden nog draait, stop het dan.
    # Dit voorkomt dat cron-jobs zich opstapelen als het netwerk traag is.
    import signal
    signal.signal(signal.SIGALRM, lambda sig, frame: sys.exit(0))
    signal.alarm(GLOBAL_TIMEOUT)
    
    main()
