<?php

// report_op.php

session_start();

include("conexion.php"); // $con (mysqli)



$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) { die("ID inválido"); }



// Header

$sqlH = "SELECT id, op, fecha, turno, operario, created_at FROM op_header WHERE id=$id";

$rh   = mysqli_query($con, $sqlH);

$h    = $rh ? mysqli_fetch_assoc($rh) : null;

if (!$h) { die("OP no encontrada"); }



// Items

$sqlI = "

  SELECT i.id, i.tipo_codigo, i.codigo, i.maquina, i.cantidad_total,

         i.buenas, i.malas, i.excedente,

         i.desc_trabajo, i.obs, i.caja_excedente,

         i.created_at

  FROM op_item i

  WHERE i.header_id = $id

  ORDER BY i.id ASC

";

$ri = mysqli_query($con, $sqlI);

?>

<!doctype html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <title>Detalle OP <?=$h['op']?> · Empaque</title>

    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/styles.css">

    <style>
    .badge-exc {
        background: #fef9c3;
        color: #854d0e;
        border: 1px solid #facc15;
    }

    .chip-ls {
        background: #e0f2fe;
        color: #075985;
        border: 1px solid #7dd3fc;
    }

    .chip-f {
        background: #fae8ff;
        color: #86198f;
        border: 1px solid #f0abfc;
    }
    </style>

</head>

<body class="bg-light">

    <div class="container py-4">



        <div class="d-flex justify-content-between align-items-center mb-3">

            <h1 class="h5 m-0">Detalle OP <code><?=$h['op']?></code></h1>

            <div class="d-flex gap-2">

                <a href="report_ops.php" class="btn btn-outline-secondary">← Volver a reportes</a>

                <a href="index.php" class="btn btn-secondary">Inicio</a>

            </div>

        </div>



        <div class="section-card compact mb-3">

            <div class="row g-2">

                <div class="col-6 col-md-3"><b>Fecha OP:</b> <?=htmlspecialchars($h['fecha'])?></div>

                <div class="col-6 col-md-3"><b>Turno:</b>
                    <?= (int)$h['turno']===1?'Mañana':((int)$h['turno']===2?'Tarde':'Noche') ?></div>

                <div class="col-6 col-md-3"><b>Operario:</b> <?= (int)$h['operario'] ?></div>

                <div class="col-6 col-md-3"><b>Creada:</b> <?= htmlspecialchars($h['created_at']) ?></div>

            </div>

        </div>



        <div class="section-card compact">

            <div class="table-responsive">

                <table class="table table-sm align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Tipo</th>

                            <th>Código</th>

                            <th>Máquina</th>

                            <th>Total</th>

                            <th class="text-success">Buenas</th>

                            <th class="text-danger">Malas</th>

                            <th class="text-warning">Excedente</th>

                            <th>Caja excedente</th>

                            <th>Descripción</th>

                            <th>Observaciones</th>

                            <th>Creada</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($ri && mysqli_num_rows($ri)>0): $n=0; ?>

                        <?php while($row = mysqli_fetch_assoc($ri)): $n++; ?>

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

                            <td><?=htmlspecialchars($row['maquina'])?></td>

                            <td><?= (int)$row['cantidad_total'] ?></td>

                            <td class="text-success"><?= (int)$row['buenas'] ?></td>

                            <td class="text-danger"><?= (int)$row['malas'] ?></td>

                            <td>

                                <?php $exc = (int)$row['excedente']; ?>

                                <?php if ($exc>0): ?>

                                <span class="badge badge-exc"><?=$exc?></span>

                                <?php else: ?>

                                0

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php

                  $caja = trim((string)$row['caja_excedente']);

                  echo $caja!=='' ? htmlspecialchars($caja) : '<span class="text-muted">—</span>';

                ?>

                            </td>

                            <td><?=htmlspecialchars($row['desc_trabajo'])?></td>

                            <td><?=htmlspecialchars($row['obs'])?></td>

                            <td><?=htmlspecialchars($row['created_at'])?></td>

                        </tr>

                        <?php endwhile; ?>

                        <?php else: ?>

                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">Sin líneas para esta OP</td>
                        </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>



    </div>

</body>

</html>