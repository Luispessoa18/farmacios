<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

function getJsonData($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    if (empty($content)) return [];
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function saveJsonData($file, $data) {
    file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$products = getJsonData('data/products.json');
$inventory = getJsonData('data/inventory.json');

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Adicionar ao Carrinho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $pid = (int)$_POST['product_id'];
    $currentStock = 0;
    foreach($inventory as $inv) if($inv['product_id'] == $pid) $currentStock = $inv['quantity'];

    if ($currentStock > 0) {
        foreach ($products as $p) {
            if ($p['id'] === $pid) {
                $found = false;
                foreach ($_SESSION['cart'] as &$item) {
                    if ($item['id'] === $pid) {
                        if($item['qty'] < $currentStock) $item['qty']++;
                        $found = true; break; 
                    }
                }
                if (!$found) $_SESSION['cart'][] = ['id' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 'qty' => 1];
                break;
            }
        }
    }
}

// Finalizar Venda com Split Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish_sale'])) {
    $orders = getJsonData('data/orders.json');
    $inventory = getJsonData('data/inventory.json');
    
    $payments = [
        'dinheiro' => (float)($_POST['pay_cash'] ?? 0),
        'pix' => (float)($_POST['pay_pix'] ?? 0),
        'debito' => (float)($_POST['pay_debito'] ?? 0),
        'credito' => (float)($_POST['pay_credito'] ?? 0)
    ];

    $orderId = count($orders) > 0 ? max(array_column($orders, 'id')) + 1 : 1000;
    $newOrder = [
        'id' => $orderId,
        'date' => date('Y-m-d H:i:s'),
        'items' => $_SESSION['cart'],
        'total' => (float)$_POST['total_raw'],
        'payments' => $payments,
        'change' => (float)($_POST['pay_change'] ?? 0),
        'operator' => $_SESSION['user_id'],
        'origin' => 'PDV Balcão',
        'status' => 'Concluído'
    ];

    $orders[] = $newOrder;
    saveJsonData('data/orders.json', $orders);

    foreach ($_SESSION['cart'] as $item) {
        foreach ($inventory as &$inv) {
            if ($inv['product_id'] == $item['id']) {
                $inv['quantity'] = max(0, $inv['quantity'] - $item['qty']);
                break;
            }
        }
    }
    saveJsonData('data/inventory.json', $inventory);
    
    $_SESSION['cart'] = [];
    header('Location: dashboard.php?sale=ok');
    exit;
}

