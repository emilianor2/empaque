<?php
// report_ops.php
session_start();
include("conexion.php"); // define $con (mysqli)

function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$op            = g('op');
$desde         = g('desde');
$hasta         = g('hasta');
$tipo          = g('tipo');           // '', 'LS', 'F'
$codigo        = g('codigo');         // substring de i.codigo
$desc_trabajo  = g('desc_trabajo');   // substring de i.desc_trabajo

/* ---------- WHERE base (sobre header) ---------- */
$where = "1=1";
if ($op !== '') {
  $op_esc = mysqli_real_escape_string($con, $op);
  $where .= " AND h.op = '$op_esc'";
}
if ($desde !== '') {
  $desde_esc = mysqli_real_escape_string($con, $desde);
  $where .= " AND h.fecha >= STR_TO_DATE('$desde_esc','%Y-%m-%d')";
}
if ($hasta !== '') {
  $hasta_esc = mysqli_real_escape_string($con, $hasta);
  $where .= " AND h.fecha <= STR_TO_DATE('$hasta_esc','%Y-%m-%d')";
}

/* --- Filtro por tipo/código/descripcion SIN tocar el LEFT JOIN principal --- */
if ($tipo !== '' || $codigo !== '' || $desc_trabajo !== '') {
  $conds = [];
  if ($tipo !== '') {
    $tipo_esc = mysqli_real_escape_string($con, $tipo);
    $conds[] = "ix.tipo_codigo = '$tipo_esc'";
  }
  if ($codigo !== '') {
    $codigo_esc = mysqli_real_escape_string($con, $codigo);
    $conds[] = "ix.codigo LIKE '%$codigo_esc%'";
  }
  if ($desc_trabajo !== '') {
    $desc_esc = mysqli_real_escape_string($con, $desc_trabajo);
    $conds[] = "ix.desc_trabajo LIKE '%$desc_esc%'";
  }

  $where .= " AND EXISTS (
    SELECT 1
    FROM op_item ix
    WHERE ix.header_id = h.id
      ".(count($conds) ? " AND ".implode(" AND ", $conds) : "")."
  )";
}

/* ---------- Consulta listado ---------- */
$sql = "
  SELECT
      h.id,
      h.op,
      h.fecha,
      h.turno,
      h.operario,
      h.created_at,
      COUNT(i.id)                  AS cant_lineas,
      SUM(COALESCE(i.excedente,0)) AS exced_total,
      CASE
        WHEN SUM(CASE
                   WHEN i.excedente > 0
                        AND i.caja_excedente IS NOT NULL
                        AND i.caja_excedente <> ''
                        AND i.caja_excedente <> '0'
                   THEN 1 ELSE 0 END) > 0
        THEN 1 ELSE 0
      END AS hay_caja
  FROM op_header h
  LEFT JOIN op_item i ON i.header_id = h.id
  WHERE $where
  GROUP BY h.id
  ORDER BY h.fecha DESC, h.id DESC
  LIMIT 500
";
$res = mysqli_query($con, $sql);

/* ===== Resumen de búsqueda ===== */
$totalRows = ($res && mysqli_num_rows($res)>0) ? mysqli_num_rows($res) : 0;

function badge($txt,$color='light'){
  return '<span class="badge rounded-pill text-bg-'.$color.'">'.$txt.'</span>';
}

