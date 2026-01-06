# ACPS v3.5.0 Multi-Location Deployment Script
# Usage: .\deploy.ps1 [local|staging|production|all]

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet("local","staging","production","all")]
    [string]$Target = "all"
)

$ErrorActionPreference = "Stop"

# Load config
$config = Get-Content "deploy.config.json" | ConvertFrom-Json

function Write-Status {
    param($Message, $Color = "Cyan")
    Write-Host "[DEPLOY] $Message" -ForegroundColor $Color
}

function Deploy-Local {
    Write-Status "Deploying to LOCAL (localhost)..." "Yellow"
    
    $source = Get-Location
    $dest = $config.environments.local.path
    
    # Copy files excluding patterns
    $exclude = $config.exclude
    
    Write-Status "Copying files to $dest..."
    robocopy $source $dest /MIR /XD .git .github node_modules vendor logs /XF .env deploy.config.json deploy.ps1 *.md /NP /NDL /NFL /NJS
    
    Write-Status "✅ Local deployment complete: $($config.environments.local.url)" "Green"
}

function Deploy-Staging {
    Write-Status "Deploying to STAGING (v2.acps.dev)..." "Yellow"
    
    $hostname = $config.environments.staging.host
    $user = $config.environments.staging.user
    $path = $config.environments.staging.path
    
    # Check if rsync available (Git Bash or WSL)
    if (Get-Command rsync -ErrorAction SilentlyContinue) {
        Write-Status "Using rsync for staging deploy..."
        
        $excludeArgs = $config.exclude | ForEach-Object { "--exclude='$_'" }
        $rsyncCmd = "rsync -avz --delete $($excludeArgs -join ' ') ./ ${user}@${hostname}:${path}"
        
        Invoke-Expression $rsyncCmd
        
        # Run post-deploy commands
        Write-Status "Running post-deploy commands..."
        ssh "${user}@${hostname}" "cd $path && composer install --no-dev --optimize-autoloader && rm -f usps_token_cache.txt"
        
    } else {
        Write-Status "rsync not found. Install Git Bash or WSL for remote deploy." "Red"
        exit 1
    }
    
    Write-Status "✅ Staging deployment complete: $($config.environments.staging.url)" "Green"
}

function Deploy-Production {
    Write-Status "Deploying to PRODUCTION (acps.alleycatphoto.net)..." "Red"
    
    $hostname = $config.environments.production.host
    $user = $config.environments.production.user
    $path = $config.environments.production.path
    
    # Confirmation for production
    $confirm = Read-Host "⚠️  Deploy to PRODUCTION? This affects live kiosks! (yes/no)"
    if ($confirm -ne "yes") {
        Write-Status "Production deployment cancelled." "Yellow"
        return
    }
    
    # Backup first
    if ($config.environments.production.backup) {
        Write-Status "Creating production backup..."
        $backupDate = Get-Date -Format "yyyyMMdd_HHmmss"
        ssh "${user}@${hostname}" "cp -r $path ${path}_backup_${backupDate}"
    }
    
    # Deploy
    if (Get-Command rsync -ErrorAction SilentlyContinue) {
        Write-Status "Deploying to production..."
        
        $excludeArgs = $config.exclude | ForEach-Object { "--exclude='$_'" }
        $rsyncCmd = "rsync -avz --delete $($excludeArgs -join ' ') ./ ${user}@${hostname}:${path}"
        
        Invoke-Expression $rsyncCmd
        
        # Post-deploy
        Write-Status "Running post-deploy commands..."
        ssh "${user}@${hostname}" "cd $path && composer install --no-dev --optimize-autoloader && rm -f usps_token_cache.txt"
        
    } else {
        Write-Status "rsync not found. Install Git Bash or WSL for remote deploy." "Red"
        exit 1
    }
    
    Write-Status "✅ Production deployment complete: $($config.environments.production.url)" "Green"
}

# Main deployment logic
Write-Status "🚀 ACPS v3.5.0 Deployment Script" "Magenta"
Write-Status "Target: $Target" "Cyan"
Write-Status ""

switch ($Target) {
    "local" { Deploy-Local }
    "staging" { Deploy-Staging }
    "production" { Deploy-Production }
    "all" {
        Deploy-Local
        Write-Status ""
        Deploy-Staging
        Write-Status ""
        Deploy-Production
    }
}

Write-Status ""
Write-Status "🎉 Deployment complete!" "Green"
