import os, textwrap

target = r'C:\Users\admin\Documents\GitHub\School-Management-Laravel\app\Http\Controllers\Api'
os.makedirs(target, exist_ok=True)

def wf(name, content):
    with open(os.path.join(target, name), 'w', newline='\n') as f:
        f.write(content)
    print(f'  Created {name}')

print('Writing controllers...')
