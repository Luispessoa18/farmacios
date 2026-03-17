<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function getOrders() {
    if (!file_exists('data/orders.json')) return [];
    return json_decode(file_get_contents('data/orders.json'), true) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orders = getOrders();
    foreach($orders as &$o) {
        if($o['id'] == $_POST['order_id']) {
            $o['status'] = $_POST['new_status'];
            break;
        }
    }
    file_put_contents('data/orders.json', json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header('Location: orders.php');
    exit;
}

$orders = array_reverse(getOrders());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>FarmaCium - Pedidos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen">

    <nav class="bg-indigo-700 text-white p-4 flex justify-between items-center shadow-lg">
        <h1 class="text-xl font-bold uppercase italic tracking-tighter"><i class="fas fa-clipboard-list mr-2"></i>Gestão de Pedidos</h1>
        <a href="dashboard.php" class="bg-white/10 px-4 py-2 rounded-lg text-sm font-bold">Voltar</a>
    </nav>

    <div class="container mx-auto p-6">
        <div class="grid grid-cols-1 gap-6">
            <?php foreach ($orders as $order): ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col md:flex-row">
                    
                    <div class="p-6 md:w-1/4 bg-slate-50 border-r flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ID Pedido</span>
                            <h2 class="text-2xl font-black text-slate-800">#<?= $order['id'] ?></h2>
                            <p class="text-xs text-slate-500 font-bold"><?= date('d/m/Y H:i', strtotime($order['date'])) ?></p>
                        </div>
                        <div class="mt-4">
                            <span class="text-[10px] font-black text-indigo-400 uppercase">Origem</span>
                            <p class="font-bold text-indigo-700 uppercase italic text-xs"><?= $order['origin'] ?? 'PDV Local' ?></p>
                        </div>
                    </div>

                    <div class="p-6 flex-1">
                        <h3 class="font-black text-slate-700 uppercase text-xs mb-4 pb-2 border-b">Detalhamento dos Itens</h3>
                        <div class="space-y-2">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-slate-600 font-medium"><?= $item['qty'] ?>x <?= htmlspecialchars($item['name']) ?></span>
                                    <span class="font-bold text-slate-800 italic">R$ <?= number_format($item['price'] * $item['qty'], 2, ',', '.') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-6 pt-4 border-t flex flex-wrap gap-4 items-center justify-between">
                            <div class="flex gap-8">
                                <div>
                                    <span class="text-[10px] font-black text-slate-400 uppercase">Pagamento</span>
                                    <div class="text-[11px] font-black text-slate-700">
                                        <?php if(isset($order['payments'])): ?>
                                            <?php if($order['payments']['dinheiro'] > 0) echo "Dinheiro: R$ ".number_format($order['payments']['dinheiro'],2,',','.')."<br>"; ?>
                                            <?php if($order['payments']['pix'] > 0) echo "Pix: R$ ".number_format($order['payments']['pix'],2,',','.')."<br>"; ?>
                                            <?php if($order['payments']['debito'] > 0) echo "Débito: R$ ".number_format($order['payments']['debito'],2,',','.')."<br>"; ?>
                                            <?php if($order['payments']['credito'] > 0) echo "Crédito: R$ ".number_format($order['payments']['credito'],2,',','.')."<br>"; ?>
                                            <?php if(($order['change'] ?? 0) > 0) echo "<span class='text-amber-600'>Troco: R$ ".number_format($order['change'],2,',','.')."</span>"; ?>
                                        <?php else: ?>
                                            <?= $order['payment'] ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-[10px] font-black text-slate-400 uppercase">Total Geral</span>
                                    <p class="text-xl font-black text-indigo-600 tracking-tighter">R$ <?= number_format($order['total'], 2, ',', '.') ?></p>
                                </div>
                            </div>

                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <select name="new_status" onchange="this.form.submit()" class="bg-indigo-50 text-indigo-700 font-black text-[10px] uppercase py-3 px-4 rounded-xl border-none focus:ring-2 focus:ring-indigo-500">
                                    <option <?= ($order['status'] ?? '') == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option <?= ($order['status'] ?? '') == 'Preparando' ? 'selected' : '' ?>>Preparando</option>
                                    <option <?= ($order['status'] ?? '') == 'Saiu para Entrega' ? 'selected' : '' ?>>Saiu para Entrega</option>
                                    <option <?= ($order['status'] ?? '') == 'Entregue' ? 'selected' : '' ?>>Entregue</option>
                                    <option <?= ($order['status'] ?? '') == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
                                    <option <?= ($order['status'] ?? '') == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </div>
                        
                        <?php if(isset($order['address'])): ?>
                            <div class="mt-4 bg-slate-100 p-4 rounded-2xl text-[10px] font-bold text-slate-500 uppercase">
                                <i class="fas fa-map-marker-alt mr-2 text-indigo-500"></i> Entrega: <?= $order['address'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>