import os, sys

gen_path = os.path.join(r"c:\Usersdmin\Documents\GitHub\School-Management-PhpVersion", "generate_all.py")

def append_controller(name, template):
    with open(gen_path, "ab") as f:
        f.write(b'wf("' + name.encode() + b'\" , r\"\"\"' )
