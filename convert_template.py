import os, sys

D = bytes([36])
BS = bytes([92])

def php(template):
    result = template.encode('utf-8')
    result = result.replace(b'@', D)
    result = result.replace(b'~~', BS)
    return result

target = os.path.join('C:' + os.sep, 'Users', 'admin', 'Documents', 'GitHub', 'School-Management-Laravel', 'app', 'Http', 'Controllers', 'Api')
os.makedirs(target, exist_ok=True)

def wf(name, content_bytes):
    path = os.path.join(target, name)
    with open(path, 'wb') as f:
        f.write(content_bytes)
    print(f'  Created {name} ({len(content_bytes)} bytes)')

# Read template from file specified as argument
template_file = sys.argv[1]
output_name = sys.argv[2]

with open(template_file, 'r', encoding='utf-8') as f:
    template = f.read()

wf(output_name, php(template))
