import os

target = os.path.join(
    "C:" + os.sep,
    "Users", "admin", "Documents", "GitHub",
    "School-Management-Laravel", "app", "Http", "Controllers", "Api"
)
os.makedirs(target, exist_ok=True)

def wf(name, content):
    filepath = os.path.join(target, name)
    with open(filepath, "w", newline="
") as f:
        f.write(content)
    print(f"  Created {name} ({len(content)} bytes)")


# Helper constants
NS = 'App\\Http\\Controllers\\Api'
EXC = '\\Exception'
MNF = '\\Illuminate\\Database\\Eloquent\\ModelNotFoundException'
VE = 'Illuminate\\Validation\\ValidationException'

print(f"NS={NS}, EXC={EXC}")
