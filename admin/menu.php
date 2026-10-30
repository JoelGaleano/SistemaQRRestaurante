<?php
require 'conexion.php';
require 'layout.php';

$id_restaurante = (int) $_SESSION['restaurante_id'];

// Obtener categorías existentes de este restaurante
$stmtCat = $pdo->prepare("SELECT id, nombre FROM categorias WHERE id_restaurante = ? ORDER BY id ASC");
$stmtCat->execute([$id_restaurante]);
$categorias = $stmtCat->fetchAll();

// Obtener productos existentes de este restaurante
$stmtProd = $pdo->prepare("
    SELECT p.*, c.nombre as categoria_nombre 
    FROM productos p 
    JOIN categorias c ON p.id_categoria = c.id 
    WHERE c.id_restaurante = ?
    ORDER BY c.id ASC, p.id ASC
");
$stmtProd->execute([$id_restaurante]);
$productos = $stmtProd->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php renderAdminHead("Gestión de Carta y Stock"); ?>
    <style>
        .forms-grid {
            display: grid;
            grid-template-columns: 0.85fr 1.15fr;
            gap: 24px;
            margin-bottom: 34px;
            align-items: start;
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .img-preview {
            width: 54px;
            height: 54px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid var(--border-glass);
        }
        .img-placeholder {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px dashed var(--border-glass);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .btn-stock {
            padding: 8px 16px;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 800;
            font-size: 0.8rem;
            min-width: 118px;
            text-align: center;
            transition: all 0.2s ease;
        }
        .stock-on {
            background: var(--neon-soft);
            color: var(--neon);
            border: 1px solid var(--neon);
            box-shadow: 0 0 14px rgba(57, 255, 20, 0.2);
        }
        .stock-on:hover {
            background: var(--neon);
            color: #050505;
        }
        .stock-off {
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid rgba(255, 77, 79, 0.45);
        }
        .stock-off:hover {
            background: var(--danger);
            color: #fff;
        }
        .cat-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-glass);
        }
        @media (max-width: 900px) {
            .forms-grid { grid-template-columns: 1fr; }
            .form-row-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php renderAdminNav('menu'); ?>

    <main class="admin-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Gestión de <span>Carta y Stock</span></h1>
                <p>Organiza tus categorías, carga nuevos platos o bebidas y pausa/activa su disponibilidad en tiempo real.</p>
            </div>
            <span class="badge badge-neon"><?= count($productos) ?> productos en carta</span>
        </div>

        <div class="forms-grid">
            <!-- 1. Agregar Categoría -->
            <div class="glass-card">
                <h2>1. Nueva <span>Categoría</span></h2>
                <form action="procesar_categoria.php" method="POST">
                    <div class="form-group">
                        <label>Nombre de la Categoría</label>
                        <input type="text" name="nombre_categoria" placeholder="Ej: 🍺 Cervezas Tiradas, 🍔 Burgers..." required>
                    </div>
                    <button type="submit" class="btn-neon" style="width:100%;">+ Guardar Categoría</button>
                </form>

                <?php if (!empty($categorias)): ?>
                    <div class="cat-chips">
                        <?php foreach ($categorias as $cat): ?>
                            <span class="badge badge-neon"><?= htmlspecialchars($cat['nombre']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. Agregar Producto -->
            <div class="glass-card">
                <h2>2. Nuevo <span>Producto o Bebida</span></h2>
                <form action="procesar_producto.php" method="POST" enctype="multipart/form-data">
                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Categoría</label>
                            <select name="id_categoria" required>
                                <option value="">Selecciona una categoría...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Precio ($)</label>
                            <input type="number" step="0.01" name="precio" placeholder="Ej: 4800.00" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nombre del Plato / Bebida</label>
                        <input type="text" name="nombre" placeholder="Ej: Session IPA Citra Gold (Pinta 500ml)" required>
                    </div>

                    <div class="form-group">
                        <label>Descripción / Ingredientes</label>
                        <textarea name="descripcion" placeholder="Describe los ingredientes, notas de cata o acompañamientos..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Fotografía del Producto (Opcional · JPG, PNG o WEBP máx. 2MB)</label>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <button type="submit" class="btn-neon" style="width:100%;">+ Publicar en la Carta Digital</button>
                </form>
            </div>
        </div>

        <!-- Tabla de Productos -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Producto y Descripción</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Stock en Carta QR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:var(--text-muted); padding:32px;">
                                No tienes productos cargados todavía. Crea una categoría y agrega tu primer plato arriba.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $prod): ?>
                            <tr>
                                <td style="width:72px;">
                                    <?php if (!empty($prod['url_imagen'])): ?>
                                        <img src="../<?= htmlspecialchars($prod['url_imagen']) ?>" class="img-preview" alt="Foto">
                                    <?php else: ?>
                                        <div class="img-placeholder">🍽️</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size:0.98rem; color:#fff; display:block;"><?= htmlspecialchars($prod['nombre']) ?></strong>
                                    <?php if (!empty($prod['descripcion'])): ?>
                                        <small style="color:var(--text-muted); font-size:0.83rem;"><?= htmlspecialchars($prod['descripcion']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-neon"><?= htmlspecialchars($prod['categoria_nombre']) ?></span>
                                </td>
                                <td style="font-weight:800; color:var(--neon); font-size:1rem;">
                                    $<?= number_format((float)$prod['precio'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <?php if ($prod['disponible']): ?>
                                        <button class="btn-stock stock-on" id="btn-prod-<?= $prod['id'] ?>" onclick="cambiarStock(<?= $prod['id'] ?>, 0)">● Disponible</button>
                                    <?php else: ?>
                                        <button class="btn-stock stock-off" id="btn-prod-<?= $prod['id'] ?>" onclick="cambiarStock(<?= $prod['id'] ?>, 1)">✕ Agotado</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function cambiarStock(idProducto, nuevoEstado) {
            const btn = document.getElementById('btn-prod-' + idProducto);
            btn.innerText = "Actualizando...";

            fetch('toggle_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id_producto: idProducto,
                    disponible: nuevoEstado
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (nuevoEstado === 1) {
                        btn.className = "btn-stock stock-on";
                        btn.innerText = "● Disponible";
                        btn.setAttribute('onclick', `cambiarStock(${idProducto}, 0)`);
                    } else {
                        btn.className = "btn-stock stock-off";
                        btn.innerText = "✕ Agotado";
                        btn.setAttribute('onclick', `cambiarStock(${idProducto}, 1)`);
                    }
                } else {
                    alert("Error al actualizar el stock: " + (data.message || ""));
                }
            })
            .catch(() => alert("Error de red al actualizar stock."));
        }
    </script>
</body>
</html>
