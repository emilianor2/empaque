<?php
session_start();
include("conexion.php"); // define $con (mysqli)

function g($k, $d = '') { return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function turnoName($t) { return $t == 1 ? 'Manana' : ($t == 2 ? 'Tarde' : ($t == 3 ? 'Noche' : '-')); }

$fecha_desde = g('fecha_desde');
$fecha_hasta = g('fecha_hasta');
$caja        = g('caja');
$caja_desde  = g('caja_desde');
$caja_hasta  = g('caja_hasta');

$buscar = isset($_GET['buscar']);
$errores = [];
$avisos = [];
$rows = [];
$totalExcedente = 0;

if ($buscar) {
  $where = [];

  // Solo lineas con excedente cargado y caja de excedente informada.
  $where[] = "COALESCE(i.excedente,0) > 0";
  $where[] = "NULLIF(TRIM(i.caja_excedente),'') IS NOT NULL";
  $where[] = "TRIM(i.caja_excedente) <> '0'";

  if ($fecha_desde !== '') {
    $fd = mysqli_real_escape_string($con, $fecha_desde);
    $where[] = "h.fecha >= STR_TO_DATE('$fd','%Y-%m-%d')";
  }
  if ($fecha_hasta !== '') {
    $fh = mysqli_real_escape_string($con, $fecha_hasta);
    $where[] = "h.fecha <= STR_TO_DATE('$fh','%Y-%m-%d')";
  }

  $filtroCajaAplicado = false;
  if ($caja !== '') {
    if (!ctype_digit($caja)) {
      $errores[] = "La caja exacta debe ser numerica.";
    } else {
      $cajaEsc = mysqli_real_escape_string($con, $caja);
      $where[] = "TRIM(i.caja_excedente) = '$cajaEsc'";
      $filtroCajaAplicado = true;
      if ($caja_desde !== '' || $caja_hasta !== '') {
        $avisos[] = "Se uso caja exacta. El rango fue ignorado.";
      }
    }
  } else {
    if ($caja_desde !== '' || $caja_hasta !== '') {
      if ($caja_desde === '' || $caja_hasta === '') {
        $errores[] = "Para buscar por rango, completa Caja desde y Caja hasta.";
      } elseif (!ctype_digit($caja_desde) || !ctype_digit($caja_hasta)) {
        $errores[] = "El rango de cajas debe ser numerico.";
      } else {
        $desdeN = (int)$caja_desde;
        $hastaN = (int)$caja_hasta;
        if ($desdeN > $hastaN) {
          $tmp = $desdeN;
          $desdeN = $hastaN;
          $hastaN = $tmp;
          $avisos[] = "El rango estaba invertido. Se aplico de $desdeN a $hastaN.";
        }
        $where[] = "TRIM(i.caja_excedente) REGEXP '^[0-9]+$'";
        $where[] = "CAST(TRIM(i.caja_excedente) AS UNSIGNED) BETWEEN $desdeN AND $hastaN";
        $filtroCajaAplicado = true;
      }
    }
  }

  if (!$filtroCajaAplicado) {
    $errores[] = "Ingresa una caja exacta o un rango de cajas.";
  }

  if (empty($errores)) {
    $sql = "
      SELECT
        h.id AS header_id,
        h.fecha,
        h.op,
        h.turno,
        h.operario,
        h.created_at,
        i.id AS item_id,
        i.maquina,
        i.tipo_codigo,
        i.codigo,
        COALESCE(NULLIF(TRIM(i.desc_trabajo),''),'(Sin descripcion)') AS desc_trabajo,
        COALESCE(i.buenas,0) AS buenas,
        COALESCE(i.malas,0) AS malas,
        COALESCE(i.excedente,0) AS excedente,
        TRIM(i.caja_excedente) AS caja_excedente,
        COALESCE(NULLIF(TRIM(i.obs),''),'-') AS obs
      FROM op_item i
      JOIN op_header h ON h.id = i.header_id
      WHERE ".implode(" AND ", $where)."
      ORDER BY
        CAST(TRIM(i.caja_excedente) AS UNSIGNED) ASC,
        h.fecha DESC,
        h.id DESC,
        i.id DESC
      LIMIT 2000
    ";

    $res = mysqli_query($con, $sql);
    if ($res) {
      while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
        $totalExcedente += (int)$r['excedente'];
      }
    } else {
      $errores[] = "Error al consultar excedentes: " . mysqli_error($con);
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte de excedentes por caja</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css">
  <style>
    .badge-exc { background:#fef9c3; color:#854d0e; border:1px solid #facc15; }
    .summary-bar{border:1px solid #e5e7eb;background:#fff;border-radius:12px;padding:10px 14px}
    .summary-bar .badge{border:1px solid #e5e7eb;background:#f8fafc;color:#0f172a}
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Reporte de excedentes por caja</h1>
    <a href="reportes.php" class="btn btn-outline-secondary">Volver</a>
  </div>

  <div class="section-card mb-3">
    <form method="get">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label">Fecha desde (opcional)</label>
          <input type="date" name="fecha_desde" value="<?=h($fecha_desde)?>" class="form-control">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Fecha hasta (opcional)</label>
          <input type="date" name="fecha_hasta" value="<?=h($fecha_hasta)?>" class="form-control">
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">Caja exacta</label>
          <input type="text" name="caja" value="<?=h($caja)?>" class="form-control" inputmode="numeric" placeholder="Ej: 124">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Caja desde</label>
          <input type="text" name="caja_desde" value="<?=h($caja_desde)?>" class="form-control" inputmode="numeric" placeholder="Ej: 100">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Caja hasta</label>
          <input type="text" name="caja_hasta" value="<?=h($caja_hasta)?>" class="form-control" inputmode="numeric" placeholder="Ej: 150">
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary" type="submit" name="buscar" value="1">Buscar</button>
        <a class="btn btn-outline-secondary" href="report_excedentes_caja.php">Limpiar filtros</a>
      </div>

      <small class="text-muted d-block mt-2">
        Este reporte solo muestra movimientos con excedente mayor a 0 y con caja de excedente informada.
      </small>
    </form>
  </div>

  <?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
      <?=h(implode(' ', $errores))?>
    </div>
  <?php endif; ?>

  <?php if (!empty($avisos)): ?>
    <div class="alert alert-warning">
      <?=h(implode(' ', $avisos))?>
    </div>
  <?php endif; ?>

  <?php if ($buscar && empty($errores)): ?>
    <div class="summary-bar mb-3 d-flex flex-wrap align-items-center gap-2">
      <span class="badge rounded-pill">Filas: <?=count($rows)?></span>
      <span class="badge rounded-pill">Excedente total: <?=number_format($totalExcedente, 0, ',', '.')?></span>
      <?php if ($caja !== ''): ?>
        <span class="badge rounded-pill">Caja: <?=h($caja)?></span>
      <?php else: ?>
        <span class="badge rounded-pill">Rango: <?=h($caja_desde)?> a <?=h($caja_hasta)?></span>
      <?php endif; ?>
    </div>

    <div class="section-card">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Fecha</th>
              <th>OP</th>
              <th>Turno</th>
              <th>Operario</th>
              <th>Maquina</th>
              <th>Tipo</th>
              <th>Codigo</th>
              <th>Descripcion</th>
              <th>Buenas</th>
              <th>Malas</th>
              <th>Excedente</th>
              <th>Caja excedente</th>
              <th>Obs</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php if (count($rows) > 0): ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?=h($r['fecha'])?></td>
                <td><code><?=h($r['op'])?></code></td>
                <td><?=turnoName((int)$r['turno'])?></td>
                <td><?=h($r['operario'])?></td>
                <td><?=h($r['maquina'])?></td>
                <td><?=h($r['tipo_codigo'])?></td>
                <td><code><?=h($r['codigo'])?></code></td>
                <td><?=h($r['desc_trabajo'])?></td>
                <td><?= (int)$r['buenas'] ?></td>
                <td><?= (int)$r['malas'] ?></td>
                <td><span class="badge badge-exc"><?= (int)$r['excedente'] ?></span></td>
                <td><b><?=h($r['caja_excedente'])?></b></td>
                <td><?=h($r['obs'])?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="report_op.php?id=<?=$r['header_id']?>">Ver OP</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="14" class="text-center text-muted py-4">Sin resultados para los filtros seleccionados.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
