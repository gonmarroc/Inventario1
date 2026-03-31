<?php
// Frontend + API ligero en un solo archivo.
// Guarda datos en inventario.json en el mismo directorio.

declare(strict_types=1);

$dataFile = __DIR__ . '/inventario.json';

function send_json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function read_items(string $file): array {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return [];
    }

    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode($content ?: '[]', true);
    return is_array($data) ? $data : [];
}

function write_items(string $file, array $items): bool {
    $fp = fopen($file, 'c+');
    if (!$fp) {
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }

    ftruncate($fp, 0);
    rewind($fp);
    $ok = fwrite($fp, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

function input_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function normalize_item(array $item): array {
    return [
        'id' => (string)($item['id'] ?? bin2hex(random_bytes(8))),
        'nombre' => trim((string)($item['nombre'] ?? '')),
        'categoria' => trim((string)($item['categoria'] ?? 'Otros')),
        'stock' => max(0, (int)($item['stock'] ?? 0)),
        'minimo' => max(0, (int)($item['minimo'] ?? 0)),
        'precio' => round((float)($item['precio'] ?? 0), 2),
        'descripcion' => trim((string)($item['descripcion'] ?? '')),
        'imagen' => trim((string)($item['imagen'] ?? '')),
        'historial' => is_array($item['historial'] ?? null) ? array_values($item['historial']) : [],
        'updatedAt' => (string)($item['updatedAt'] ?? gmdate('c')),
    ];
}

$api = isset($_GET['api']) ? (string)$_GET['api'] : null;
if ($api) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $items = read_items($dataFile);

    if ($api === 'items' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        send_json($items);
    }

    if ($api === 'items' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = input_json();
        $item = normalize_item($payload);
        if ($item['nombre'] === '') {
            send_json(['error' => 'El nombre es obligatorio.'], 422);
        }
        array_unshift($items, $item);
        if (!write_items($dataFile, $items)) {
            send_json(['error' => 'No se pudo guardar el inventario.'], 500);
        }
        send_json($item, 201);
    }

    if ($api === 'items' && $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $payload = input_json();
        $id = (string)($payload['id'] ?? '');
        if ($id === '') {
            send_json(['error' => 'ID obligatorio.'], 422);
        }
        $found = false;
        foreach ($items as &$existing) {
            if (($existing['id'] ?? '') === $id) {
                $payload['historial'] = $existing['historial'] ?? [];
                $payload['updatedAt'] = gmdate('c');
                $existing = normalize_item(array_merge($existing, $payload));
                $found = true;
                break;
            }
        }
        unset($existing);
        if (!$found) {
            send_json(['error' => 'Producto no encontrado.'], 404);
        }
        if (!write_items($dataFile, $items)) {
            send_json(['error' => 'No se pudo actualizar el inventario.'], 500);
        }
        send_json(['ok' => true]);
    }

    if ($api === 'move' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = input_json();
        $id = (string)($payload['id'] ?? '');
        $type = (string)($payload['type'] ?? '');
        $qty = max(0, (int)($payload['qty'] ?? 0));
        $note = trim((string)($payload['note'] ?? ''));

        if ($id === '' || !in_array($type, ['entrada', 'salida'], true) || $qty <= 0) {
            send_json(['error' => 'Movimiento inválido.'], 422);
        }

        $found = false;
        foreach ($items as &$item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            $found = true;
            $stock = (int)($item['stock'] ?? 0);
            if ($type === 'salida' && $qty > $stock) {
                send_json(['error' => 'No puedes descontar más unidades de las que hay.'], 422);
            }
            $stock = $type === 'entrada' ? $stock + $qty : $stock - $qty;
            $item['stock'] = $stock;
            $item['updatedAt'] = gmdate('c');
            $item['historial'][] = [
                'id' => bin2hex(random_bytes(8)),
                'tipo' => $type,
                'cantidad' => $qty,
                'nota' => $note,
                'fecha' => gmdate('c'),
                'stockResultante' => $stock,
            ];
            break;
        }
        unset($item);

        if (!$found) {
            send_json(['error' => 'Producto no encontrado.'], 404);
        }
        if (!write_items($dataFile, $items)) {
            send_json(['error' => 'No se pudo guardar el movimiento.'], 500);
        }
        send_json(['ok' => true]);
    }

    if ($api === 'items' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $payload = input_json();
        $id = (string)($payload['id'] ?? '');
        if ($id === '') {
            send_json(['error' => 'ID obligatorio.'], 422);
        }
        $before = count($items);
        $items = array_values(array_filter($items, fn($it) => ($it['id'] ?? '') !== $id));
        if ($before === count($items)) {
            send_json(['error' => 'Producto no encontrado.'], 404);
        }
        if (!write_items($dataFile, $items)) {
            send_json(['error' => 'No se pudo borrar el producto.'], 500);
        }
        send_json(['ok' => true]);
    }

    send_json(['error' => 'Ruta no encontrada.'], 404);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventario Fundación</title>
  <style>
    :root {
      --bg: #f4f7fb;
      --card: #fff;
      --text: #1f2937;
      --muted: #6b7280;
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --success: #15803d;
      --danger: #dc2626;
      --warning: #d97706;
      --border: #e5e7eb;
      --shadow: 0 10px 30px rgba(15, 23, 42, .08);
      --radius: 18px;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: var(--bg); color: var(--text); }
    .container { max-width: 1280px; margin: 0 auto; padding: 24px; }
    .header { display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:24px; }
    h1 { margin:0; font-size:2rem; }
    .subtitle { color:var(--muted); margin-top:6px; }
    .btn { border:0; border-radius:12px; padding:12px 16px; cursor:pointer; font-weight:700; }
    .btn-primary { background:var(--primary); color:#fff; }
    .btn-primary:hover { background:var(--primary-dark); }
    .btn-outline { background:#fff; border:1px solid var(--border); }
    .btn-success { background:var(--success); color:#fff; }
    .btn-warning { background:var(--warning); color:#fff; }
    .btn-danger { background:var(--danger); color:#fff; }
    .btn-small { padding:8px 12px; font-size:.92rem; }
    .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
    .card { background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); border:1px solid rgba(229,231,235,.8); }
    .stat-card { padding:20px; }
    .stat-label { color:var(--muted); font-size:.95rem; margin-bottom:10px; }
    .stat-value { font-size:2rem; font-weight:700; }
    .toolbar { display:grid; grid-template-columns:1.4fr 1fr 1fr auto; gap:12px; padding:18px; margin-bottom:20px; align-items:center; }
    input, select, textarea { width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:12px; font:inherit; background:#fff; }
    textarea { min-height:110px; resize:vertical; }
    .grid { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; }
    .product-card { overflow:hidden; }
    .product-image { width:100%; height:220px; object-fit:cover; display:block; background:#e5e7eb; }
    .product-body { padding:18px; }
    .product-top { display:flex; justify-content:space-between; gap:12px; align-items:start; }
    .product-title { margin:0 0 6px; font-size:1.2rem; }
    .muted { color:var(--muted); }
    .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:.8rem; font-weight:700; background:#eff6ff; color:#1d4ed8; }
    .badge-low { background:#fff7ed; color:#c2410c; }
    .stock { font-size:1.8rem; font-weight:700; margin:16px 0 6px; }
    .product-actions,.move-row { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
    .move-controls { margin-top:16px; padding-top:14px; border-top:1px solid var(--border); }
    .move-controls label,.form-group label { display:block; margin-bottom:6px; font-weight:600; font-size:.95rem; }
    .history { margin-top:16px; border-top:1px solid var(--border); padding-top:14px; }
    .history-item { padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:.95rem; }
    .empty,.loading { text-align:center; padding:48px 20px; color:var(--muted); }
    .modal { position:fixed; inset:0; background:rgba(15,23,42,.5); display:none; align-items:center; justify-content:center; padding:20px; z-index:50; }
    .modal.open { display:flex; }
    .modal-content { width:100%; max-width:760px; max-height:92vh; overflow:auto; background:#fff; border-radius:24px; box-shadow:var(--shadow); padding:24px; }
    .modal-header { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:18px; }
    .form-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
    .full { grid-column:1 / -1; }
    .preview { width:100%; max-height:250px; object-fit:cover; border-radius:16px; border:1px solid var(--border); margin-top:10px; display:none; }
    .notice { margin: 0 0 18px; padding: 12px 14px; border-radius: 12px; display:none; }
    .notice.show { display:block; }
    .notice.ok { background:#ecfdf5; color:#166534; }
    .notice.error { background:#fef2f2; color:#991b1b; }
    @media (max-width:1024px) { .stats{grid-template-columns:repeat(2,1fr)} .grid{grid-template-columns:repeat(2,1fr)} .toolbar{grid-template-columns:1fr 1fr} }
    @media (max-width:720px) { .stats,.grid,.form-grid,.toolbar{grid-template-columns:1fr} .product-top,.header{flex-direction:column; align-items:stretch} .container{padding:16px} h1{font-size:1.6rem} }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div>
        <h1>Inventario de Merchandising</h1>
        <div class="subtitle">Ahora sí guarda en el servidor con PHP + JSON.</div>
      </div>
      <button class="btn btn-primary" onclick="openModal()">+ Nuevo producto</button>
    </div>

    <div id="notice" class="notice"></div>

    <div class="stats">
      <div class="card stat-card"><div class="stat-label">Productos</div><div class="stat-value" id="statProducts">0</div></div>
      <div class="card stat-card"><div class="stat-label">Unidades totales</div><div class="stat-value" id="statUnits">0</div></div>
      <div class="card stat-card"><div class="stat-label">Valor estimado</div><div class="stat-value" id="statValue">0 €</div></div>
      <div class="card stat-card"><div class="stat-label">Con stock bajo</div><div class="stat-value" id="statLow">0</div></div>
    </div>

    <div class="card toolbar">
      <input id="searchInput" type="text" placeholder="Buscar por nombre, categoría o descripción">
      <select id="categoryFilter"></select>
      <select id="sortFilter">
        <option value="updated">Más recientes</option>
        <option value="name">Nombre A-Z</option>
        <option value="stockAsc">Stock ascendente</option>
        <option value="stockDesc">Stock descendente</option>
      </select>
      <button class="btn btn-outline" onclick="loadItems()">Recargar</button>
    </div>

    <div id="productGrid" class="grid"><div class="card loading" style="grid-column:1/-1">Cargando inventario...</div></div>
  </div>

  <div class="modal" id="productModal">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 id="modalTitle" style="margin:0">Nuevo producto</h2>
          <div class="muted">Añade foto, stock y datos básicos del artículo.</div>
        </div>
        <button class="btn btn-outline btn-small" onclick="closeModal()">Cerrar</button>
      </div>

      <form id="productForm">
        <div class="form-grid">
          <div class="form-group"><label>Nombre</label><input type="text" id="nombre" required></div>
          <div class="form-group"><label>Categoría</label><input type="text" id="categoria" placeholder="Ej. Ropa, Hogar, Accesorios" required></div>
          <div class="form-group"><label>Stock inicial</label><input type="number" id="stock" min="0" value="0" required></div>
          <div class="form-group"><label>Stock mínimo</label><input type="number" id="minimo" min="0" value="0" required></div>
          <div class="form-group"><label>Precio (€)</label><input type="number" id="precio" min="0" step="0.01" value="0"></div>
          <div class="form-group"><label>URL de imagen</label><input type="text" id="imagen" placeholder="https://..." oninput="updatePreview()"></div>
          <div class="form-group full"><label>O subir imagen</label><input type="file" id="imagenFile" accept="image/*" onchange="handleImageUpload(event)"></div>
          <div class="form-group full"><label>Descripción</label><textarea id="descripcion" placeholder="Notas internas, uso, proveedor, campaña..."></textarea></div>
        </div>
        <img id="imagePreview" class="preview" alt="Vista previa">
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
          <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar producto</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let items = [];
    let editingId = null;

    function showNotice(message, type = 'ok') {
      const el = document.getElementById('notice');
      el.textContent = message;
      el.className = `notice show ${type}`;
      setTimeout(() => {
        el.className = 'notice';
      }, 3000);
    }

    async function api(url, options = {}) {
      const response = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.error || 'Error inesperado');
      }
      return data;
    }

    function formatCurrency(value) {
      return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
    }

    function formatDate(date) {
      return new Date(date).toLocaleString('es-ES');
    }

    async function loadItems() {
      try {
        items = await api('?api=items');
        renderProducts();
      } catch (err) {
        document.getElementById('productGrid').innerHTML = '<div class="card empty" style="grid-column:1/-1">No se pudo cargar el inventario.</div>';
        showNotice(err.message, 'error');
      }
    }

    function updateStats() {
      const totalUnits = items.reduce((sum, item) => sum + Number(item.stock || 0), 0);
      const totalValue = items.reduce((sum, item) => sum + Number(item.stock || 0) * Number(item.precio || 0), 0);
      const lowStock = items.filter(item => Number(item.stock) <= Number(item.minimo)).length;
      document.getElementById('statProducts').textContent = items.length;
      document.getElementById('statUnits').textContent = totalUnits;
      document.getElementById('statValue').textContent = formatCurrency(totalValue);
      document.getElementById('statLow').textContent = lowStock;
    }

    function getFilters() {
      return {
        search: document.getElementById('searchInput').value.toLowerCase().trim(),
        category: document.getElementById('categoryFilter').value,
        sort: document.getElementById('sortFilter').value
      };
    }

    function renderCategories() {
      const select = document.getElementById('categoryFilter');
      const categories = ['Todas', ...new Set(items.map(i => i.categoria).filter(Boolean))];
      const current = select.value || 'Todas';
      select.innerHTML = categories.map(cat => `<option value="${cat}">${cat}</option>`).join('');
      select.value = categories.includes(current) ? current : 'Todas';
    }

    function filteredItems() {
      const { search, category, sort } = getFilters();
      let list = items.filter(item => {
        const text = `${item.nombre} ${item.categoria} ${item.descripcion}`.toLowerCase();
        return (!search || text.includes(search)) && (category === 'Todas' || item.categoria === category);
      });
      list.sort((a, b) => {
        if (sort === 'name') return a.nombre.localeCompare(b.nombre);
        if (sort === 'stockAsc') return Number(a.stock) - Number(b.stock);
        if (sort === 'stockDesc') return Number(b.stock) - Number(a.stock);
        return new Date(b.updatedAt) - new Date(a.updatedAt);
      });
      return list;
    }

    function renderProducts() {
      updateStats();
      renderCategories();
      const grid = document.getElementById('productGrid');
      const list = filteredItems();
      if (!list.length) {
        grid.innerHTML = '<div class="card empty" style="grid-column:1/-1">No hay productos que coincidan con la búsqueda.</div>';
        return;
      }
      grid.innerHTML = list.map(item => {
        const low = Number(item.stock) <= Number(item.minimo);
        const history = (item.historial || []).slice(-4).reverse().map(h => `
          <div class="history-item">
            <strong>${h.tipo === 'entrada' ? 'Entrada' : 'Salida'}</strong> de ${h.cantidad} uds · ${formatDate(h.fecha)}<br>
            <span class="muted">${escapeHtml(h.nota || 'Sin nota')} · Stock resultante: ${h.stockResultante}</span>
          </div>
        `).join('');
        return `
          <div class="card product-card">
            <img class="product-image" src="${item.imagen || 'https://via.placeholder.com/800x500?text=Sin+imagen'}" alt="${escapeHtml(item.nombre)}" onerror="this.src='https://via.placeholder.com/800x500?text=Sin+imagen'">
            <div class="product-body">
              <div class="product-top">
                <div>
                  <h3 class="product-title">${escapeHtml(item.nombre)}</h3>
                  <div class="muted">${escapeHtml(item.categoria)}</div>
                </div>
                <span class="badge ${low ? 'badge-low' : ''}">${low ? 'Stock bajo' : 'Disponible'}</span>
              </div>
              <div class="stock">${item.stock}</div>
              <div class="muted">Mínimo recomendado: ${item.minimo} · Precio: ${formatCurrency(item.precio)}</div>
              <p style="margin-top:12px;">${escapeHtml(item.descripcion || 'Sin descripción')}</p>
              <div class="move-controls">
                <label>Cantidad a mover</label>
                <input type="number" id="qty-${item.id}" min="1" value="1">
                <label style="margin-top:10px;">Nota del movimiento</label>
                <input type="text" id="note-${item.id}" placeholder="Ej. Venta en evento, reposición, donación...">
                <div class="move-row">
                  <button class="btn btn-success btn-small" onclick="changeStock('${item.id}','entrada')">+ Añadir</button>
                  <button class="btn btn-warning btn-small" onclick="changeStock('${item.id}','salida')">- Descontar</button>
                </div>
              </div>
              <div class="product-actions">
                <button class="btn btn-outline btn-small" onclick="editItem('${item.id}')">Editar</button>
                <button class="btn btn-danger btn-small" onclick="deleteItem('${item.id}')">Eliminar</button>
              </div>
              <div class="history">
                <strong>Últimos movimientos</strong>
                ${history || '<div class="history-item muted">Todavía no hay movimientos registrados.</div>'}
              </div>
            </div>
          </div>`;
      }).join('');
    }

    function openModal(id = null) {
      editingId = id;
      document.getElementById('productModal').classList.add('open');
      document.getElementById('productForm').reset();
      document.getElementById('imagePreview').style.display = 'none';
      document.getElementById('modalTitle').textContent = id ? 'Editar producto' : 'Nuevo producto';
      if (id) {
        const item = items.find(i => i.id === id);
        if (!item) return;
        nombre.value = item.nombre || '';
        categoria.value = item.categoria || '';
        stock.value = item.stock || 0;
        minimo.value = item.minimo || 0;
        precio.value = item.precio || 0;
        imagen.value = item.imagen || '';
        descripcion.value = item.descripcion || '';
        updatePreview();
      }
    }

    function closeModal() {
      document.getElementById('productModal').classList.remove('open');
      editingId = null;
    }

    function updatePreview() {
      const img = document.getElementById('imagePreview');
      const url = document.getElementById('imagen').value.trim();
      if (url) {
        img.src = url;
        img.style.display = 'block';
      } else {
        img.style.display = 'none';
      }
    }

    function handleImageUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = e => {
        document.getElementById('imagen').value = e.target.result;
        updatePreview();
      };
      reader.readAsDataURL(file);
    }

    document.getElementById('productForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const payload = {
        id: editingId,
        nombre: nombre.value.trim(),
        categoria: categoria.value.trim(),
        stock: Number(stock.value || 0),
        minimo: Number(minimo.value || 0),
        precio: Number(precio.value || 0),
        imagen: imagen.value.trim(),
        descripcion: descripcion.value.trim(),
        updatedAt: new Date().toISOString()
      };
      try {
        if (editingId) {
          await api('?api=items', { method: 'PUT', body: JSON.stringify(payload) });
          showNotice('Producto actualizado.');
        } else {
          await api('?api=items', { method: 'POST', body: JSON.stringify(payload) });
          showNotice('Producto creado.');
        }
        closeModal();
        await loadItems();
      } catch (err) {
        showNotice(err.message, 'error');
      }
    });

    async function changeStock(id, type) {
      const qty = Number(document.getElementById(`qty-${id}`).value || 0);
      const note = document.getElementById(`note-${id}`).value.trim();
      try {
        await api('?api=move', { method: 'POST', body: JSON.stringify({ id, type, qty, note }) });
        showNotice(type === 'entrada' ? 'Stock añadido.' : 'Stock descontado.');
        await loadItems();
      } catch (err) {
        showNotice(err.message, 'error');
      }
    }

    async function deleteItem(id) {
      if (!confirm('¿Eliminar este producto del inventario?')) return;
      try {
        await api('?api=items', { method: 'DELETE', body: JSON.stringify({ id }) });
        showNotice('Producto eliminado.');
        await loadItems();
      } catch (err) {
        showNotice(err.message, 'error');
      }
    }

    function editItem(id) { openModal(id); }
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text || ''; return div.innerHTML; }
    document.getElementById('searchInput').addEventListener('input', renderProducts);
    document.getElementById('categoryFilter').addEventListener('change', renderProducts);
    document.getElementById('sortFilter').addEventListener('change', renderProducts);
    document.getElementById('productModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
    loadItems();
  </script>
</body>
</html>
