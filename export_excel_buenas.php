<?php
// export_excel_buenas.php (v2)
// Pivote de BUENAS por Máquina (SRI-1..SRI-4) y Turno (M/T/N)
// Filas: OP + Descripción (separadas). Export HTML compatible con Excel.

session_start();
include("conexion.php"); // define $con (mysqli)

function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$fecha = g('fecha', '');
$maq   = g('maquina','');
$turno = g('turno','');

if ($fecha==='') $fecha = date('Y-m-d');

// ---------- Filtros ----------
$where = "h.fecha = STR_TO_DATE('".mysqli_real_escape_string($con,$fecha)."','%Y-%m-%d')";
if ($maq!=='')   $where .= " AND i.maquina = '".mysqli_real_escape_string($con,$maq)."'";
if ($turno!=='') $where .= " AND h.turno = ".intval($turno);

// ---------- Consulta base: solo BUENAS, agrupado ----------
$sql = "
  SELECT
    i.maquina,            -- SRI-1..SRI-4
    h.turno,              -- 1/2/3
    h.op,                 -- OP
    COALESCE(NULLIF(TRIM(i.desc_trabajo),''),'(Sin descripción)') AS desc_trabajo,
    SUM(i.buenas) AS buenas
  FROM op_item i
  JOIN op_header h ON h.id = i.header_id
  WHERE $where
  GROUP BY i.maquina, h.turno, h.op, desc_trabajo
";
$res = mysqli_query($con, $sql);

// ---------- Pivot ----------
$machines = ['SRI-1','SRI-2','SRI-3','SRI-4'];
$turnos   = [1,2,3]; // M/T/N

$rows = []; // clave: "op|desc" => ['op'=>..., 'desc'=>..., 'vals'][maquina][turno]=buenas
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $key = $r['op']."|".$r['desc_trabajo'];
    if (!isset($rows[$key])) {
      $rows[$key] = ['op'=>$r['op'], 'desc'=>$r['desc_trabajo'], 'vals'=>[]];
    }
    $rows[$key]['vals'][$r['maquina']][(int)$r['turno']] = (int)$r['buenas'];
  }
}
uksort($rows, function($a,$b){
  [$opA,$descA] = explode('|',$a,2);
  [$opB,$descB] = explode('|',$b,2);
  if ($opA === $opB) return strnatcasecmp($descA,$descB);
  return strnatcasecmp($opA,$opB);
});

// Totales por máquina/turno
$tot_sri = array_fill_keys($machines, 0);
$tot_sri_turno = [];
foreach ($machines as $m) $tot_sri_turno[$m] = array_fill_keys($turnos, 0);

// ---------- Cabeceras Excel ----------
$filename = "reporte_SRI_".$fecha.".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// ---------- HTML ----------
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

  /* Un poco de separación visual en totales por SRI */
  .sep-left  { border-left: 2px solid #cbd5e1 !important; }
</style>

<body>
  <div class="title">SRI · Reporte diario</div>
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
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?=2 + (4*count($machines)) + 1?>" class="t-center">Sin datos</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $row_total_general = 0;
            $row_tot_sri = array_fill_keys($machines, 0);
          ?>
          <tr>
            <td><span class="badge"><?=h($row['op'])?></span></td>
            <td><?=h($row['desc'])?></td>

            <?php foreach ($machines as $m): ?>
              <?php
                $m_tot = 0;
                foreach ([1,2,3] as $t) {
                  $v = (int)($row['vals'][$m][$t] ?? 0);
                  $m_tot += $v;
                  $tot_sri_turno[$m][$t] += $v;
                  echo '<td class="t-right" style="mso-number-format:\'0\'">'.(int)$v.'</td>';
                }
                $row_tot_sri[$m] = $m_tot;
                $tot_sri[$m] += $m_tot;
                $row_total_general += $m_tot;
              ?>
              <td class="t-right col-total-sri sep-left" style="mso-number-format:'0'"><?= (int)$m_tot ?></td>
            <?php endforeach; ?>

            <td class="t-right col-grand sep-left" style="mso-number-format:'0'"><?= (int)$row_total_general ?></td>
          </tr>
        <?php endforeach; ?>

        <!-- Totales por Turno/Máquina + totales por SRI -->
        <tr class="subhead">
          <td colspan="2" class="t-right"><b>Totales por Turno</b></td>
          <?php
            $grand_total = 0;
            foreach ($machines as $m):
              foreach ([1,2,3] as $t):
          ?>
              <td class="t-right"><b><?= (int)$tot_sri_turno[$m][$t] ?></b></td>
          <?php
              endforeach;
              $grand_total += $tot_sri[$m];
          ?>
              <td class="t-right head-total-sri sep-left"><b><?= (int)$tot_sri[$m] ?></b></td>
          <?php endforeach; ?>
          <td class="t-right head-total-sri sep-left"><b><?= (int)$grand_total ?></b></td>
        </tr>

        <!-- Fila TOTAL GENERAL -->
        <tr class="row-grand">
          <td colspan="<?=2 + (4*count($machines))?>" class="t-right">TOTAL GENERAL</td>
          <td class="t-right"><b><?= (int)$grand_total ?></b></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
