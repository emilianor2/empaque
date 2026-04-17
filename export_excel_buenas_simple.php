<?php
// export_excel_buenas_simple.php
// XLS (HTML compatible) SIN Composer.
// - Una fila por OP (usa primera descripción encontrada)
// - Pivote BUENAS por SRI (M,T,N + Total SRI) y Total general con FÓRMULAS
// - Totales por turno/columna y TOTAL GENERAL con FÓRMULAS

session_start();
include("conexion.php"); // define $con (mysqli)

function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ===== Filtros =====
$fecha = g('fecha','');
$maq   = g('maquina',''); // opcional
$turno = g('turno','');   // opcional
if ($fecha==='') $fecha = date('Y-m-d');

// ===== WHERE =====
$where = "h.fecha = STR_TO_DATE('".mysqli_real_escape_string($con,$fecha)."','%Y-%m-%d')";
if ($maq!=='')   $where .= " AND i.maquina = '".mysqli_real_escape_string($con,$maq)."'";
if ($turno!=='') $where .= " AND h.turno = ".intval($turno);

// ===== Consulta base (solo BUENAS) =====
$sql = "
  SELECT
    h.op,
    i.maquina,               -- SRI-1..SRI-4
    h.turno,                 -- 1/2/3
    MIN(COALESCE(NULLIF(TRIM(i.desc_trabajo),''),'(Sin descripción)')) AS desc_choice,
    SUM(i.buenas) AS buenas
  FROM op_item i
  JOIN op_header h ON h.id = i.header_id
  WHERE $where
  GROUP BY h.op, i.maquina, h.turno
  ORDER BY h.op ASC
";
$res = mysqli_query($con, $sql);

// ===== Pivot por OP =====
$machines = ['SRI-1','SRI-2','SRI-3','SRI-4'];   // columnas fijas
$turnos   = [1,2,3];                              // M/T/N

$rows = []; // rows[op] = ['op'=>..., 'desc'=>..., 'vals'][maq][turno] = buenas
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $op  = $r['op'];
    $mq  = $r['maquina'];
    $tu  = (int)$r['turno'];
    $b   = (int)$r['buenas'];

    if (!isset($rows[$op])) {
      $rows[$op] = ['op'=>$op, 'desc'=>$r['desc_choice'], 'vals'=>[]];
    }
    if (empty($rows[$op]['desc']) && !empty($r['desc_choice'])) {
      $rows[$op]['desc'] = $r['desc_choice'];
    }
    if (!isset($rows[$op]['vals'][$mq])) $rows[$op]['vals'][$mq] = [];
    $rows[$op]['vals'][$mq][$tu] = ($rows[$op]['vals'][$mq][$tu] ?? 0) + $b;
  }
}
ksort($rows, SORT_NATURAL);

// ===== Helper: número de columna -> letra (1=A) =====
function colL($n){
  $s=''; while($n>0){ $n--; $s=chr($n%26+65).$s; $n=intdiv($n,26);} return $s;
}

// ===== Mapeo de columnas =====
// A: OP, B: Descripción
$COL_OP   = 1; // A
$COL_DESC = 2; // B
$COLS     = []; // $COLS['SRI-1'] = ['M'=>3,'T'=>4,'N'=>5,'TOT'=>6] etc.
$c = 3;
foreach ($machines as $m) {
  $COLS[$m] = ['M'=>$c++, 'T'=>$c++, 'N'=>$c++, 'TOT'=>$c++]; // 4 columnas por SRI
}
$COL_TGENERAL = $c++;      // Total general por fila
$TOTAL_COLS   = $COL_TGENERAL;

// ===== Cabeceras Excel =====
$filename = "reporte_buenas_{$fecha}.xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

