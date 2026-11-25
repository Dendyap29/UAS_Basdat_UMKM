# Kompilasi dan jalankan MdxRunner terhadap skema Mondrian FORMA.
# Pemakaian: powershell -ExecutionPolicy Bypass -File database\dw\mondrian\run_mdx.ps1
# Prasyarat: JDK 11+, Schema Workbench di C:\schema-workbench, Pentaho di C:\pentaho\data-integration
param(
    [string]$WorkbenchLib = "C:\schema-workbench\lib",
    [string]$MysqlJar = "",
    [string]$DbHost = "127.0.0.1",
    [string]$Database = "umkm_dw",
    [string]$User = "root",
    [string]$Password = ""
)

$here = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not $MysqlJar) {
    $MysqlJar = (Get-ChildItem "C:\pentaho\data-integration\lib\mysql-connector-*.jar" | Select-Object -First 1).FullName
}
$build = Join-Path $env:TEMP "forma_mdx_build"
New-Item -ItemType Directory -Force $build | Out-Null

$cp = "$WorkbenchLib\*;$MysqlJar;$build"
javac -nowarn -cp $cp -d $build (Join-Path $here "MdxRunner.java")
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

java -cp $cp MdxRunner (Join-Path $here "..\umkm_dw_olap_schema.xml") (Join-Path $here "olap_mdx_queries.mdx") $DbHost $Database $User $Password
exit $LASTEXITCODE
