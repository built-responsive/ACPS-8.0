# ACPS v3.5.0 Remote Server Git Pull Trigger
# Usage: .\deploy.ps1 [hawksnest|location2|location3|all]
# This script SSH's into remote servers and tells them to git pull

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet("hawksnest","location2","location3","all")]
    [string]$Target = "all"
)

$ErrorActionPreference = "Stop"

# Load config
$config = Get-Content "deploy.config.json" | ConvertFrom-Json

function Write-Status {
    param($Message, $Color = "Cyan")
    Write-Host "[DEPLOY] $Message" -ForegroundColor $Color
}

function Trigger-HawksNest {
    Write-Status "Triggering git pull on Hawks Nest (North Carolina)..." "Yellow"
    
    $server = $config.servers.hawksnest
    $host = $server.host
    $user = $server.user
    $path = $server.path
    
    Write-Status "Connecting to ${user}@${host}..."
    
    # SSH and run git pull
    ssh "${user}@${host}" "cd $path; git pull origin main; composer install --no-dev --optimize-autoloader"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Status "✅ Hawks Nest pulled latest code successfully" "Green"
    } else {
        Write-Status "❌ Hawks Nest git pull failed" "Red"
    }
}

function Trigger-Location2 {
    Write-Status "Triggering git pull on Location 2..." "Yellow"
    
    $server = $config.servers.location2
    
    if ($server.host -eq "TBD") {
        Write-Status "⚠️  Location 2 not configured yet. Update deploy.config.json" "Yellow"
        return
    }
    
    $host = $server.host
    $user = $server.user
    $path = $server.path
    
    Write-Status "Connecting to ${user}@${host}..."
    ssh "${user}@${host}" "cd $path; git pull origin main; composer install --no-dev --optimize-autoloader"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Status "✅ Location 2 pulled latest code successfully" "Green"
    } else {
        Write-Status "❌ Location 2 git pull failed" "Red"
    }
}

function Trigger-Location3 {
    Write-Status "Triggering git pull on Location 3..." "Yellow"
    
    $server = $config.servers.location3
    
    if ($server.host -eq "TBD") {
        Write-Status "⚠️  Location 3 not configured yet. Update deploy.config.json" "Yellow"
        return
    }
    
    $host = $server.host
    $user = $server.user
    $path = $server.path
    
    Write-Status "Connecting to ${user}@${host}..."
    ssh "${user}@${host}" "cd $path; git pull origin main; composer install --no-dev --optimize-autoloader"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Status "✅ Location 3 pulled latest code successfully" "Green"
    } else {
        Write-Status "❌ Location 3 git pull failed" "Red"
    }
}

# Main deployment logic
Write-Status "🚀 ACPS v3.5.0 Remote Git Pull Trigger" "Magenta"
Write-Status "Target: $Target" "Cyan"
Write-Status ""

switch ($Target) {
    "hawksnest" { Trigger-HawksNest }
    "location2" { Trigger-Location2 }
    "location3" { Trigger-Location3 }
    "all" {
        Trigger-HawksNest
        Write-Status ""
        Trigger-Location2
        Write-Status ""
        Trigger-Location3
    }
}

Write-Status ""
Write-Status "🎉 Git pull trigger complete!" "Green"
Write-Status "NOTE: GitHub Actions will auto-trigger these on push to main" "Cyan"
