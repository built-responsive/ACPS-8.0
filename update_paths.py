import os
import re

replacements = [
    (r'href="css/', 'href="public/css/'),
    (r'src="js/', 'src="public/js/'),
    (r'src="images/', 'src="public/images/'),
    (r'href="shadowbox-3.0.3/', 'href="public/assets/shadowbox/'),
    (r'src="shadowbox-3.0.3/', 'src="public/assets/shadowbox/'),
    (r'src="jquery-1.11.1.min.js"', 'src="public/js/jquery-1.11.1.min.js"'),
    (r'src="jsKeyboard.js"', 'src="public/js/jsKeyboard.js"'),
    (r'href="styles.css"', 'href="public/css/styles.css"'),
    (r'href="apple-touch-icon.png"', 'href="public/icons/apple-touch-icon.png"'),
    (r'href="favicon-32x32.png"', 'href="public/icons/favicon-32x32.png"'),
    (r'href="favicon-16x16.png"', 'href="public/icons/favicon-16x16.png"'),
    (r'href="site.webmanifest"', 'href="public/icons/site.webmanifest"'),
    (r'href="favicon.ico"', 'href="public/icons/favicon.ico"'),
]

def update_file(file_path):
    with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    new_content = content
    for pattern, replacement in replacements:
        new_content = re.sub(pattern, replacement, new_content)
    
    if new_content != content:
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Updated: {file_path}")

for root, dirs, files in os.walk('.'):
    if any(x in root for x in ['vendor', 'archive', '.git', 'public']):
        continue
    for file in files:
        if file.endswith('.php') or file.endswith('.html'):
            update_file(os.path.join(root, file))
