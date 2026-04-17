<?php
session_start();
include("conexion.php"); // define $con (mysqli)

function g($k, $d = '') { return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$op     = g('op');
$tipo   = g('tipo');   // '', 'LS', 'F'
$codigo = g('codigo'); // busca por base (antes de '/')

$codigoBase = $codigo;
if ($codigoBase !== '') {
  $parts = explode('/', $codigoBase, 2);
  $codigoBase = trim($parts[0]);
}

$where = "1=1";
if ($op !== '') {
  $opEsc = mysqli_real_escape_string($con, $op);
  $where .= " AND h.op = '$opEsc'";
}
if ($tipo === 'LS' || $tipo === 'F') {
  $tipoEsc = mysqli_real_escape_string($con, $tipo);
  $where .= " AND i.tipo_codigo = '$tipoEsc'";
}
if ($codigoBase !== '') {
  $codEsc = mysqli_real_escape_string($con, $codigoBase);
  $where .= " AND SUBSTRING_INDEX(TRIM(i.codigo), '/', 1) LIKE '%$codEsc%'";
}

$sql = "
  SELECT
    h.op,
    MIN(h.fecha) AS fecha_desde,
    MAX(h.fecha) AS fecha_hasta,
    COUNT(DISTINCT h.id) AS partes_op,
    SUM(COALESCE(i.buenas, 0)) AS total_buenas,
    SUM(CASE WHEN i.tipo_codigo = 'LS' THEN COALESCE(i.buenas, 0) ELSE 0 END) AS buenas_ls,
    SUM(CASE WHEN i.tipo_codigo = 'F' THEN COALESCE(i.buenas, 0) ELSE 0 END) AS buenas_f,
    SUM(COALESCE(i.malas, 0)) AS total_malas,
    SUM(CASE WHEN i.tipo_codigo = 'LS' THEN COALESCE(i.malas, 0) ELSE 0 END) AS malas_ls,
    SUM(CASE WHEN i.tipo_codigo = 'F' THEN COALESCE(i.malas, 0) ELSE 0 END) AS malas_f,
    SUM(COALESCE(i.excedente, 0)) AS total_excedente,
    SUM(CASE WHEN i.tipo_codigo = 'LS' THEN COALESCE(i.excedente, 0) ELSE 0 END) AS excedente_ls,
    SUM(CASE WHEN i.tipo_codigo = 'F' THEN COALESCE(i.excedente, 0) ELSE 0 END) AS excedente_f
  FROM op_header h
  INNER JOIN op_item i ON i.header_id = h.id
  WHERE $where
  GROUP BY h.op
  ORDER BY h.op DESC
  LIMIT 1000
";
$res = mysqli_query($con, $sql);
$totalRows = ($res && mysqli_num_rows($res) > 0) ? mysqli_num_rows($res) : 0;

$sumBuenas = 0;
$sumMalas = 0;
$sumExcedente = 0;
$rows = [];
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $sumBuenas += (int)$r['total_buenas'];
    $sumMalas += (int)$r['total_malas'];
    $sumExcedente += (int)$r['total_excedente'];
    $rows[] = $r;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Totales por OP · Empaque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8fafc; }
    .section-card { border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:16px; }
    .summary-row { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; }
    .sum-box { border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; padding:12px; }
    .sum-title { font-size:.82rem; color:#475569; margin-bottom:3px; }
    .sum-value { font-size:1.35rem; font-weight:700; line-height:1.1; }
    .cell-total { font-weight:700; }
    .cell-sub { font-size:.78rem; color:#64748b; white-space:nowrap; }
    @media (max-width: 768px) {
      .summary-row { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Totales por OP</h1>
    <a href="reportes.php" class="btn btn-outline-secondary">← Volver</a>
  </div>

  <div class="section-card mb-3">
    <form method="get">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="form-label">OP (exacta)</label>
          <input type="text" name="op" value="<?=h($op)?>" class="form-control" placeholder="Ej: 1059">
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Tipo de código</label>
          <select name="tipo" class="form-select">
            <option value="">LS + F</option>
            <option value="LS" <?=$tipo==='LS'?'selected':''?>>LS</option>
            <option value="F" <?=$tipo==='F'?'selected':''?>>F</option>
          </select>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Código contiene</label>
          <input type="text" name="codigo" value="<?=h($codigo)?>" class="form-control" placeholder="Ej: 1234">
        </div>
        <div class="col-6 col-lg-1 d-grid">
          <button class="btn btn-primary" type="submit">Buscar</button>
        </div>
        <div class="col-6 col-lg-1 d-grid">
          <a class="btn btn-outline-secondary" href="report_totales_op.php">Limpiar</a>
        </div>
      </div>
    </form>
  </div>

  <div class="section-card mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div><b>Resultados:</b> <?=$totalRows?></div>
      <div class="text-muted small">Agrupado por OP y sumado en buenas, malas y excedente.</div>
    </div>

    <div class="summary-row">
      <div class="sum-box">
        <div class="sum-title">Total general buenas</div>
        <div class="sum-value"><?=number_format($sumBuenas, 0, ',', '.')?></div>
      </div>
      <div class="sum-box">
        <div class="sum-title">Total general malas</div>
        <div class="sum-value"><?=number_format($sumMalas, 0, ',', '.')?></div>
      </div>
      <div class="sum-box">
        <div class="sum-title">Total general excedente</div>
        <div class="sum-value"><?=number_format($sumExcedente, 0, ',', '.')?></div>
      </div>
    </div>
  </div>

  <div class="section-card">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>OP</th>
            <th>Desde</th>
            <th>Hasta</th>
            <th>Partes OP</th>
            <th>Buenas</th>
            <th>Malas</th>
            <th>Excedente</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($rows) > 0): ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $detalleUrl = 'report_totales_op_detalle.php?'.http_build_query([
                'op' => $r['op'],
                'tipo' => $tipo,
                'codigo' => $codigo,
              ]);
            ?>
            <tr>
              <td><code><?=h($r['op'])?></code></td>
              <td><?=h($r['fecha_desde'])?></td>
              <td><?=h($r['fecha_hasta'])?></td>
              <td><?= (int)$r['partes_op'] ?></td>
              <td>
                <div class="cell-total"><?=number_format((int)$r['total_buenas'], 0, ',', '.')?></div>
                <div class="cell-sub">LS: <?=number_format((int)$r['buenas_ls'], 0, ',', '.')?> | F: <?=number_format((int)$r['buenas_f'], 0, ',', '.')?></div>
              </td>
              <td>
                <div class="cell-total"><?=number_format((int)$r['total_malas'], 0, ',', '.')?></div>
                <div class="cell-sub">LS: <?=number_format((int)$r['malas_ls'], 0, ',', '.')?> | F: <?=number_format((int)$r['malas_f'], 0, ',', '.')?></div>
              </td>
              <td>
                <div class="cell-total"><?=number_format((int)$r['total_excedente'], 0, ',', '.')?></div>
                <div class="cell-sub">LS: <?=number_format((int)$r['excedente_ls'], 0, ',', '.')?> | F: <?=number_format((int)$r['excedente_f'], 0, ',', '.')?></div>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?=h($detalleUrl)?>">Ver detalle</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Sin resultados</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>

