import os, sys, glob

D = chr(36)
BS = chr(92)

def php(template):
    result = template.replace('@', D).replace('~~', BS)
    return result

target = os.path.join(
    'C:' + os.sep,
    'Users', 'admin', 'Documents', 'GitHub',
    'School-Management-Laravel', 'app', 'Http', 'Controllers', 'Api'
)
os.makedirs(target, exist_ok=True)

tpl_dir = os.path.join(r'c:' + chr(92) + 'Users' + chr(92) + 'admin' + chr(92) + 'Documents' + chr(92) + 'GitHub' + chr(92) + 'School-Management-PhpVersion', 'templates')

for tpl_file in sorted(glob.glob(os.path.join(tpl_dir, '*.tpl'))):
    name = os.path.basename(tpl_file).replace('.tpl', '.php')
    with open(tpl_file, 'r', encoding='utf-8') as f:
        template = f.read()
    content = php(template)
    out_path = os.path.join(target, name)
    with open(out_path, 'w', newline=chr(10)) as f:
        f.write(content)
    print(f'  Created {name} ({len(content)} bytes)')

print('All controllers generated!')
