import os, sys, base64, json
target = r"C:\Users\admin\Documents\GitHub\School-Management-Laravel\app\Http\Controllers\Api"
os.makedirs(target, exist_ok=True)
data = json.loads(sys.stdin.read())
for name, b64 in data.items():
    content = base64.b64decode(b64)
    filepath = os.path.join(target, name)
    with open(filepath, "wb") as f:
        f.write(content)
    print(f"  Created {name} ({len(content)} bytes)")
print("All controllers written!")
