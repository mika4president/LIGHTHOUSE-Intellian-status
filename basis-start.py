import socket
import re
import sys

# Configuratie
ACU_IP = "10.22.2.180"
ACU_PORT = 4002

# Status mapping
STATUS_CODES = {
    "0": "IDLE / UNLOCK",
    "1": "SEARCHING",
    "2": "TRACKING (LOCKED)",
    "3": "WRAPPING (CABLE)",
    "4": "ERROR / INITIALIZING"
}

def monitor_intellian():
    print("Verbinden met Intellian i6 op {0}:{1}...".format(ACU_IP, ACU_PORT))
    
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(10)
        s.connect((ACU_IP, ACU_PORT))
        print("Verbonden! Wachten op data... (Druk op Ctrl+C om te stoppen)\n")
        
        buffer = ""
        last_status = None

        while True:
            chunk = s.recv(1024)
            if not chunk:
                break
            
            # Decode voor Python 3, laat ongemoeid voor Python 2
            if sys.version_info[0] >= 3:
                chunk = chunk.decode('utf-8', errors='ignore')
            
            buffer += chunk
            
            # Regex die zoekt naar {Ni [getal]
            matches = re.findall(r'\{Ni\s+(\d+)', buffer)
            
            if matches:
                current_code = matches[-1]
                
                if current_code != last_status:
                    status_text = STATUS_CODES.get(current_code, "ONBEKEND (" + current_code + ")")
                    print("[STATUS] " + status_text)
                    last_status = current_code
                
                # Voorkom dat de buffer oneindig groeit
                if len(buffer) > 4096:
                    buffer = buffer[-500:]

    except KeyboardInterrupt:
        print("\nGestopt door gebruiker.")
    except Exception as e:
        print("\nFout: {0}".format(str(e)))
    finally:
        s.close()

if __name__ == "__main__":
    monitor_intellian()
