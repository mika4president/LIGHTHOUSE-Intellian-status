# -*- coding: utf-8 -*-
import socket
import re
import sys
import json
import time
import signal
import os

# --- CONFIGURATIE ---
# Scheepsnaam: herkenbaar op het dashboard van alle schepen/schotels
SHIP_NAME = "MV Example"

DEVICES = [
    {"name": "Dish_1", "ip": "10.15.2.180", "port": 4002},
    {"name": "Dish_2", "ip": "10.15.2.181", "port": 4002}
]

# Paden (GPS: /tmp/position met regels lat, lon, of Position = lat,lon)
GPS_FILE_PATH = "/tmp/position"
JSON_OUTPUT_PATH = "/home/es/lighthouse.json"
GLOBAL_TIMEOUT = 25

# Online backend: URL waar de status naartoe wordt gepost (lege string = uit).
BACKEND_URL = "https://www.escs-portal.nl/lighthouse/post-status.php" 

STATUS_CODES = {
    "0": "IDLE / UNLOCK",
    "1": "SEARCHING",
    "2": "TRACKING (LOCKED)",
    "3": "WRAPPING (CABLE)",
    "4": "ERROR / INITIALIZING"
}

def get_external_gps():
    """Leest de coördinaten uit het lokale tekstbestand.
    Ondersteunde formaten:
    - Regels met alleen lat en lon (bijv. 51.85333 en 6.098565), eventueel na $GPRMC.
    - Position = lat,lon (legacy)
    """
    if not os.path.exists(GPS_FILE_PATH):
        return "N/A (File Not Found)"
    try:
        with open(GPS_FILE_PATH, 'r') as f:
            content = f.read()
        # Legacy: "Position = lat,lon"
        match = re.search(r'Position\s*=\s*([0-9\.\,\-]+)', content)
        if match:
            return match.group(1).strip()
        # Nieuw formaat: regels met alleen een getal (lat, dan lon)
        floats = []
        for line in content.splitlines():
            line = line.strip()
            if not line:
                continue
            try:
                v = float(line)
                if -90 <= v <= 90 or -180 <= v <= 180:
                    floats.append(v)
            except ValueError:
                continue
        if len(floats) >= 2:
            lat, lon = floats[0], floats[1]
            if -90 <= lat <= 90 and -180 <= lon <= 180:
                return "{},{}".format(lat, lon)
    except Exception:
        pass
    return "N/A"

def get_dish_status(ip, port):
    """Haalt de schotelstatus op via de socket."""
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(8)
        s.connect((ip, port))
        data = s.recv(1024)
        if sys.version_info[0] >= 3:
            data = data.decode('utf-8', errors='ignore')
        s.close()
        
        ni_match = re.findall(r'\{Ni\s+(\d+)', data)
        if ni_match:
            return STATUS_CODES.get(ni_match[-1], "UNKNOWN")
    except Exception:
        pass
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
            r = urlopen(req, timeout=5)
        else:
            from urllib2 import Request, urlopen, HTTPError
            payload = json.dumps(data)
            req = Request(url, data=payload)
            req.add_header('Content-Type', 'application/json')
            r = urlopen(req)
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
    current_gps = get_external_gps()

    current_status = {
        "ship": SHIP_NAME,
        "last_update": time.strftime("%Y-%m-%d %H:%M:%S"),
        "ship_position": current_gps,
        "devices": {}
    }

    for dev in DEVICES:
        status = get_dish_status(dev["ip"], dev["port"])
        current_status["devices"][dev["name"]] = {
            "ip": dev["ip"],
            "status": status
        }

    # Schrijf naar lokaal JSON
    with open(JSON_OUTPUT_PATH, 'w') as f:
        json.dump(current_status, f, indent=4)

    # Naar online backend indien geconfigureerd
    if BACKEND_URL.strip():
        push_to_backend(BACKEND_URL.strip(), current_status)

if __name__ == "__main__":
    # Alarm instellen voor harde timeout
    if hasattr(signal, 'SIGALRM'):
        signal.signal(signal.SIGALRM, lambda sig, frame: sys.exit(0))
        signal.alarm(GLOBAL_TIMEOUT)
    
    main()