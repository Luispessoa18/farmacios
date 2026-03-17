<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
$settings = getJsonData('data/settings.json');
$allOrders = getJsonData('data/orders.json');

if (!isset($_SESSION['my_order_ids'])) $_SESSION['my_order_ids'] = [];
if (!isset($_SESSION['menu_cart'])) $_SESSION['menu_cart'] = [];

function getStock($id, $inventory) {
    foreach ($inventory as $inv) {
        if ($inv['product_id'] == $id) return $inv['quantity'];
    }
    return 0;
}

// AJAX - Cálculo de Frete
if (isset($_GET['calc_freight'])) {
    $uLat = (float)($_GET['lat'] ?? 0);
    $uLng = (float)($_GET['lng'] ?? 0);
    $uBairro = $_GET['bairro'] ?? '';
    $sLat = (float)($settings['store_lat'] ?? 0);
    $sLng = (float)($settings['store_lng'] ?? 0);
    
    $freeArr = array_map('trim', explode(',', strtolower($settings['free_neighborhoods'] ?? '')));
    if (in_array(strtolower($uBairro), $freeArr)) {
        echo json_encode(['cost' => 0, 'msg' => 'Frete Grátis!']); exit;
    }

    $theta = $uLng - $sLng;
    $dist = sin(deg2rad($uLat)) * sin(deg2rad($sLat)) + cos(deg2rad($uLat)) * cos(deg2rad($sLat)) * cos(deg2rad($theta));
    $dist = acos($dist); $dist = rad2deg($dist);
    $distKm = $dist * 60 * 1.1515 * 1.609344;

    if ($distKm > (float)($settings['max_radius'] ?? 10)) {
        echo json_encode(['error' => 'Fora do raio de entrega.']); exit;
    }
    $cost = $distKm * (float)($settings['price_per_km'] ?? 2.5);
    echo json_encode(['cost' => round($cost, 2), 'dist' => round($distKm, 1)]); exit;
}

// Adicionar ao carrinho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = (int)$_POST['product_id'];
    $qty = (int)($_POST['quantity'] ?? 1);
    $stockAvailable = getStock($productId, $inventory);
    
    if ($stockAvailable >= $qty) {
        foreach ($products as $p) {
            if ($p['id'] === $productId) {
                $found = false;
                foreach ($_SESSION['menu_cart'] as &$item) {
                    if ($item['id'] === $productId) {
                        $item['qty'] = min($stockAvailable, $item['qty'] + $qty);
                        $found = true; break;
                    }
                }
                if (!$found) {
                    $_SESSION['menu_cart'][] = [
                        'id' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 
                        'qty' => $qty, 'requires_prescription' => $p['requires_prescription'] ?? false,
                        'photo' => $p['photo']
                    ];
                }
                break;
            }
        }
    }
    header('Location: menu.php?show_added_modal=1'); exit;
}

// Finalizar Pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish_order'])) {
    if (!empty($_SESSION['menu_cart'])) {
        $orders = getJsonData('data/orders.json');
        $inventory = getJsonData('data/inventory.json');
        
        $orderId = count($orders) > 0 ? max(array_column($orders, 'id')) + 1 : 1000;
        $subtotal = (float)($_POST['subtotal_value'] ?? 0);
        $freight = (float)($_POST['freight_value'] ?? 0);

        $newOrder = [
            'id' => $orderId,
            'date' => date('Y-m-d H:i:s'),
            'items' => $_SESSION['menu_cart'],
            'total' => $subtotal + $freight,
            'payment' => $_POST['payment_method'] ?? 'Dinheiro',
            'origin' => 'Loja Online',
            'status' => 'Pendente',
            'address' => $_POST['full_address'] ?? 'Não informado'
        ];

        $orders[] = $newOrder;
        saveJsonData('data/orders.json', $orders);

        foreach ($_SESSION['menu_cart'] as $cartItem) {
            foreach ($inventory as &$inv) {
                if ($inv['product_id'] == $cartItem['id']) {
                    $inv['quantity'] = max(0, $inv['quantity'] - $cartItem['qty']);
                }
            }
        }
        saveJsonData('data/inventory.json', $inventory);
        
        $_SESSION['my_order_ids'][] = $orderId;
        $_SESSION['menu_cart'] = [];
        header('Location: menu.php?view=orders&success_id='.$orderId); exit;
    }
}

