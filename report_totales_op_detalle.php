<?php
session_start();
include("conexion.php"); // define $con (mysqli)

function g($k, $d = '') { return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$op     = g('op');
$tipo   = g('tipo');   // '', 'LS', 'F'
$codigo = g('codigo'); // por base (antes de '/')

if ($op === '') {
  header("Location: report_totales_op.php");
  exit;
}

$codigoBase = $codigo;
if ($codigoBase !== '') {
  $parts = explode('/', $codigoBase, 2);
  $codigoBase = trim($parts[0]);
}

$opEsc = mysqli_real_escape_string($con, $op);
$where = "h.op = '$opEsc'";

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
    h.id AS header_id,
    h.fecha,
    h.turno,
    h.operario,
    i.id AS item_id,
    i.tipo_codigo,
    i.codigo,
    i.maquina,
    i.desc_trabajo,
    i.buenas,
    i.malas,
    i.excedente,
    i.caja_excedente
  FROM op_header h
  INNER JOIN op_item i ON i.header_id = h.id
  WHERE $where
  ORDER BY h.fecha DESC, h.id DESC, i.id DESC
";
$res = mysqli_query($con, $sql);

$rows = [];
$totBuenas = 0;
$totMalas = 0;
$totExcedente = 0;
$headerSeen = [];

if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $totBuenas += (int)$r['buenas'];
    $totMalas += (int)$r['malas'];
    $totExcedente += (int)$r['excedente'];
    $headerSeen[(int)$r['header_id']] = true;
    $rows[] = $r;
  }
}

$totalLineas = count($rows);
$totalCargas = count($headerSeen); // cabeceras OP

$volverUrl = 'report_totales_op.php?'.http_build_query([
  'op' => $op,
  'tipo' => $tipo,
  'codigo' => $codigo,
]);

function turnoTxt($t) {
  $n = (int)$t;
  if ($n === 1) return 'Manana';
  if ($n === 2) return 'Tarde';
  if ($n === 3) return 'Noche';
  return '-';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Detalle de Cargas por OP · Empaque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8fafc; }
    .section-card { border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:16px; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Detalle de cargas · OP <code><?=h($op)?></code></h1>
    <a href="<?=h($volverUrl)?>" class="btn btn-outline-secondary">← Volver</a>
  </div>

  <div class="section-card mb-3">
    <div class="row g-2">
      <div class="col-12 col-md-3"><b>Cargas OP:</b> <?=$totalCargas?></div>
      <div class="col-12 col-md-3"><b>Lineas:</b> <?=$totalLineas?></div>
      <div class="col-12 col-md-2"><b>Buenas:</b> <?=number_format($totBuenas, 0, ',', '.')?></div>
      <div class="col-12 col-md-2"><b>Malas:</b> <?=number_format($totMalas, 0, ',', '.')?></div>
      <div class="col-12 col-md-2"><b>Excedente:</b> <?=number_format($totExcedente, 0, ',', '.')?></div>
    </div>
  </div>

  <div class="section-card">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Fecha</th>
            <th>Turno</th>
            <th>Operario</th>
            <th>Tipo</th>
            <th>Codigo</th>
            <th>Maquina</th>
            <th>Descripcion</th>
            <th>Buenas</th>
            <th>Malas</th>
            <th>Excedente</th>
            <th>Caja excedente</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($totalLineas > 0): ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?=h($r['fecha'])?></td>
                <td><?=h(turnoTxt($r['turno']))?></td>
                <td><?= (int)$r['operario'] ?></td>
                <td>
                  <?php if ($r['tipo_codigo'] === 'LS'): ?>
                    <span class="badge text-bg-primary">LS</span>
                  <?php else: ?>
                    <span class="badge text-bg-warning">F</span>
                  <?php endif; ?>
                </td>
                <td><code><?=h($r['codigo'])?></code></td>
                <td><?=h($r['maquina'])?></td>
                <td><?=h($r['desc_trabajo'])?></td>
                <td><b><?=number_format((int)$r['buenas'], 0, ',', '.')?></b></td>
                <td><?=number_format((int)$r['malas'], 0, ',', '.')?></td>
                <td><?=number_format((int)$r['excedente'], 0, ',', '.')?></td>
                <td><?=h($r['caja_excedente'])?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="11" class="text-center text-muted py-4">Sin resultados</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>

