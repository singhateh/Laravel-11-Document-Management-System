# ============================================================
# check-routes.ps1  --  Laravel route health checker
# Usage:  .\check-routes.ps1  [-BaseUrl http://127.0.0.1:8000]
# ============================================================

param(
    [string]$BaseUrl = "http://127.0.0.1:8000"
)

function Probe {
    param(
        [string]$Method   = "GET",
        [string]$Uri,
        [hashtable]$Headers = @{},
        [string]$Body     = $null
    )
    try {
        $req = [System.Net.HttpWebRequest]::Create($Uri)
        $req.Method             = $Method
        $req.AllowAutoRedirect  = $false
        $req.Timeout            = 10000
        foreach ($k in $Headers.Keys) {
            if ($k -eq "Accept") { $req.Accept = $Headers[$k] }
            else { $req.Headers[$k] = $Headers[$k] }
        }
        if ($Body) {
            $req.ContentType = "application/json"
            $bytes = [System.Text.Encoding]::UTF8.GetBytes($Body)
            $req.ContentLength = $bytes.Length
            $stream = $req.GetRequestStream()
            $stream.Write($bytes, 0, $bytes.Length)
            $stream.Close()
        }
        $resp = $req.GetResponse()
        $code = [int]$resp.StatusCode
        $resp.Close()
        return $code
    } catch [System.Net.WebException] {
        if ($_.Exception.Response) {
            $code = [int]$_.Exception.Response.StatusCode
            $_.Exception.Response.Close()
            return $code
        }
        return 0
    } catch {
        return 0
    }
}

function Check {
    param(
        [string]$Label,
        [string]$Method  = "GET",
        [string]$Path,
        [int[]] $Expect,
        [hashtable]$Headers = @{},
        [string]$Body    = $null,
        [string]$Note    = ""
    )
    $uri    = $BaseUrl + $Path
    $actual = Probe -Method $Method -Uri $uri -Headers $Headers -Body $Body
    $ok        = $actual -in $Expect
    $expectStr = $Expect -join " or "
    $tick      = if ($ok) { "[  OK  ]" } else { "[ FAIL ]" }
    $noteStr   = if ($Note) { "  # $Note" } else { "" }
    $color     = if ($ok) { "Green" } else { "Red" }
    $detail    = "got $actual  (expected $expectStr)"
    Write-Host ("{0}  {1,-44} {2}{3}" -f $tick, "$Method $Path", $detail, $noteStr) -ForegroundColor $color
    return [pscustomobject]@{ Label=$Label; Ok=$ok; Status=$actual }
}

Write-Host ""
Write-Host ("Laravel route checker -> " + $BaseUrl) -ForegroundColor Cyan
Write-Host ("-" * 80) -ForegroundColor DarkGray

# -- Public web ---------------------------------------------------------------
Write-Host "`n[Public web -- no session required]" -ForegroundColor Yellow
$results = @()
$results += Check "welcome"    GET "/"          @(200)  -Note "Welcome page (guest)"
$results += Check "health"     GET "/up"        @(200)  -Note "Laravel health check"
$results += Check "spa-shell"  GET "/stego-app" @(200)  -Note "StegoLock SPA shell"
$results += Check "login-page" GET "/login"     @(200)  -Note "Login form"
$results += Check "register"   GET "/register"  @(200)  -Note "Register form"

# -- Auth-guarded web ---------------------------------------------------------
Write-Host "`n[Auth-guarded web -- expect 302 to /login for guests]" -ForegroundColor Yellow
$results += Check "documents"  GET "/documents"   @(302)
$results += Check "folders"    GET "/folders"     @(302)
$results += Check "dashboard"  GET "/dashboard"   @(302)
$results += Check "profile"    GET "/profile"     @(302)
$results += Check "tags"       GET "/tags"        @(302)
$results += Check "categories" GET "/categories"  @(302)
$results += Check "search"     GET "/search"      @(302)
$results += Check "stego"      GET "/stego"       @(302)
$results += Check "home"       GET "/home"        @(302)
$results += Check "projects"   GET "/projects"    @(302)
$results += Check "contacts"   GET "/contacts"    @(302)

# -- API: public --------------------------------------------------------------
Write-Host "`n[API -- public endpoints]" -ForegroundColor Yellow
$J = @{ "Accept" = "application/json" }
$loginBody    = '{"email":"","password":""}'
$results += Check "api-health"   GET  "/api/health"        @(200)  -Headers $J  -Note "JSON health"
$results += Check "api-login"    POST "/api/auth/login"    @(422)  -Headers $J  -Body $loginBody  -Note "422=validation, endpoint alive"
$results += Check "api-register" POST "/api/auth/register" @(422)  -Headers $J  -Body $loginBody  -Note "422=validation, endpoint alive"

# -- API: auth-guarded --------------------------------------------------------
Write-Host "`n[API -- auth-guarded (expect 401 without token)]" -ForegroundColor Yellow
$results += Check "api-me"          GET    "/api/auth/me"          @(401)      -Headers $J
$results += Check "api-logout"      POST   "/api/auth/logout"      @(401)      -Headers $J
$results += Check "api-tokens"      GET    "/api/auth/tokens"      @(401)      -Headers $J
$results += Check "api-docs-list"   GET    "/api/documents"        @(401)      -Headers $J
$results += Check "api-docs-show"   GET    "/api/documents/1"      @(401,404)  -Headers $J
$results += Check "api-docs-store"  POST   "/api/documents"        @(401)      -Headers $J
$results += Check "api-stego-docs"  GET    "/api/stego/documents"  @(401)      -Headers $J
$results += Check "api-stego"       GET    "/api/stego"            @(401)      -Headers $J
$results += Check "api-dash-stats"  GET    "/api/dashboard/stats"  @(401)      -Headers $J
$results += Check "api-dash-recent" GET    "/api/dashboard/recent" @(401)      -Headers $J
$results += Check "api-encode"      POST   "/api/stego/encode"     @(401)      -Headers $J
$results += Check "api-decode"      POST   "/api/stego/decode"     @(401)      -Headers $J

# -- Edge cases ---------------------------------------------------------------
Write-Host "`n[Edge cases]" -ForegroundColor Yellow
$results += Check "404-web" GET "/this-route-xyz-404" @(200,302,404)  -Note "Must not 500"
$results += Check "404-api" GET "/api/route-xyz-404"  @(404)          -Headers $J  -Note "JSON 404"
$results += Check "405-api" GET "/api/auth/login"     @(405)          -Headers $J  -Note "GET on POST-only"

# -- Summary ------------------------------------------------------------------
$passed = ($results | Where-Object { $_.Ok }).Count
$failed = ($results | Where-Object { -not $_.Ok }).Count
$total  = $results.Count
Write-Host ""
Write-Host ("-" * 80) -ForegroundColor DarkGray
$summaryColor = if ($failed -eq 0) { "Green" } else { "Yellow" }
Write-Host ("Summary: $passed/$total passed") -ForegroundColor $summaryColor
if ($failed -gt 0) {
    Write-Host "`nFailed checks:" -ForegroundColor Red
    $results | Where-Object { -not $_.Ok } | ForEach-Object {
        Write-Host ("  - " + $_.Label + "  (got HTTP " + $_.Status + ")") -ForegroundColor Red
    }
}
Write-Host ""
