import os, base64

target = os.path.join(
    "C:" + os.sep,
    "Users", "admin", "Documents", "GitHub",
    "School-Management-Laravel", "app", "Http", "Controllers", "Api"
)
os.makedirs(target, exist_ok=True)

def wf(name, b64):
    filepath = os.path.join(target, name)
    content = base64.b64decode(b64)
    with open(filepath, "wb") as fh:
        fh.write(content)
    print(f"  Created {name} ({len(content)} bytes)")

print("Generating all 12 Laravel API controllers...")

# Controller data will be appended as base64 strings
