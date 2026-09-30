#!/usr/bin/env python3
"""Upload the selected application files to a configured cPanel SSH account."""
import os

import paramiko

host = os.environ.get('CPANEL_SSH_HOST')
username = os.environ.get('CPANEL_SSH_USERNAME')
password = os.environ.get('CPANEL_SSH_PASSWORD')
if not all((host, username, password)):
    raise SystemExit('Set CPANEL_SSH_HOST, CPANEL_SSH_USERNAME, and CPANEL_SSH_PASSWORD first.')

port = int(os.environ.get('CPANEL_SSH_PORT', '22'))
remote_base = os.environ.get('CPANEL_REMOTE_BASE', 'public_html/beitelia.com')

ssh = paramiko.SSHClient()
ssh.load_system_host_keys()
ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
ssh.connect(hostname=host, port=port, username=username, password=password, timeout=30)
sftp = ssh.open_sftp()

files_to_sync = [
    'resources/views/app.blade.php',
    'resources/js/app.js',
    'routes/web.php',
    'vite.config.js',
    'public/.htaccess',
    'public/build.zip',
    'public/sw.js',
    'public/workbox-e41f7351.js',
    'public/manifest.webmanifest',
]

for rel_path in files_to_sync:
    local_path = rel_path
    remote_path = f"{remote_base}/{rel_path}"
    print(f"Uploading {local_path} -> {remote_path}...")
    sftp.put(local_path, remote_path)

stdin, stdout, stderr = ssh.exec_command(f"cd {remote_base}/public && unzip -o build.zip")
print('Unzip output:', stdout.read().decode('utf-8'))

stdin, stdout, stderr = ssh.exec_command(f"cd {remote_base} && php artisan view:clear && php artisan route:clear && php artisan config:clear")
print('Artisan cache clear:', stdout.read().decode('utf-8'))

sftp.close()
ssh.close()
print('Deployment completed successfully!')