$chips = [];
$chips[] = badge("Resultados: {$totalRows}", $totalRows>0?'success':'secondary');
$chips[] = badge('Desde: '.($desde ? h($desde) : '—'));
$chips[] = badge('Hasta: '.($hasta ? h($hasta) : '—'));
if ($op!=='')           $chips[] = badge('OP: '.h($op));
if ($tipo!=='')         $chips[] = badge('Tipo: '.h($tipo));
if ($codigo!=='')       $chips[] = badge('Código: '.h($codigo));
if ($desc_trabajo!=='') $chips[] = badge('Desc: '.h($desc_trabajo));
$chips_html = implode("\n", $chips);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reportes de OPs · Empaque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css">
  <style>
    body { background:#f8fafc; }
    .section-card { border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:16px; }
    .badge-exc { background:#fef9c3; color:#854d0e; border:1px solid #facc15; }
    .summary-bar{border:1px solid #e5e7eb;background:#fff;border-radius:12px;padding:10px 14px}
    .summary-bar .title{font-weight:700;margin-right:8px}
    .summary-bar .badge{border:1px solid #e5e7eb;background:#f8fafc;color:#0f172a}
  </style>
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Reportes de OPs</h1>
    <a href="reportes.php" class="btn btn-outline-secondary">← Volver</a>
  </div>

  <!-- Filtros -->
  <div class="section-card mb-3">
    <form method="get">
      <!-- Fila 1 -->
      <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="form-label">OP (exacta)</label>
          <input type="text" name="op" value="<?=h($op)?>" class="form-control" placeholder="Ej: 1059">
        </div>
        <div class="col-6 col-lg-3">
          <label class="form-label">Desde</label>
          <input type="date" name="desde" value="<?=h($desde)?>" class="form-control">
        </div>
        <div class="col-6 col-lg-3">
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" value="<?=h($hasta)?>" class="form-control">
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label d-none d-lg-block">&nbsp;</label>
          <div class="d-grid">
            <a class="btn btn-outline-secondary" href="report_ops.php">Limpiar filtros</a>
          </div>
        </div>
      </div>

      <!-- Fila 2 -->
      <div class="row g-3 align-items-end mt-1">
        <div class="col-12 col-lg-3">
          <label class="form-label">Tipo de código</label>
          <select name="tipo" class="form-select">
            <option value="">LS + F</option>
            <option value="LS" <?=$tipo==='LS'?'selected':''?>>LS</option>
            <option value="F"  <?=$tipo==='F'?'selected':''?>>F</option>
          </select>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Código contiene</label>
          <input type="text" name="codigo" value="<?=h($codigo)?>" class="form-control" placeholder="Ej: 1234">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label">Descripción contiene</label>
          <input type="text" name="desc_trabajo" value="<?=h($desc_trabajo)?>" class="form-control"
                 placeholder="">
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label d-none d-lg-block">&nbsp;</label>
          <div class="d-grid">
            <button class="btn btn-primary" type="submit">Buscar</button>
          </div>
        </div>
      </div>

      <div class="row mt-2">
        <div class="col-12">
          <small class="text-muted">
            Podés filtrar por OP, fechas, tipo (LS/F), código o descripción de trabajo.
            Si alguna línea coincide, se muestra la OP completa; los totales son generales de la OP.
          </small>
        </div>
      </div>
    </form>
  </div>

  <!-- Resumen -->
  <div class="summary-bar mb-3 d-flex flex-wrap align-items-center gap-2">
    <span class="title">Resumen:</span>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <?=$chips_html?>
    </div>
    <div class="ms-auto small text-muted">
      <?= $totalRows>0 ? "Mostrando {$totalRows} OP".($totalRows===1?'':'s').($desde||$hasta?' en el rango seleccionado.':'.') : "Sin resultados en este rango." ?>
    </div>
  </div>

  <!-- Tabla listado -->
  <div class="section-card">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>OP</th>
            <th>Fecha (OP)</th>
            <th>Turno</th>
            <th>Operario</th>
            <th>Creada</th>
            <th>Líneas</th>
            <th>Excedente total</th>
            <th>Cajas informadas</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($res && mysqli_num_rows($res)>0): ?>
            <?php while ($r = mysqli_fetch_assoc($res)): ?>
              <tr>
                <td><?=$r['id']?></td>
                <td><code><?=h($r['op'])?></code></td>
                <td><?=h($r['fecha'])?></td>
                <td><?= (int)$r['turno']===1?'Mañana':((int)$r['turno']===2?'Tarde':'Noche') ?></td>
                <td><?= (int)$r['operario'] ?></td>
                <td><?= h($r['created_at']) ?></td>
                <td><?= (int)$r['cant_lineas'] ?></td>
                <td>
                  <?php $exc=(int)$r['exced_total']; ?>
                  <?= $exc>0 ? '<span class="badge badge-exc">'.$exc.'</span>' : '<span class="badge text-bg-secondary">0</span>' ?>
                </td>
                <td>
                  <?= ((int)$r['hay_caja']===1) ? '<span class="badge text-bg-success">Sí</span>' : '<span class="badge text-bg-danger">No</span>' ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="report_op.php?id=<?=$r['id']?>">Ver detalle</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="10" class="text-center text-muted py-4">Sin resultados</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
