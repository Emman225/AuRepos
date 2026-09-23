# Recalcule le tableau d'avancement de PLAN-REALISATION.md a partir des cases cochees.
# Usage : pwsh -File .\maj-avancement.ps1
$plan = Join-Path $PSScriptRoot 'PLAN-REALISATION.md'
$lignes = Get-Content -Path $plan -Encoding UTF8

$phases = 'P0', 'P1', 'P2', 'P3', 'P4', 'P5', 'PT'
$total = @{}; $faites = @{}
foreach ($p in $phases) { $total[$p] = 0; $faites[$p] = 0 }

foreach ($l in $lignes) {
    if ($l -match '^- \[( |x|~)\] (P\d|PT)-') {
        $total[$Matches[2]]++
        if ($Matches[1] -eq 'x') { $faites[$Matches[2]]++ }
    }
}

function Pourcent($f, $t) { if ($t -eq 0) { '0 %' } else { '{0} %' -f [math]::Floor(100 * $f / $t) } }

$tt = ($total.Values | Measure-Object -Sum).Sum
$td = ($faites.Values | Measure-Object -Sum).Sum

$sortie = foreach ($l in $lignes) {
    if ($l -match '^\| (P\d|PT) \| ([^|]+) \|') {
        $p = $Matches[1]
        '| {0} | {1} | {2} | {3} | {4} |' -f $p, $Matches[2].Trim(), $total[$p], $faites[$p], (Pourcent $faites[$p] $total[$p])
    }
    elseif ($l -match '^\| \*\*Total\*\* \|') {
        '| **Total** | | **{0}** | **{1}** | **{2}** |' -f $tt, $td, (Pourcent $td $tt)
    }
    elseif ($l -match '^> Plan cr.. le .* Derni.re mise . jour') {
        $l -replace '(avancement : )\d{2}/\d{2}/\d{4}', ('${1}' + (Get-Date -Format 'dd/MM/yyyy'))
    }
    else { $l }
}

Set-Content -Path $plan -Value $sortie -Encoding UTF8
'{0} taches sur {1} terminees ({2})' -f $td, $tt, (Pourcent $td $tt)
