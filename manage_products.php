<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

function getJsonData($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?? [];
}
function saveJsonData($file, $data) {
    file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$products = getJsonData('data/products.json');
$inventory = getJsonData('data/inventory.json');
// Removido "Saúde", mantido o foco
$categories = ["Medicamentos", "Beleza", "Vitaminas", "Mãe e Bebê", "Higiene Pessoal", "Farmácia Popular"];

$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($products as $p) {
        if ($p['id'] === $editId) {
            $editProduct = $p;
            foreach($inventory as $inv) {
                if($inv['product_id'] == $editId) $editProduct['stock'] = $inv['quantity'];
            }
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_product']) || isset($_POST['update_product']))) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = $_POST['name'];
    $price = (float)$_POST['price'];
    $category = $_POST['category'];
    $subcategory = $_POST['subcategory'] ?? '';
    $prescription = isset($_POST['requires_prescription']);
    $photo = $_POST['photo_url'] ?: 'https://placehold.co/400x400?text=Produto';
    $description = $_POST['description'] ?? '';
    $stock = (int)$_POST['stock'];

    if ($id > 0) {
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                $p['name'] = $name;
                $p['price'] = $price;
                $p['category'] = $category;
                $p['subcategory'] = $subcategory;
                $p['requires_prescription'] = $prescription;
                $p['photo'] = $photo;
                $p['description'] = $description;
                break;
            }
        }
        foreach ($inventory as &$inv) {
            if ($inv['product_id'] === $id) {
                $inv['quantity'] = $stock;
                break;
            }
        }
    } else {
        $newId = count($products) > 0 ? max(array_column($products, 'id')) + 1 : 1;
        $products[] = [
            'id' => $newId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'category' => $category,
            'subcategory' => $subcategory,
            'requires_prescription' => $prescription,
            'photo' => $photo
        ];
        $inventory[] = ['product_id' => $newId, 'quantity' => $stock];
    }
    
    saveJsonData('data/products.json', $products);
    saveJsonData('data/inventory.json', $inventory);
    header('Location: manage_products.php'); exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $products = array_filter($products, fn($p) => $p['id'] !== $id);
    $inventory = array_filter($inventory, fn($i) => $i['product_id'] !== $id);
    saveJsonData('data/products.json', $products);
    saveJsonData('data/inventory.json', $inventory);
    header('Location: manage_products.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>FarmaCium - Gestão de Produtos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 p-6">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <a href="dashboard.php" class="text-xs font-bold text-slate-400 uppercase tracking-widest"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
            <h1 class="text-xl font-black text-slate-800 uppercase italic">Estoque & Catálogo</h1>
        </div>
        
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm mb-8 border border-slate-100">
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if($editProduct): ?> <input type="hidden" name="id" value="<?= $editProduct['id'] ?>"> <?php endif; ?>
                
                <div class="md:col-span-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Nome do Produto</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>" placeholder="Ex: Dipirona 500mg" required class="w-full bg-slate-100 p-4 rounded-2xl border-none font-bold">
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Preço (R$)</label>
                    <input type="number" step="0.01" name="price" value="<?= $editProduct['price'] ?? '' ?>" placeholder="0.00" required class="w-full bg-slate-100 p-4 rounded-2xl border-none font-bold">
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Categoria Principal</label>
                    <select name="category" class="w-full bg-slate-100 p-4 rounded-2xl border-none font-bold text-slate-600">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= ($editProduct['category'] ?? '') == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Subcategoria</label>
                    <input type="text" name="subcategory" value="<?= htmlspecialchars($editProduct['subcategory'] ?? '') ?>" placeholder="Ex: Analgésicos" class="w-full bg-slate-100 p-4 rounded-2xl border-none font-bold">
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Estoque Inicial/Atual</label>
                    <input type="number" name="stock" value="<?= $editProduct['stock'] ?? '' ?>" placeholder="0" required class="w-full bg-slate-100 p-4 rounded-2xl border-none font-bold">
                </div>

                <div class="md:col-span-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Descrição do Produto</label>
                    <textarea name="description" rows="2" class="w-full bg-slate-100 p-4 rounded-2xl border-none font-medium text-sm"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">URL da Foto</label>
                    <input type="text" name="photo_url" value="<?= htmlspecialchars($editProduct['photo'] ?? '') ?>" placeholder="https://..." class="w-full bg-slate-100 p-4 rounded-2xl border-none font-bold text-xs">
                </div>

                <div class="flex items-center">
                    <label class="flex items-center gap-2 p-4 bg-orange-50 rounded-2xl text-orange-700 font-bold text-[10px] uppercase cursor-pointer select-none">
                        <input type="checkbox" name="requires_prescription" <?= ($editProduct['requires_prescription'] ?? false) ? 'checked' : '' ?>> Exigir Receita
                    </label>
                </div>

                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" name="<?= $editProduct ? 'update_product' : 'add_product' ?>" class="flex-1 bg-indigo-600 text-white font-black py-4 rounded-2xl uppercase tracking-widest shadow-lg hover:bg-indigo-700 transition">
                        <?= $editProduct ? 'Salvar Alterações' : 'Cadastrar Produto' ?>
                    </button>
                    <?php if($editProduct): ?>
                    <a href="manage_products.php" class="bg-slate-200 text-slate-500 font-black py-4 px-6 rounded-2xl uppercase tracking-widest hover:bg-slate-300 transition">Voltar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm overflow-hidden border border-slate-100">
            <table class="w-full text-left">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-black text-slate-400 uppercase">
                        <th class="p-6">Produto / Subcategoria</th>
                        <th class="p-6">Categoria</th>
                        <th class="p-6">Estoque</th>
                        <th class="p-6">Preço</th>
                        <th class="p-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($products as $p): 
                        $stock = 0;
                        foreach($inventory as $inv) if($inv['product_id'] == $p['id']) $stock = $inv['quantity'];
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-6 flex items-center gap-4">
                            <img src="<?= $p['photo'] ?>" class="w-12 h-12 rounded-xl bg-slate-100 object-contain p-1">
                            <div>
                                <p class="font-bold text-slate-800 leading-tight"><?= htmlspecialchars($p['name']) ?></p>
                                <p class="text-[10px] font-black text-indigo-400 uppercase tracking-tighter"><?= htmlspecialchars($p['subcategory'] ?? '-') ?></p>
                            </div>
                        </td>
                        <td class="p-6">
                            <span class="text-[10px] font-black px-3 py-1 bg-slate-100 rounded-full text-slate-500"><?= $p['category'] ?></span>
                        </td>
                        <td class="p-6">
                            <span class="font-black <?= $stock <= 5 ? 'text-red-500' : 'text-slate-400' ?> text-sm"><?= $stock ?></span>
                        </td>
                        <td class="p-6 font-black text-indigo-600 text-sm">R$ <?= number_format($p['price'], 2, ',', '.') ?></td>
                        <td class="p-6 text-right space-x-3">
                            <a href="?edit=<?= $p['id'] ?>" class="text-indigo-400 hover:text-indigo-600 transition"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Excluir este produto?')" class="text-red-300 hover:text-red-500 transition"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>