?>
<!DOCTYPE html>
<html lang="es">
<meta charset="utf-8">
<style>
  body { font-family: Arial, Helvetica, sans-serif; }
  .title { font-weight: 800; font-size: 16px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border:1px solid #d1d5db; padding:6px 8px; font-size:12px; }
  thead th { background:#f1f5f9; color:#0f172a; }
  .subhead { background:#e2e8f0; font-weight:700; }
  .t-right { text-align:right; }
  .t-center{ text-align:center; }
  .badge { display:inline-block; padding:2px 6px; border-radius:999px; border:1px solid #cbd5e1; background:#fff; }

  /* Colores para Totales por SRI y Total general */
  .col-total-sri  { background:#fff7ed; font-weight:700; }   /* anaranjado suave */
  .head-total-sri { background:#fde68a; font-weight:800; }   /* cabecera más marcada */

  .col-grand      { background:#fef08a; font-weight:800; }   /* Total general por fila */
  .row-grand      { background:#fde047; font-weight:800; }   /* Fila TOTAL GENERAL (pie) */

  /* Anchos fijos usando colgroup (Excel los respeta) */
  .w-op      { width: 70pt;  }
  .w-desc    { width: 260pt; }
  .w-mtn     { width: 60pt;  }   /* M/T/N iguales */
  .w-total   { width: 75pt;  }   /* Total por SRI */
  .w-grand   { width: 90pt;  }   /* Total General */

  /* Separador izquierdo para TOT */
  .sep-left  { border-left: 2px solid #cbd5e1 !important; }
</style>

<body>
  <div class="title">SRI · Reporte diario (solo BUENAS)</div>
  <div>Fecha: <b><?=h($fecha)?></b> &nbsp; | &nbsp; Máquina: <b><?= $maq? h($maq):'Todas' ?></b> &nbsp; | &nbsp; Turno: <b><?= $turno? ['','Mañana','Tarde','Noche'][(int)$turno]:'Todos' ?></b></div>
  <br>

  <table>
    <!-- Colgroup para forzar anchos fijos -->
    <colgroup>
      <col class="w-op">
      <col class="w-desc">
      <?php foreach ($machines as $m): ?>
        <col class="w-mtn"><!-- M -->
        <col class="w-mtn"><!-- T -->
        <col class="w-mtn"><!-- N -->
        <col class="w-total"><!-- Total SRI -->
      <?php endforeach; ?>
      <col class="w-grand"><!-- Total general -->
    </colgroup>

    <thead>
      <tr>
        <th rowspan="2">OP</th>
        <th rowspan="2">Descripción</th>

        <?php foreach ($machines as $m): ?>
          <th class="t-center" colspan="4"><?=$m?></th>
        <?php endforeach; ?>

        <th rowspan="2" class="t-center head-total-sri sep-left">Total<br>general</th>
      </tr>
      <tr>
        <?php foreach ($machines as $m): ?>
          <th class="t-center">M</th>
          <th class="t-center">T</th>
          <th class="t-center">N</th>
          <th class="t-center head-total-sri sep-left">Total <?=$m?></th>
        <?php endforeach; ?>
      </tr>
    </thead>

    <tbody>
<?php
$firstDataRow = 6;          // en Excel, primera fila de datos (después de 2 filas header + subhead)
$currentExcelRow = $firstDataRow;
$rowCount = 0;

// ===== Render de filas OP (una por OP) =====
if (empty($rows)) {
  $colspan = 2 + (4*count($machines)) + 1;
  echo '<tr><td colspan="'.$colspan.'" class="t-center">Sin datos</td></tr>';
} else {
  foreach ($rows as $item) {
    $rowCount++;
    echo "<tr>\n";
    // OP y Descripción
    echo '<td><span class="badge">'.h($item['op'])."</span></td>\n";
    echo '<td>'.h($item['desc'])."</td>\n";

    // Valores y fórmulas por SRI
    foreach ($machines as $m) {
      $vM = (int)($item['vals'][$m][1] ?? 0);
      $vT = (int)($item['vals'][$m][2] ?? 0);
      $vN = (int)($item['vals'][$m][3] ?? 0);

      $colM  = colL($COLS[$m]['M']);
      $colT  = colL($COLS[$m]['T']);
      $colN  = colL($COLS[$m]['N']);
      $colTot= colL($COLS[$m]['TOT']);

      // M, T, N (valores crudos sin formateo para que Excel entienda como número)
      echo '<td class="t-right">'.$vM.'</td>';
      echo '<td class="t-right">'.$vT.'</td>';
      echo '<td class="t-right">'.$vN.'</td>';

      // Total SRI = SUMA(M:N) con FÓRMULA
      $formulaTotSRI = "=SUMA({$colM}{$currentExcelRow}:{$colN}{$currentExcelRow})";
      echo '<td class="t-right col-total-sri sep-left">'.$formulaTotSRI.'</td>';
    }

    // Total general por fila = SUMA(F, J, N, R) -> siempre son las columnas TOT de cada SRI
    // Calculamos dinámicamente las letras de las columnas TOT para cada SRI
    $totColsRefs = [];
    foreach ($machines as $m) {
      $totColsRefs[] = colL($COLS[$m]['TOT']).$currentExcelRow;
    }
    $formulaGrand = '='.implode('+', $totColsRefs);
    echo '<td class="t-right col-grand sep-left">'.$formulaGrand."</td>\n";

    echo "</tr>\n";
    $currentExcelRow++;
  }
}

$lastDataRow = $currentExcelRow - 1;

// ===== Totales por Turno + SRI + Total general (todas FÓRMULAS) =====
if ($rowCount > 0) {
  echo '<tr class="subhead">';
  echo '<td colspan="2" class="t-right"><b>Totales por Turno</b></td>';

  // Recorremos cada columna de datos (M,T,N,TOT por SRI)
  foreach ($machines as $m) {
    // M, T, N: sumas por columna
    foreach (['M','T','N'] as $tt) {
      $col = colL($COLS[$m][$tt]);
      $formulaCol = "=SUMA({$col}{$firstDataRow}:{$col}{$lastDataRow})";
      echo '<td class="t-right"><b>'.$formulaCol.'</b></td>';
    }
    // TOT por SRI (columna de totales con estilo diferenciado)
    $colTot = colL($COLS[$m]['TOT']);
    $formulaTotSRI = "=SUMA({$colTot}{$firstDataRow}:{$colTot}{$lastDataRow})";
    echo '<td class="t-right head-total-sri sep-left"><b>'.$formulaTotSRI.'</b></td>';
  }

  // Total general (suma de la columna de total general por fila)
  $colG = colL($COL_TGENERAL);
  $formulaGrandTotal = "=SUMA({$colG}{$firstDataRow}:{$colG}{$lastDataRow})";
  echo '<td class="t-right head-total-sri sep-left"><b>'.$formulaGrandTotal.'</b></td>';

  echo "</tr>\n";

  // Fila TOTAL GENERAL (texto a la izquierda + total a la derecha)
  $span = 2 + (4*count($machines));
  echo '<tr class="row-grand">';
  echo '<td colspan="'.$span.'" class="t-right">TOTAL GENERAL</td>';
  echo '<td class="t-right"><b>'.$formulaGrandTotal.'</b></td>';
  echo "</tr>\n";
}
?>
    </tbody>
  </table>
</body>
</html>
