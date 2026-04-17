<?php
// report_op_consol_selector.php
session_start();
include("conexion.php"); // $con

// ------- Carga de OPs para datalist -------
$ops = [];
$qops = mysqli_query($con, "SELECT DISTINCT op FROM op_header ORDER BY created_at DESC LIMIT 300");
if ($qops) {
  while ($r = mysqli_fetch_assoc($qops)) { $ops[] = $r['op']; }
} else {
  // opcional: mostrar un pequeño aviso si la consulta falló
  // error_log("Error listando OPs: ".mysqli_error($con));
}

$op   = isset($_GET['op']) ? trim($_GET['op']) : '';
$rows = [];
$meta = null;

if ($op !== '') {
  $op_esc = mysqli_real_escape_string($con, $op);

  // ------- Meta de la OP (rango de fechas, turnos, operarios) -------
  $sqlMeta = "
    SELECT 
      MIN(h.fecha) AS fecha_min,
      MAX(h.fecha) AS fecha_max,
      MIN(h.created_at) AS created_min,
      MAX(h.created_at) AS created_max,
      GROUP_CONCAT(DISTINCT h.turno ORDER BY h.turno) AS turnos,
      GROUP_CONCAT(DISTINCT h.operario ORDER BY h.operario) AS operarios
    FROM op_header h
    WHERE h.op = '$op_esc'
  ";
  $rMeta = mysqli_query($con, $sqlMeta);
  if ($rMeta) { $meta = mysqli_fetch_assoc($rMeta); }

  // ------- Consolidado por (tipo,codigo) -------
  $sql = "
    SELECT 
      i.tipo_codigo,
      i.codigo,
      GROUP_CONCAT(DISTINCT i.maquina ORDER BY i.maquina SEPARATOR ', ') AS maquinas,
      MIN(i.cantidad_total) AS total_min,
      MAX(i.cantidad_total) AS total_max,
      SUM(i.buenas)    AS buenas,
      SUM(i.malas)     AS malas,
      SUM(i.excedente) AS excedente,
      GROUP_CONCAT(DISTINCT NULLIF(i.caja_excedente,'') ORDER BY i.caja_excedente SEPARATOR ' | ') AS cajas_excedente,
      GROUP_CONCAT(DISTINCT NULLIF(i.desc_trabajo,'') ORDER BY i.desc_trabajo SEPARATOR ' | ')     AS descripciones,
      GROUP_CONCAT(DISTINCT NULLIF(i.obs,'') ORDER BY i.obs SEPARATOR ' | ')                        AS observaciones,
      COUNT(*) AS lineas_incluidas
    FROM op_header h
    JOIN op_item   i ON i.header_id = h.id
    WHERE h.op = '$op_esc'
    GROUP BY i.tipo_codigo, i.codigo
    ORDER BY i.tipo_codigo, i.codigo
  ";
  $res = mysqli_query($con, $sql);
  if ($res) {
    while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
  } else {
    // opcional: mostrar mensaje visible si falla
    $error_msg = "Error al consolidar: ".mysqli_error($con);
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Consolidar por OP</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/styles.css">
<style>
  .badge-exc { background:#fef9c3; color:#854d0e; border:1px solid #facc15; }
  .chip-ls   { background:#e0f2fe; color:#075985; border:1px solid #7dd3fc; }
  .chip-f    { background:#fae8ff; color:#86198f; border:1px solid #f0abfc; }
  .page-title { text-align:center; font-size:1.6rem; font-weight:800; margin:0 0 14px; }
</style>
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title m-0">Consolidar por OP</h1>
    <div class="d-flex gap-2">
      <a href="report_ops.php" class="btn btn-outline-secondary">← Volver a reportes</a>
      <a href="index.php" class="btn btn-secondary">Inicio</a>
    </div>
  </div>

  <!-- Selector de OP -->
  <div class="section-card compact mb-3">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-6">
        <label class="form-label">OP</label>
        <input name="op" list="opsList" class="form-control" value="<?=htmlspecialchars($op)?>" placeholder="Escribí o elegí una OP" required>
        <datalist id="opsList">
          <?php foreach($ops as $oop): ?>
            <option value="<?=htmlspecialchars($oop)?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-12 col-md-3">
        <button class="btn btn-primary w-100" type="submit">Consolidar</button>
      </div>
      <div class="col-12 col-md-3">
        <a class="btn btn-outline-secondary w-100" href="report_op_consol_selector.php">Limpiar</a>
      </div>
    </form>
  </div>

  <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><?=htmlspecialchars($error_msg)?></div>
  <?php endif; ?>

  <?php if ($op !== ''): ?>
    <!-- Meta OP -->
    <div class="section-card compact mb-3">
      <div class="row g-2">
        <div class="col-6 col-md-3"><b>OP:</b> <code><?=htmlspecialchars($op)?></code></div>
        <div class="col-6 col-md-3"><b>Fecha(s) OP:</b>
          <?= htmlspecialchars($meta && $meta['fecha_min'] ? $meta['fecha_min'] : '—') ?>
          <?php if ($meta && $meta['fecha_max'] && $meta['fecha_max'] !== $meta['fecha_min']): ?>
            a <?= htmlspecialchars($meta['fecha_max']) ?>
          <?php endif; ?>
        </div>
        <div class="col-6 col-md-3"><b>Turnos:</b> <?= htmlspecialchars($meta && $meta['turnos'] ? $meta['turnos'] : '—') ?></div>
        <div class="col-6 col-md-3"><b>Operarios:</b> <?= htmlspecialchars($meta && $meta['operarios'] ? $meta['operarios'] : '—') ?></div>
      </div>
    </div>

    <!-- Tabla consolidada -->
    <div class="section-card compact">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Tipo</th>
              <th>Código</th>
              <th>Máquinas</th>
              <th>Total</th>
              <th class="text-success">Buenas</th>
              <th class="text-danger">Malas</th>
              <th class="text-warning">Excedente</th>
              <th>Cajas excedente</th>
              <th>Descripciones</th>
              <th>Observaciones</th>
              <th>Líneas</th>
            </tr>
          </thead>
          <tbody>
          <?php if (count($rows) > 0): $n=0; ?>
            <?php foreach ($rows as $row): $n++; 
              $total_min = (int)$row['total_min'];
              $total_max = (int)$row['total_max'];
            ?>
              <tr>
                <td><?=$n?></td>
                <td>
                  <?php if ($row['tipo_codigo']==='LS'): ?>
                    <span class="badge chip-ls">LS</span>
                  <?php else: ?>
                    <span class="badge chip-f">F</span>
                  <?php endif; ?>
                </td>
                <td><code><?=htmlspecialchars($row['codigo'])?></code></td>
                <td><?=htmlspecialchars($row['maquinas'])?></td>
                <td>
                  <?php if ($total_min === $total_max): ?>
                    <?=$total_min?>
                  <?php else: ?>
                    <span class="text-muted">varía:</span> <?=$total_min?>–<?=$total_max?>
                  <?php endif; ?>
                </td>
                <td class="text-success"><?= (int)$row['buenas'] ?></td>
                <td class="text-danger"><?= (int)$row['malas'] ?></td>
                <td>
                  <?php $exc=(int)$row['excedente']; ?>
                  <?php if ($exc>0): ?><span class="badge badge-exc"><?=$exc?></span><?php else: ?>0<?php endif; ?>
                </td>
                <td><?= $row['cajas_excedente'] ? htmlspecialchars($row['cajas_excedente']) : '—' ?></td>
                <td><?= $row['descripciones'] ? htmlspecialchars($row['descripciones']) : '—' ?></td>
                <td><?= $row['observaciones'] ? htmlspecialchars($row['observaciones']) : '—' ?></td>
                <td><?= (int)$row['lineas_incluidas'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="12" class="text-center text-muted py-4">Sin datos para la OP indicada.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
