<?php
// report_operario_detalle.php
session_start();
include("conexion.php"); // define $con (mysqli)

/* ====== Seguridad ====== */
if (empty($_SESSION['oper_ok'])) {
  header("Location: report_operarios.php");
  exit;
}

/* -------- Helpers -------- */
function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function turnoName($t){ return $t==1?'Mañana':($t==2?'Tarde':($t==3?'Noche':'-')); }

/* -------- Filtros -------- */
$operario = g('operario','');
$desde    = g('desde','');
$hasta    = g('hasta','');

if ($operario==='') die("Operario (legajo) requerido.");

/* ====== Nombre del operario ====== */
$empNombreLegajo = '';
$qEmp = mysqli_query($con, "SELECT TRIM(CONCAT(apellido,' ',nombre)) AS n FROM empleado WHERE legajo=".intval($operario)." LIMIT 1");
if ($qEmp && ($emp = mysqli_fetch_assoc($qEmp))) $empNombreLegajo = $emp['n'];

/* ====== WHERE ====== */
$where = "h.operario = ".intval($operario);
if ($desde!=='') $where .= " AND h.fecha >= STR_TO_DATE('".mysqli_real_escape_string($con,$desde)."','%Y-%m-%d')";
if ($hasta!=='') $where .= " AND h.fecha <= STR_TO_DATE('".mysqli_real_escape_string($con,$hasta)."','%Y-%m-%d')";

/* ====== Totales ====== */
$sqlTotales = "
  SELECT 
    SUM(COALESCE(i.buenas,0)) AS buenas,
    SUM(COALESCE(i.malas,0)) AS malas,
    SUM(COALESCE(i.excedente,0)) AS excedente
  FROM op_header h
  JOIN op_item i ON i.header_id = h.id
  WHERE $where
";
$t = mysqli_fetch_assoc(mysqli_query($con, $sqlTotales));

/* ====== Detalle ====== */
$sql = "
  SELECT
    h.fecha, h.op, h.turno,
    i.tipo_codigo, i.codigo, i.maquina,
    i.cantidad_total, i.buenas, i.malas, i.excedente,
    i.caja_excedente, i.desc_trabajo, i.obs, i.created_at
  FROM op_header h
  JOIN op_item i ON i.header_id = h.id
  WHERE $where
  ORDER BY h.fecha DESC, h.op ASC, i.id ASC
  LIMIT 5000
";
$res = mysqli_query($con, $sql);

$RESPONSABLE = $_SESSION['oper_user'] ?? '—';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Detalle operario <?=h($operario)?> · Empaque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8fafc; }
    .section-card { border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:16px; }
    .badge-exc { background:#fef9c3; color:#854d0e; border:1px solid #facc15; }
    .chip-ls   { background:#e0f2fe; color:#075985; border:1px solid #7dd3fc; }
    .chip-f    { background:#fae8ff; color:#86198f; border:1px solid #f0abfc; }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 m-0">Detalle del operario</h1>
      <div class="text-muted" style="font-size:.9rem">Responsable: <b><?=h($RESPONSABLE)?></b></div>
    </div>
    <div>
      <a class="btn btn-outline-secondary" href="report_operarios.php">← Volver al reporte</a>
    </div>
  </div>

  <!-- Encabezado del operario -->
  <div class="section-card mb-4 text-center">
    <h2 class="mb-2">
      Operario <span class="text-primary">#<?=h($operario)?></span>
      <?= $empNombreLegajo ? ' — '.h($empNombreLegajo) : '' ?>
    </h2>
    <div class="d-flex justify-content-center gap-4 mt-3">
      <div><span class="text-success fw-bold fs-5"><?= (int)$t['buenas'] ?></span><br><small>Buenas</small></div>
      <div><span class="text-danger fw-bold fs-5"><?= (int)$t['malas'] ?></span><br><small>Malas</small></div>
      <div><span class="text-warning fw-bold fs-5"><?= (int)$t['excedente'] ?></span><br><small>Excedente</small></div>
    </div>
    <?php if ($desde || $hasta): ?>
      <div class="mt-2 text-muted small">
        <?= $desde ? 'Desde '.h($desde) : '' ?> <?= $hasta ? 'Hasta '.h($hasta) : '' ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Detalle -->
  <div class="section-card">
    <h5 class="mb-3">Detalle de producción</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Fecha</th>
            <th>OP</th>
            <th>Turno</th>
            <th>Tipo</th>
            <th>Código</th>
            <th>Máquina</th>
            <th>Total</th>
            <th class="text-success">Buenas</th>
            <th class="text-danger">Malas</th>
            <th class="text-warning">Excedente</th>
            <th>Caja</th>
            <th>Descripción</th>
            <th>Obs</th>
            <th>Creada</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($res && mysqli_num_rows($res)>0): ?>
            <?php while($row = mysqli_fetch_assoc($res)): ?>
              <tr>
                <td><?= h($row['fecha']) ?></td>
                <td><code><?= h($row['op']) ?></code></td>
                <td><?= turnoName((int)$row['turno']) ?></td>
                <td><?= $row['tipo_codigo']==='LS' ? '<span class="badge chip-ls">LS</span>' : '<span class="badge chip-f">F</span>' ?></td>
                <td class="small"><?= h($row['codigo']) ?></td>
                <td><?= h($row['maquina']) ?></td>
                <td><?= (int)$row['cantidad_total'] ?></td>
                <td class="text-success"><?= (int)$row['buenas'] ?></td>
                <td class="text-danger"><?= (int)$row['malas'] ?></td>
                <td><?= ((int)$row['excedente']>0) ? '<span class="badge badge-exc">'.(int)$row['excedente'].'</span>' : '0' ?></td>
                <td><?= ($row['caja_excedente'] && $row['caja_excedente']!=='0') ? h($row['caja_excedente']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= h($row['desc_trabajo']) ?></td>
                <td><?= h($row['obs']) ?></td>
                <td><?= h($row['created_at']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="14" class="text-center text-muted py-4">Sin registros para este operario en el rango</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
