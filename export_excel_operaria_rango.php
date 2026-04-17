<?php
// export_excel_operaria_rango.php
// Genera un Excel (HTML compatible) con:
// 1) Totales por tipo (LS/F) y máquina en el rango
// 2) Totales generales (Buenas / Malas / Excedente)
// 3) Detalle por Fecha + OP + Tipo (LS/F) con Buenas, Malas, Excedente

session_start();
include("conexion.php"); // define $con (mysqli)

function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$operario = g('operario','');
$desde    = g('desde','');   // YYYY-MM-DD o vacío
$hasta    = g('hasta','');   // YYYY-MM-DD o vacío

if ($operario==='') {
  die("Debe informar el Operario (legajo).");
}

/* ====== Datos del empleado ====== */
$empNombre = '';
$qEmp = mysqli_query($con, "SELECT TRIM(CONCAT(apellido,' ',nombre)) AS n FROM empleado WHERE legajo=".intval($operario)." LIMIT 1");
if ($qEmp && ($emp = mysqli_fetch_assoc($qEmp))) $empNombre = $emp['n'];

/* ====== WHERE ====== */
$where = "h.operario = ".intval($operario);
if ($desde!=='') $where .= " AND h.fecha >= STR_TO_DATE('".mysqli_real_escape_string($con,$desde)."','%Y-%m-%d')";
if ($hasta!=='') $where .= " AND h.fecha <= STR_TO_DATE('".mysqli_real_escape_string($con,$hasta)."','%Y-%m-%d')";

/* ====== 1) Totales por tipo y máquina ====== */
$sqlTot = "
  SELECT 
    i.tipo_codigo AS tipo,
    i.maquina,
    SUM(COALESCE(i.buenas,0))    AS buenas,
    SUM(COALESCE(i.malas,0))     AS malas,
    SUM(COALESCE(i.excedente,0)) AS excedente
  FROM op_item i
  JOIN op_header h ON h.id = i.header_id
  WHERE $where
  GROUP BY i.tipo_codigo, i.maquina
  ORDER BY i.tipo_codigo, i.maquina
";
$rTot = mysqli_query($con, $sqlTot);
$rowsTot = [];
if ($rTot) while ($r = mysqli_fetch_assoc($rTot)) $rowsTot[] = $r;

/* Totales generales */
$sqlGen = "
  SELECT
    SUM(COALESCE(i.buenas,0))    AS tb,
    SUM(COALESCE(i.malas,0))     AS tm,
    SUM(COALESCE(i.excedente,0)) AS te
  FROM op_item i
  JOIN op_header h ON h.id = i.header_id
  WHERE $where
";
$rGen = mysqli_query($con, $sqlGen);
list($tot_b, $tot_m, $tot_e) = [0,0,0];
if ($rGen && ($rg = mysqli_fetch_assoc($rGen))) {
  $tot_b = (int)$rg['tb']; $tot_m = (int)$rg['tm']; $tot_e = (int)$rg['te'];
}

/* ====== 2) Detalle por fecha y OP (compactado) ====== */
$sqlDet = "
  SELECT
    DATE_FORMAT(h.fecha,'%Y-%m-%d') AS fecha,
    h.op,
    i.tipo_codigo AS tipo,
    SUM(COALESCE(i.buenas,0))    AS buenas,
    SUM(COALESCE(i.malas,0))     AS malas,
    SUM(COALESCE(i.excedente,0)) AS excedente
  FROM op_item i
  JOIN op_header h ON h.id = i.header_id
  WHERE $where
  GROUP BY h.fecha, h.op, i.tipo_codigo
  ORDER BY h.fecha ASC, h.op ASC, i.tipo_codigo ASC
";
$rDet = mysqli_query($con, $sqlDet);
$rowsDet = [];
if ($rDet) while ($r = mysqli_fetch_assoc($rDet)) $rowsDet[] = $r;

/* ====== Headers Excel ====== */
$labelDesde = $desde!=='' ? $desde : '—';
$labelHasta = $hasta!=='' ? $hasta : '—';
$filename   = "reporte_operario_{$operario}_rango.xls";

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
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; }
  thead th { background: #f3f4f6; font-weight: 700; }
  .title { font-size: 16px; font-weight: bold; margin-bottom: 6px; }
  .totals { background: #fef9c3; font-weight: bold; }
  .mt-16 { margin-top: 16px; }
</style>

<body>
  <div class="title">Reporte por operario (rango)</div>
  <div>Operario: <b><?=h($operario)?></b> — <?=h($empNombre ?: 'Desconocido')?> </div>
  <div>Rango: <b><?=h($labelDesde)?></b> a <b><?=h($labelHasta)?></b></div>

  <!-- Totales generales -->
  <h3 class="mt-16">Totales generales</h3>
  <table>
    <thead>
      <tr>
        <th>Buenas</th>
        <th>Malas</th>
        <th>Excedente</th>
      </tr>
    </thead>
    <tbody>
      <tr class="totals">
        <td align="right"><b><?= (int)$tot_b ?></b></td>
        <td align="right"><b><?= (int)$tot_m ?></b></td>
        <td align="right"><b><?= (int)$tot_e ?></b></td>
      </tr>
    </tbody>
  </table>

  <!-- Totales por tipo y máquina -->
  <h3 class="mt-16">Totales por tipo y máquina</h3>
  <table>
    <thead>
      <tr>
        <th>Tipo</th>
        <th>Máquina</th>
        <th>Buenas</th>
        <th>Malas</th>
        <th>Excedente</th>
      </tr>
    </thead>
    <tbody>
<?php if (empty($rowsTot)): ?>
      <tr><td colspan="5" align="center">Sin datos</td></tr>
<?php else: foreach ($rowsTot as $r): ?>
      <tr>
        <td><?=h($r['tipo'])?></td>
        <td><?=h($r['maquina'])?></td>
        <td align="right"><?= (int)$r['buenas'] ?></td>
        <td align="right"><?= (int)$r['malas'] ?></td>
        <td align="right"><?= (int)$r['excedente'] ?></td>
      </tr>
<?php endforeach; endif; ?>
    </tbody>
  </table>

  <!-- Detalle por fecha y OP -->
  <h3 class="mt-16">Detalle por fecha y OP</h3>
  <table>
    <thead>
      <tr>
        <th>Fecha</th>
        <th>OP</th>
        <th>Tipo</th>
        <th>Buenas</th>
        <th>Malas</th>
        <th>Excedente</th>
      </tr>
    </thead>
    <tbody>
<?php if (empty($rowsDet)): ?>
      <tr><td colspan="6" align="center">Sin movimientos en el rango</td></tr>
<?php else: foreach ($rowsDet as $r): ?>
      <tr>
        <td><?=h($r['fecha'])?></td>
        <td><?=h($r['op'])?></td>
        <td><?=h($r['tipo'])?></td>
        <td align="right"><?= (int)$r['buenas'] ?></td>
        <td align="right"><?= (int)$r['malas'] ?></td>
        <td align="right"><?= (int)$r['excedente'] ?></td>
      </tr>
<?php endforeach; endif; ?>
    </tbody>
  </table>
</body>
</html>