$cartCount = array_sum(array_column($_SESSION['menu_cart'], 'qty'));
$subTotalValue = array_reduce($_SESSION['menu_cart'], function($acc, $i) { return $acc + ($i['price'] * $i['qty']); }, 0);
$categories = ["Medicamentos", "Beleza", "Vitaminas", "Mãe e Bebê", "Higiene Pessoal", "Farmácia Popular"];
$currentView = $_GET['view'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($settings['store_name'] ?? 'FarmaCium') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { background-color: #F8FAFC; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-tap-highlight-color: transparent; }
        .bg-medical { background: linear-gradient(135deg, #e11d48 0%, #be123c 100%); }
        .text-medical { color: #e11d48; }
        .product-card { transition: all 0.2s ease; overflow: hidden; border-radius: 2rem; }
        .product-card:active { transform: scale(0.98); }
        .step-hidden { display: none; }
        .animate-slide-up { animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .hide-scroll::-webkit-scrollbar { display: none; }
        .cat-btn.active { background-color: #e11d48 !important; color: white !important; border-color: #e11d48 !important; }
        .subcat-btn.active { background-color: #1e293b !important; color: white !important; }
    </style>
</head>
<body class="pb-24 text-slate-900">

    <!-- Header -->
    <header class="bg-white px-4 pt-4 pb-2 sticky top-0 z-40 border-b border-slate-100">
        <div class="flex items-center gap-4 mb-4">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="searchInput" oninput="filterProducts()" placeholder="Busque por nome ou tipo..." class="w-full bg-slate-100 py-3 pl-10 pr-4 rounded-2xl text-sm outline-none border-2 border-transparent focus:border-red-100 transition">
            </div>
            <button onclick="openCheckout()" class="relative text-slate-700 w-11 h-11 bg-slate-100 rounded-2xl flex items-center justify-center">
                <i class="fas fa-shopping-basket"></i>
                <?php if($cartCount > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center border-2 border-white"><?= $cartCount ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <div class="flex gap-2 overflow-x-auto hide-scroll pb-2" id="categoryBar">
            <button onclick="setCategory('Tudo')" class="cat-btn active whitespace-nowrap bg-white px-5 py-2 rounded-full text-[11px] font-black uppercase tracking-wider text-slate-500 border border-slate-200 transition">Tudo</button>
            <?php foreach($categories as $cat): ?>
            <button onclick="setCategory('<?= $cat ?>')" class="cat-btn whitespace-nowrap bg-white px-5 py-2 rounded-full text-[11px] font-black uppercase tracking-wider text-slate-500 border border-slate-200 transition"><?= $cat ?></button>
            <?php endforeach; ?>
        </div>

        <div class="flex gap-2 overflow-x-auto hide-scroll py-2 hidden" id="subcategoryBar"></div>
    </header>

    <?php if($currentView === 'home'): ?>
    <main class="p-4 space-y-6">
        <div class="bg-medical rounded-[2.5rem] p-8 text-white relative overflow-hidden shadow-2xl shadow-red-100">
            <div class="relative z-10">
                <h2 class="text-3xl font-black leading-none mb-2 italic tracking-tighter">O MELHOR DA<br>FARMÁCIA.</h2>
                <p class="text-[10px] font-black uppercase tracking-widest bg-white/20 w-fit px-3 py-1 rounded-full">Entrega Rápida & Segura</p>
            </div>
            <i class="fas fa-prescription-bottle-alt absolute -right-6 -bottom-6 text-white/10 text-9xl rotate-12"></i>
        </div>

        <div class="grid grid-cols-2 gap-4" id="productsGrid">
            <?php foreach($products as $p): 
                $stk = getStock($p['id'], $inventory); 
            ?>
                <div class="product-item product-card bg-white shadow-sm border border-slate-100 flex flex-col" 
                     data-category="<?= $p['category'] ?? '' ?>" 
                     data-subcategory="<?= $p['subcategory'] ?? '' ?>"
                     data-name="<?= strtolower($p['name']) ?>">
                    
                    <div onclick='showProductDetails(<?= json_encode($p) ?>)' class="cursor-pointer">
                        <div class="w-full aspect-square bg-white relative p-4">
                             <img src="<?= $p['photo'] ?>" class="w-full h-full object-contain">
                             <?php if($p['requires_prescription'] ?? false): ?>
                             <span class="absolute top-3 left-3 bg-orange-100 text-orange-600 text-[8px] font-black px-2 py-1 rounded-lg uppercase tracking-widest"><i class="fas fa-file-medical mr-1"></i>Receita</span>
                             <?php endif; ?>
                        </div>
                        <div class="px-5 py-2">
                            <h4 class="font-bold text-[11px] text-slate-800 leading-tight line-clamp-2 min-h-[32px]"><?= htmlspecialchars($p['name']) ?></h4>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-tighter"><?= htmlspecialchars($p['subcategory'] ?? '') ?></p>
                            <p class="text-medical font-black text-lg mt-1">R$ <?= number_format($p['price'], 2, ',', '.') ?></p>
                        </div>
                    </div>

                    <div class="px-5 pb-5 mt-auto">
                        <form method="POST" class="flex items-center gap-2">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <div class="flex items-center bg-slate-50 rounded-xl p-1 border border-slate-100">
                                <button type="button" onclick="changeQty(this, -1)" class="w-7 h-7 flex items-center justify-center text-slate-400"><i class="fas fa-minus text-[8px]"></i></button>
                                <input type="number" name="quantity" value="1" min="1" max="<?= $stk ?>" class="w-6 text-center bg-transparent border-none text-[11px] font-black text-slate-700 outline-none p-0">
                                <button type="button" onclick="changeQty(this, 1)" class="w-7 h-7 flex items-center justify-center text-slate-400"><i class="fas fa-plus text-[8px]"></i></button>
                            </div>
                            <button type="submit" name="add_to_cart" <?= $stk <= 0 ? 'disabled' : '' ?> class="flex-1 bg-medical text-white h-9 rounded-xl text-[10px] font-black uppercase transition active:scale-95 disabled:bg-slate-200">
                                <?= $stk > 0 ? 'Comprar' : 'Esgotado' ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <?php elseif($currentView === 'orders'): ?>
        <main class="p-4">
             <h2 class="text-2xl font-black text-slate-800 mb-6 italic">Meus Pedidos</h2>
             <div class="space-y-4">
                <?php 
                $myOrders = array_filter($allOrders, fn($o) => in_array($o['id'], $_SESSION['my_order_ids']));
                if(empty($myOrders)): ?>
                    <div class="text-center py-20 opacity-20">
                        <i class="fas fa-box-open text-7xl mb-4"></i>
                        <p class="font-black text-sm uppercase tracking-widest">Nenhum pedido</p>
                    </div>
                <?php else: foreach(array_reverse($myOrders) as $mo): ?>
                    <a href="track_order.php?id=<?= $mo['id'] ?>" class="block bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition active:scale-95">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[10px] font-black text-slate-300 tracking-widest uppercase">PEDIDO #<?= $mo['id'] ?></span>
                            <span class="px-4 py-1 bg-red-50 text-red-600 rounded-full text-[9px] font-black uppercase"><?= $mo['status'] ?></span>
                        </div>
                        <p class="font-black text-slate-800 text-lg">R$ <?= number_format($mo['total'], 2, ',', '.') ?></p>
                        <p class="text-[10px] text-slate-400 mt-1"><?= date('d/m/Y H:i', strtotime($mo['date'])) ?></p>
                    </a>
                <?php endforeach; endif; ?>
             </div>
        </main>
    <?php endif; ?>

    <nav class="fixed bottom-0 inset-x-0 bg-white border-t border-slate-100 p-4 flex justify-around items-center z-40">
        <a href="menu.php?view=home" class="flex flex-col items-center gap-1 <?= $currentView==='home'?'text-medical':'text-slate-400' ?>">
            <i class="fas fa-store text-xl"></i>
            <span class="text-[10px] font-black uppercase tracking-tighter">Início</span>
        </a>
        <a href="menu.php?view=orders" class="flex flex-col items-center gap-1 <?= $currentView==='orders'?'text-medical':'text-slate-400' ?>">
            <i class="fas fa-receipt text-xl"></i>
            <span class="text-[10px] font-black uppercase tracking-tighter">Pedidos</span>
        </a>
        <a href="dashboard.php" class="flex flex-col items-center gap-1 text-slate-400">
            <i class="fas fa-user-circle text-xl"></i>
            <span class="text-[10px] font-black uppercase tracking-tighter">Admin</span>
        </a>
    </nav>

    <!-- Modal Detalhes do Produto -->
    <div id="detailsModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[3rem] w-full max-w-sm p-0 overflow-hidden animate-slide-up flex flex-col">
            <div class="p-6 pb-0 flex justify-end">
                <button onclick="closeDetails()" class="w-10 h-10 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="px-8 pb-10">
                <div class="w-full aspect-square bg-white flex items-center justify-center mb-6">
                    <img id="detImg" src="" class="max-w-full max-h-full object-contain">
                </div>
                <div class="mb-4">
                    <span id="detSubcat" class="text-[9px] font-black text-indigo-500 uppercase tracking-widest"></span>
                    <h3 id="detName" class="text-2xl font-black text-slate-800 leading-tight"></h3>
                </div>
                <p id="detDesc" class="text-sm text-slate-500 leading-relaxed line-clamp-4 mb-6"></p>
                <div class="flex items-center justify-between border-t border-slate-100 pt-6">
                    <p id="detPrice" class="text-2xl font-black text-medical"></p>
                    <div class="flex items-center bg-slate-100 rounded-2xl p-1 border border-slate-200">
                        <button type="button" onclick="changeQty(this, -1)" class="w-10 h-10 flex items-center justify-center text-slate-500"><i class="fas fa-minus text-xs"></i></button>
                        <input type="number" name="quantity" value="1" min="1" id="detQtyInput" class="w-10 text-center bg-transparent border-none text-base font-black text-slate-800 outline-none p-0">
                        <button type="button" onclick="changeQty(this, 1)" class="w-10 h-10 flex items-center justify-center text-slate-500"><i class="fas fa-plus text-xs"></i></button>
                    </div>
                </div>
                <form method="POST" class="mt-6">
                    <input type="hidden" name="product_id" id="detIdInput">
                    <input type="hidden" name="quantity" id="detQtyHidden">
                    <button type="submit" name="add_to_cart" onclick="document.getElementById('detQtyHidden').value = document.getElementById('detQtyInput').value" class="w-full bg-medical text-white h-14 rounded-2xl font-black uppercase text-xs tracking-widest shadow-2xl shadow-red-100 active:scale-95 transition">
                        Adicionar ao Carrinho
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Checkout Drawer Corrigido -->
    <div id="checkoutDrawer" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[100] hidden flex flex-col justify-end">
        <div class="bg-white rounded-t-[3rem] h-[90vh] overflow-y-auto p-8 flex flex-col animate-slide-up relative w-full max-w-xl mx-auto shadow-2xl">
            <button onclick="closeCheckout()" class="absolute top-6 right-8 text-slate-300 hover:text-red-600 transition"><i class="fas fa-times-circle text-2xl"></i></button>
            <div class="flex justify-center gap-2 mb-8">
                <div id="stepDot1" class="w-10 h-1.5 rounded-full bg-red-600"></div>
                <div id="stepDot2" class="w-10 h-1.5 rounded-full bg-slate-100"></div>
                <div id="stepDot3" class="w-10 h-1.5 rounded-full bg-slate-100"></div>
            </div>
            <form method="POST" id="checkoutForm" class="flex-1 flex flex-col">
                <input type="hidden" name="subtotal_value" value="<?= $subTotalValue ?>">
                <input type="hidden" name="freight_value" id="f_val" value="0">
                <input type="hidden" name="full_address" id="f_addr" value="">
                
                <div id="step1" class="space-y-6 flex-1 flex flex-col">
                    <h2 class="text-2xl font-black text-slate-800 italic">Sua Cesta</h2>
                    <div class="space-y-4 overflow-y-auto flex-1">
                        <?php if(empty($_SESSION['menu_cart'])): ?>
                            <p class="text-center text-slate-400 font-bold py-10 uppercase text-xs tracking-widest">Vazio...</p>
                        <?php else: foreach($_SESSION['menu_cart'] as $it): ?>
                        <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-[1.5rem] border border-slate-100">
                            <img src="<?= $it['photo'] ?>" class="w-12 h-12 object-contain bg-white rounded-xl">
                            <div class="flex-1">
                                <p class="text-xs font-bold text-slate-800"><?= htmlspecialchars($it['name']) ?></p>
                                <p class="text-[10px] text-slate-400"><?= $it['qty'] ?> un x R$ <?= number_format($it['price'],2,',','.') ?></p>
                            </div>
                            <span class="font-black text-medical text-sm">R$ <?= number_format($it['price']*$it['qty'],2,',','.') ?></span>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="pt-6 border-t border-slate-100">
                        <div class="flex justify-between mb-6">
                            <span class="text-slate-400 font-bold uppercase text-[10px]">Subtotal</span>
                            <span class="text-xl font-black text-slate-800">R$ <?= number_format($subTotalValue,2,',','.') ?></span>
                        </div>
                        <button type="button" onclick="goToStep(2)" <?= empty($_SESSION['menu_cart'])?'disabled':'' ?> class="w-full bg-red-600 text-white py-5 rounded-2xl font-black uppercase text-xs tracking-widest shadow-xl active:scale-95 transition">Próximo Passo</button>
                    </div>
                </div>

                <div id="step2" class="step-hidden space-y-6 flex-1 flex flex-col">
                    <h2 class="text-2xl font-black text-slate-800 italic">Onde Entregar?</h2>
                    <div class="space-y-4">
                        <div class="flex gap-2">
                            <input type="text" id="cep" placeholder="CEP" class="flex-1 bg-slate-100 p-4 rounded-2xl font-bold text-sm outline-none">
                            <button type="button" onclick="calcCEP()" class="bg-slate-900 text-white px-6 rounded-2xl font-black text-[10px] uppercase">Buscar</button>
                        </div>
                        <div id="addressFields" class="hidden space-y-3">
                            <input type="text" id="street" placeholder="Rua" class="w-full bg-slate-100 p-4 rounded-2xl text-sm font-bold outline-none">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" id="num" placeholder="Número" class="bg-slate-100 p-4 rounded-2xl text-sm font-bold outline-none">
                                <input type="text" id="neigh" placeholder="Bairro" class="bg-slate-100 p-4 rounded-2xl text-sm font-bold outline-none">
                            </div>
                            <div id="freightMsg" class="p-4 bg-red-50 text-red-600 rounded-2xl text-[9px] font-black uppercase tracking-widest text-center"></div>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-auto">
                        <button type="button" onclick="goToStep(1)" class="flex-1 bg-slate-100 text-slate-400 py-5 rounded-2xl font-black uppercase text-[10px]">Voltar</button>
                        <button type="button" onclick="goToStep(3)" class="flex-[2] bg-red-600 text-white py-5 rounded-2xl font-black uppercase text-[10px]">Pagamento</button>
                    </div>
                </div>

                <div id="step3" class="step-hidden space-y-6 flex-1 flex flex-col">
                    <h2 class="text-2xl font-black text-slate-800 italic">Como pagar?</h2>
                    <div class="space-y-2">
                        <?php foreach(['Dinheiro', 'Pix', 'Cartão (Motoboy)'] as $m): ?>
                        <label class="p-5 border-2 border-slate-100 rounded-[2rem] flex items-center gap-4 cursor-pointer has-[:checked]:border-red-600 has-[:checked]:bg-red-50 transition">
                            <input type="radio" name="payment_method" value="<?= $m ?>" <?= $m==='Dinheiro'?'checked':'' ?> class="accent-red-600">
                            <span class="text-xs font-black uppercase text-slate-600"><?= $m ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="pt-6 border-t border-slate-100 mt-auto">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-slate-400 font-bold uppercase text-[10px]">Total Geral</span>
                            <span id="displayTotalFinal" class="text-3xl font-black text-medical italic">R$ <?= number_format($subTotalValue,2,',','.') ?></span>
                        </div>
                        <button type="submit" name="finish_order" class="w-full bg-medical text-white py-6 rounded-3xl font-black uppercase text-xs tracking-widest shadow-2xl shadow-red-200 active:scale-95 transition">Finalizar Pedido</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Pós-Adição -->
    <?php if(isset($_GET['show_added_modal'])): ?>
    <div id="addedSuccessModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md z-[110] flex items-center justify-center p-6">
        <div class="bg-white rounded-[3rem] p-8 text-center max-w-xs shadow-2xl animate-slide-up">
            <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6"><i class="fas fa-check text-3xl"></i></div>
            <h3 class="text-xl font-black text-slate-800 mb-2">Sucesso!</h3>
            <p class="text-xs text-slate-500 mb-8 font-medium">Produto adicionado.</p>
            <div class="space-y-3">
                <button onclick="closeAddedModal(); openCheckout();" class="w-full bg-red-600 text-white py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg">Ver Carrinho</button>
                <button onclick="closeAddedModal()" class="block w-full text-slate-400 font-black text-[10px] uppercase py-2">Continuar Comprando</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const productsRaw = <?= json_encode($products) ?>;
        let selectedCategory = 'Tudo';
        let selectedSubcategory = 'Todas';

        function changeQty(btn, delta) {
            const input = btn.parentNode.querySelector('input');
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            if (input.max && val > parseInt(input.max)) val = parseInt(input.max);
            input.value = val;
        }

        function filterProducts() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.product-item').forEach(el => {
                const name = el.getAttribute('data-name');
                const cat = el.getAttribute('data-category');
                const sub = el.getAttribute('data-subcategory');

                let matchesSearch = name.includes(term);
                let matchesCat = (selectedCategory === 'Tudo' || cat === selectedCategory);
                let matchesSub = (selectedSubcategory === 'Todas' || sub === selectedSubcategory);

                el.style.display = (matchesSearch && matchesCat && matchesSub) ? 'flex' : 'none';
            });
        }

        function setCategory(cat) {
            selectedCategory = cat;
            selectedSubcategory = 'Todas';
            
            document.querySelectorAll('.cat-btn').forEach(btn => {
                btn.classList.toggle('active', btn.innerText.includes(cat) || (cat === 'Tudo' && btn.innerText === 'TUDO'));
            });

            const subBar = document.getElementById('subcategoryBar');
            if(cat === 'Tudo') {
                subBar.classList.add('hidden');
            } else {
                const subcats = [...new Set(productsRaw.filter(p => p.category === cat).map(p => p.subcategory).filter(s => s))];
                if(subcats.length > 0) {
                    subBar.innerHTML = `<button onclick="setSubcategory('Todas')" class="subcat-btn active whitespace-nowrap bg-slate-200 px-4 py-1.5 rounded-full text-[10px] font-bold text-slate-600 transition">Todas</button>` + 
                        subcats.map(s => `<button onclick="setSubcategory('${s}')" class="subcat-btn whitespace-nowrap bg-slate-100 px-4 py-1.5 rounded-full text-[10px] font-bold text-slate-600 transition">${s}</button>`).join('');
                    subBar.classList.remove('hidden');
                } else {
                    subBar.classList.add('hidden');
                }
            }
            filterProducts();
        }

        function setSubcategory(sub) {
            selectedSubcategory = sub;
            document.querySelectorAll('.subcat-btn').forEach(btn => {
                btn.classList.toggle('active', btn.innerText === sub);
            });
            filterProducts();
        }

        function showProductDetails(p) {
            document.getElementById('detImg').src = p.photo;
            document.getElementById('detName').innerText = p.name;
            document.getElementById('detSubcat').innerText = p.subcategory || p.category;
            document.getElementById('detDesc').innerText = p.description || "Descrição em breve...";
            document.getElementById('detPrice').innerText = "R$ " + p.price.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            document.getElementById('detIdInput').value = p.id;
            document.getElementById('detQtyInput').value = 1;
            document.getElementById('detailsModal').classList.remove('hidden');
        }

        function closeDetails() { document.getElementById('detailsModal').classList.add('hidden'); }
        function openCheckout() { document.getElementById('checkoutDrawer').classList.remove('hidden'); }
        function closeCheckout() { document.getElementById('checkoutDrawer').classList.add('hidden'); }
        function closeAddedModal() { 
            const m = document.getElementById('addedSuccessModal'); 
            if(m) {
                m.style.display = 'none';
                const url = new URL(window.location);
                url.searchParams.delete('show_added_modal');
                window.history.replaceState({}, '', url);
            }
        }

        function goToStep(s) {
            document.querySelectorAll('[id^="step"]').forEach(el => {
                if(el.id === 'step'+s) el.classList.remove('step-hidden');
                else if(!el.id.includes('Dot')) el.classList.add('step-hidden');
                if(el.id === 'stepDot'+s) el.classList.replace('bg-slate-100', 'bg-red-600');
                else if(el.id.startsWith('stepDot')) el.classList.replace('bg-red-600', 'bg-slate-100');
            });
        }

        async function calcCEP() {
            const cep = document.getElementById('cep').value.replace(/\D/g,'');
            if(cep.length !== 8) return;
            const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const d = await r.json();
            if(!d.erro) {
                document.getElementById('street').value = d.logradouro;
                document.getElementById('neigh').value = d.bairro;
                document.getElementById('addressFields').classList.remove('hidden');
                document.getElementById('freightMsg').innerText = "CALCULANDO FRETE...";
                
                const geo = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(d.logradouro + ',' + d.localidade)}`);
                const gd = await geo.json();
                if(gd.length > 0) {
                    const fr = await fetch(`menu.php?calc_freight=1&lat=${gd[0].lat}&lng=${gd[0].lon}&bairro=${d.bairro}`);
                    const frd = await fr.json();
                    const cost = parseFloat(frd.cost || 0);
                    document.getElementById('f_val').value = cost;
                    document.getElementById('freightMsg').innerText = cost === 0 ? "FRETE GRÁTIS!" : `TAXA DE ENTREGA: R$ ${cost.toFixed(2)}`;
                    const total = <?= $subTotalValue ?> + cost;
                    document.getElementById('displayTotalFinal').innerText = `R$ ${total.toLocaleString('pt-BR',{minimumFractionDigits:2})}`;
                    document.getElementById('f_addr').value = `${d.logradouro}, ${d.bairro} - ${d.localidade}`;
                }
            }
        }
    </script>
</body>
</html>