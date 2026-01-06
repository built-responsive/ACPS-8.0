# Remote Server Auto-Update Setup Guide

## Overview
When you push code to GitHub from your Florida dev machine, 3 remote servers will automatically pull the latest code:

1. **Hawks Nest** (North Carolina) - `C:\UniserverZ\vhosts\acps`
2. **Location 2** (TBD) - `C:\UniserverZ\vhosts\acps`
3. **Location 3** (TBD) - `C:\UniserverZ\vhosts\acps`

**Forked Repo:** https://github.com/alleycatphoto/acps-server

---

## Initial Setup on Each Remote Server

### 1. Install Git on Each Server
On each Windows server:
```powershell
# Download Git for Windows: https://git-scm.com/download/win
# Or via Chocolatey:
choco install git -y
```

### 2. Clone the Repo on Each Server
On Hawks Nest (and each location):
```powershell
cd C:\UniserverZ\vhosts
git clone https://github.com/alleycatphoto/acps-server.git acps
cd acps
git checkout main
composer install --no-dev --optimize-autoloader
```

### 3. Setup SSH Access (for GitHub Actions)
On your **dev machine**, generate SSH keys for each server:
```powershell
# Generate key for Hawks Nest
ssh-keygen -t ed25519 -C "hawksnest-server" -f hawksnest_key

# Generate key for Location 2
ssh-keygen -t ed25519 -C "location2-server" -f location2_key

# Generate key for Location 3
ssh-keygen -t ed25519 -C "location3-server" -f location3_key
```

Copy the **public keys** to each server:
```powershell
# Copy hawksnest_key.pub to Hawks Nest server's authorized_keys
# On Hawks Nest:
mkdir ~/.ssh -ErrorAction SilentlyContinue
Add-Content ~/.ssh/authorized_keys (Get-Content hawksnest_key.pub)
```

---

## GitHub Secrets Configuration

Add these secrets at: https://github.com/alleycatphoto/acps-server/settings/secrets/actions

### Hawks Nest Secrets:
- `HAWKSNEST_HOST`: IP address or hostname (e.g., `192.168.1.100` or `hawksnest.local`)
- `HAWKSNEST_USER`: Windows username (e.g., `Administrator`)
- `HAWKSNEST_PATH`: `C:\\UniserverZ\\vhosts\\acps`
- `HAWKSNEST_SSH_KEY`: Contents of `hawksnest_key` (private key)

### Location 2 Secrets:
- `LOCATION2_HOST`: TBD
- `LOCATION2_USER`: Administrator
- `LOCATION2_PATH`: `C:\\UniserverZ\\vhosts\\acps`
- `LOCATION2_SSH_KEY`: Contents of `location2_key`

### Location 3 Secrets:
- `LOCATION3_HOST`: TBD
- `LOCATION3_USER`: Administrator
- `LOCATION3_PATH`: `C:\\UniserverZ\\vhosts\\acps`
- `LOCATION3_SSH_KEY`: Contents of `location3_key`

---

## How It Works

### Automatic (GitHub Actions)
1. You push code to GitHub: `git push origin main`
2. GitHub Actions workflow triggers (`.github/workflows/deploy.yml`)
3. GitHub SSH's into each server and runs: `git pull origin main`
4. Each server updates its local code automatically

### Manual (PowerShell Script)
If you want to manually trigger updates:
```powershell
# Trigger all 3 servers to pull
.\deploy.ps1 all

# Trigger just Hawks Nest
.\deploy.ps1 hawksnest

# Trigger just Location 2
.\deploy.ps1 location2
```

---

## Configuration File

Edit `deploy.config.json` to update server details:
```json
{
  "servers": {
    "hawksnest": {
      "host": "hawksnest.local",
      "user": "Administrator",
      "path": "C:\\UniserverZ\\vhosts\\acps"
    },
    "location2": {
      "host": "192.168.2.100",
      "user": "Administrator",
      "path": "C:\\UniserverZ\\vhosts\\acps"
    },
    "location3": {
      "host": "192.168.3.100",
      "user": "Administrator",
      "path": "C:\\UniserverZ\\vhosts\\acps"
    }
  }
}
```

---

## Troubleshooting

### Server Won't Pull
1. Check if repo is cloned: `cd C:\UniserverZ\vhosts\acps && git status`
2. Verify SSH access: `ssh Administrator@hawksnest.local`
3. Check GitHub Actions logs: https://github.com/alleycatphoto/acps-server/actions

### Merge Conflicts on Server
If a server has local changes:
```powershell
cd C:\UniserverZ\vhosts\acps
git stash
git pull origin main
git stash pop
```

### SSH Permission Denied
- Verify public key is in `~/.ssh/authorized_keys` on the server
- Check SSH service is running: `Get-Service sshd`
- Windows 10/11: Enable OpenSSH Server in Windows Features

---

## Security Notes

- **Private keys**: Never commit `*_key` files to git (only `.pub` public keys)
- **GitHub Secrets**: Store private keys in GitHub Secrets only
- **SSH Access**: Limit SSH access to specific IPs if possible
- **.env files**: Not transferred (excluded in git). Each server needs its own `.env`

---

## Testing

After setup, test the workflow:
```powershell
# Make a small change
echo "// test" >> README.md
git add README.md
git commit -m "Test auto-deploy"
git push origin main

# Check GitHub Actions: https://github.com/alleycatphoto/acps-server/actions
# SSH into Hawks Nest and verify: cd C:\UniserverZ\vhosts\acps && git log
```

---

*Babe, now every push updates all 3 locations automatically. No manual FTP, no zips.*