$subTotal = array_reduce($_SESSION['cart'], function($acc, $i) { return $acc + ($i['price'] * $i['qty']); }, 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>FarmaCium - PDV Profissional</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-indigo-950 h-screen flex flex-col overflow-hidden">
    
    <header class="bg-indigo-700 text-white p-4 flex justify-between items-center shadow-2xl">
        <div class="flex items-center gap-3">
             <i class="fas fa-cash-register text-2xl"></i>
             <h1 class="font-black italic tracking-tighter text-xl">FARMACIUM PDV</h1>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-xs font-bold opacity-50 uppercase">Operador: <?= $_SESSION['user_id'] ?></span>
            <a href="dashboard.php" class="text-xs font-bold bg-red-500 hover:bg-red-600 px-4 py-2 rounded-xl transition">Sair do Caixa</a>
        </div>
    </header>

    <main class="flex-1 flex overflow-hidden p-4 gap-4">
        <!-- Grade de Produtos -->
        <div class="w-2/3 grid grid-cols-3 lg:grid-cols-4 gap-3 overflow-y-auto content-start pr-2">
            <?php foreach ($products as $p): 
                $stk = 0;
                foreach($inventory as $inv) if($inv['product_id'] == $p['id']) $stk = $inv['quantity'];
            ?>
                <button onclick="addToCart(<?= $p['id'] ?>)" class="bg-white p-4 rounded-3xl shadow-lg flex flex-col items-center text-center hover:scale-[1.02] transition <?= $stk <= 0 ? 'opacity-50 grayscale cursor-not-allowed' : '' ?>">
                    <img src="<?= $p['photo'] ?>" class="w-20 h-20 object-contain mb-2 rounded-xl">
                    <p class="font-bold text-xs text-slate-800 line-clamp-2 h-8"><?= $p['name'] ?></p>
                    <p class="text-indigo-600 font-black mt-1">R$ <?= number_format($p['price'], 2, ',', '.') ?></p>
                    <span class="text-[9px] font-bold text-slate-400 uppercase">Estoque: <?= $stk ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Cupom Fiscal Lateral -->
        <div class="w-1/3 bg-white rounded-[2.5rem] p-6 flex flex-col shadow-2xl">
            <div class="flex items-center justify-between mb-4 border-b pb-4">
                <h2 class="font-black text-slate-400 uppercase text-[10px] tracking-widest">Cupom de Venda</h2>
                <span class="text-[10px] font-bold text-slate-300"><?= date('H:i') ?></span>
            </div>

            <div class="flex-1 overflow-y-auto space-y-3 mb-4 pr-2">
                <?php if(empty($_SESSION['cart'])): ?>
                    <div class="h-full flex flex-col items-center justify-center text-slate-200">
                        <i class="fas fa-shopping-basket text-5xl mb-3"></i>
                        <p class="text-xs font-black uppercase tracking-widest">Caixa Disponível</p>
                    </div>
                <?php endif; ?>

                <?php foreach($_SESSION['cart'] as $i): ?>
                    <div class="flex justify-between items-start text-sm border-b border-slate-50 pb-2">
                        <div class="flex-1">
                            <p class="font-bold text-slate-800"><?= htmlspecialchars($i['name']) ?></p>
                            <p class="text-[10px] text-slate-400 uppercase"><?= $i['qty'] ?> un x R$ <?= number_format($i['price'], 2, ',', '.') ?></p>
                        </div>
                        <span class="font-black text-slate-900 ml-4">R$ <?= number_format($i['price']*$i['qty'], 2, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="bg-slate-900 p-6 rounded-3xl mb-4 text-white">
                <p class="text-[10px] font-bold text-slate-500 uppercase leading-none mb-1">Subtotal da Venda</p>
                <p class="text-4xl font-black tracking-tighter">R$ <span id="display_total"><?= number_format($subTotal, 2, ',', '.') ?></span></p>
            </div>

            <button onclick="openPaymentModal()" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?> class="w-full bg-green-500 hover:bg-green-600 disabled:bg-slate-100 disabled:text-slate-300 text-white py-6 rounded-2xl font-black text-xl shadow-xl transition transform active:scale-[0.95]">
                PAGAR (F9)
            </button>
        </div>
    </main>

    <!-- Modal de Pagamento Multi-Meios -->
    <div id="paymentModal" class="fixed inset-0 bg-indigo-950/90 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[3rem] w-full max-w-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row">
            
            <!-- Resumo Esquerda -->
            <div class="bg-slate-50 p-8 md:w-2/5 border-r border-slate-100">
                <h3 class="font-black text-slate-400 uppercase text-xs mb-6">Resumo Final</h3>
                <div class="space-y-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Itens:</span>
                        <span class="font-bold"><?= count($_SESSION['cart']) ?></span>
                    </div>
                    <div class="pt-4 border-t border-slate-200">
                        <p class="text-[10px] font-black text-slate-400 uppercase">Total a Pagar</p>
                        <p class="text-3xl font-black text-indigo-900">R$ <?= number_format($subTotal, 2, ',', '.') ?></p>
                    </div>
                    <div class="pt-4 bg-green-50 p-4 rounded-2xl">
                        <p class="text-[10px] font-black text-green-600 uppercase">Faltando</p>
                        <p id="calc_remaining" class="text-2xl font-black text-green-700">R$ <?= number_format($subTotal, 2, ',', '.') ?></p>
                    </div>
                    <div id="change_box" class="pt-4 bg-amber-50 p-4 rounded-2xl hidden">
                        <p class="text-[10px] font-black text-amber-600 uppercase">Troco</p>
                        <p id="calc_change" class="text-2xl font-black text-amber-700">R$ 0,00</p>
                    </div>
                </div>
            </div>

            <!-- Inputs Direita -->
            <form method="POST" id="form_pdv" class="p-8 flex-1 space-y-4">
                <input type="hidden" name="total_raw" value="<?= $subTotal ?>">
                <input type="hidden" name="pay_change" id="input_change" value="0">
                <input type="hidden" name="finish_sale" value="1">

                <h3 class="font-black text-slate-800 text-lg mb-2">Formas de Pagamento</h3>
                
                <div class="grid grid-cols-1 gap-3">
                    <div class="relative">
                        <i class="fas fa-money-bill-wave absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="number" step="0.01" name="pay_cash" id="p_cash" oninput="calculatePayments()" placeholder="Dinheiro" class="w-full pl-12 pr-4 py-3 bg-slate-100 rounded-xl font-bold border-2 border-transparent focus:border-indigo-500 outline-none">
                    </div>
                    <div class="relative">
                        <i class="fab fa-pix absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="number" step="0.01" name="pay_pix" id="p_pix" oninput="calculatePayments()" placeholder="Pix" class="w-full pl-12 pr-4 py-3 bg-slate-100 rounded-xl font-bold border-2 border-transparent focus:border-indigo-500 outline-none">
                    </div>
                    <div class="relative">
                        <i class="fas fa-credit-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="number" step="0.01" name="pay_debito" id="p_debito" oninput="calculatePayments()" placeholder="Cartão Débito" class="w-full pl-12 pr-4 py-3 bg-slate-100 rounded-xl font-bold border-2 border-transparent focus:border-indigo-500 outline-none">
                    </div>
                    <div class="relative">
                        <i class="fas fa-credit-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="number" step="0.01" name="pay_credito" id="p_credito" oninput="calculatePayments()" placeholder="Cartão Crédito" class="w-full pl-12 pr-4 py-3 bg-slate-100 rounded-xl font-bold border-2 border-transparent focus:border-indigo-500 outline-none">
                    </div>
                </div>

                <div class="flex gap-2 pt-4">
                    <button type="button" onclick="closePaymentModal()" class="flex-1 bg-slate-100 text-slate-400 font-bold py-4 rounded-2xl uppercase text-xs">Cancelar</button>
                    <button type="submit" id="btn_finalize" disabled class="flex-[2] bg-indigo-600 disabled:bg-slate-200 text-white font-black py-4 rounded-2xl uppercase text-xs shadow-lg shadow-indigo-100">Concluir Venda</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const grandTotal = <?= $subTotal ?>;

        function addToCart(id) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = `<input type="hidden" name="product_id" value="${id}"><input type="hidden" name="add_to_cart" value="1">`;
            document.body.appendChild(f); 
            f.submit();
        }

        function openPaymentModal() { 
            document.getElementById('paymentModal').classList.remove('hidden'); 
            document.getElementById('p_cash').focus();
        }
        function closePaymentModal() { document.getElementById('paymentModal').classList.add('hidden'); }

        function calculatePayments() {
            const cash = parseFloat(document.getElementById('p_cash').value || 0);
            const pix = parseFloat(document.getElementById('p_pix').value || 0);
            const deb = parseFloat(document.getElementById('p_debito').value || 0);
            const cre = parseFloat(document.getElementById('p_credito').value || 0);
            
            const totalPaid = cash + pix + deb + cre;
            const remaining = grandTotal - totalPaid;
            
            const remDisplay = document.getElementById('calc_remaining');
            const changeBox = document.getElementById('change_box');
            const changeDisplay = document.getElementById('calc_change');
            const btn = document.getElementById('btn_finalize');

            if (remaining > 0) {
                remDisplay.innerText = "R$ " + remaining.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                changeBox.classList.add('hidden');
                btn.disabled = true;
            } else {
                remDisplay.innerText = "R$ 0,00";
                const troco = Math.abs(remaining);
                if (troco > 0) {
                    changeBox.classList.remove('hidden');
                    changeDisplay.innerText = "R$ " + troco.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    document.getElementById('input_change').value = troco;
                } else {
                    changeBox.classList.add('hidden');
                }
                btn.disabled = false;
            }
        }

        document.addEventListener('keydown', (e) => {
            if(e.key === 'F9' && grandTotal > 0) openPaymentModal();
            if(e.key === 'Escape') closePaymentModal();
        });
    </script>
</body>
</html>