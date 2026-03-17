<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['user_role'] ?? 'cashier';

function getJsonData($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    if (empty($content)) return [];
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

$products = getJsonData('data/products.json');
$orders = getJsonData('data/orders.json');
$settings = getJsonData('data/settings.json');

// Financeiro
$recebido = 0;
$aReceber = 0;
$statsStatus = [
    'Pendente' => 0,
    'Preparando' => 0,
    'Saiu para Entrega' => 0,
    'Entregue' => 0,
    'Concluído' => 0,
    'Cancelado' => 0
];

foreach($orders as $o) {
    $total = (float)($o['total'] ?? 0);
    $status = $o['status'] ?? 'Pendente';
    
    if($status === 'Concluído' || $status === 'Entregue') {
        $recebido += $total;
    } elseif($status !== 'Cancelado') {
        $aReceber += $total;
    }

    if(isset($statsStatus[$status])) {
        $statsStatus[$status] += $total;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>FarmaCium - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen">

    <nav class="bg-indigo-700 text-white p-4 shadow-xl flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <i class="fas fa-pills text-xl"></i>
            <h1 class="text-xl font-bold tracking-tight uppercase italic"><?= htmlspecialchars($settings['store_name'] ?? 'FarmaCium') ?></h1>
        </div>
        <div class="flex items-center space-x-6">
            <span class="text-xs font-bold bg-white/10 px-3 py-1 rounded-lg uppercase"><?= $_SESSION['user_id'] ?> (<?= ucfirst($role) ?>)</span>
            <a href="?logout=true" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg text-sm font-black transition">Sair</a>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h2 class="text-3xl font-black text-slate-800 tracking-tighter">BEM-VINDO AO CONTROLE</h2>
                <p class="text-slate-500 font-medium">Gestão financeira e operacional em tempo real.</p>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Data do Sistema</span>
                <p class="font-bold text-slate-800"><?= date('d/m/Y') ?></p>
            </div>
        </div>

        <!-- Cards Financeiros -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <p class="text-slate-400 text-xs font-black uppercase mb-2 tracking-widest">Caixa Recebido</p>
                <p class="text-4xl font-black text-emerald-600 tracking-tighter">R$ <?= number_format($recebido, 2, ',', '.') ?></p>
                <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-emerald-500 bg-emerald-50 w-fit px-3 py-1 rounded-full">
                    <i class="fas fa-check-circle"></i> Disponível em conta/caixa
                </div>
            </div>
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <p class="text-slate-400 text-xs font-black uppercase mb-2 tracking-widest">A Receber (Online)</p>
                <p class="text-4xl font-black text-indigo-600 tracking-tighter">R$ <?= number_format($aReceber, 2, ',', '.') ?></p>
                <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-indigo-500 bg-indigo-50 w-fit px-3 py-1 rounded-full">
                    <i class="fas fa-clock"></i> Pedidos em rota ou preparação
                </div>
            </div>
            <div class="bg-slate-900 p-8 rounded-[2.5rem] shadow-2xl text-white">
                <p class="text-slate-500 text-xs font-black uppercase mb-2 tracking-widest">Total Geral Bruto</p>
                <p class="text-4xl font-black tracking-tighter">R$ <?= number_format($recebido + $aReceber, 2, ',', '.') ?></p>
                <p class="mt-4 text-[10px] text-slate-400 font-bold uppercase tracking-widest">Acumulado do mês</p>
            </div>
        </div>

        <!-- Status por Etapa -->
        <h3 class="text-lg font-black text-slate-700 mb-6 flex items-center gap-2 uppercase tracking-widest">
            <i class="fas fa-chart-line text-indigo-600"></i> Status de Pedidos (Vendas Online)
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-10">
            <?php foreach($statsStatus as $status => $val): if($status == 'Concluído' || $status == 'Cancelado') continue; ?>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm text-center">
                    <span class="text-[9px] font-black text-slate-400 uppercase"><?= $status ?></span>
                    <p class="text-xl font-black text-slate-800 mt-1">R$ <?= number_format($val, 2, ',', '.') ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Menu de Ações -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <a href="pdv.php" class="bg-indigo-600 p-6 rounded-3xl text-white flex flex-col items-center group hover:bg-indigo-700 transition">
                <i class="fas fa-cash-register text-3xl mb-3"></i>
                <span class="font-black uppercase text-xs">Abrir PDV</span>
            </a>
            <a href="orders.php" class="bg-white p-6 rounded-3xl text-slate-800 border border-slate-200 flex flex-col items-center hover:border-indigo-600 transition">
                <i class="fas fa-truck-loading text-3xl mb-3 text-indigo-600"></i>
                <span class="font-black uppercase text-xs text-slate-500">Pedidos</span>
            </a>
            <a href="reports.php" class="bg-white p-6 rounded-3xl text-slate-800 border border-slate-200 flex flex-col items-center hover:border-indigo-600 transition">
                <i class="fas fa-file-invoice-dollar text-3xl mb-3 text-indigo-600"></i>
                <span class="font-black uppercase text-xs text-slate-500">Relatórios</span>
            </a>
            <?php if($role === 'admin'): ?>
            <a href="manage_products.php" class="bg-white p-6 rounded-3xl text-slate-800 border border-slate-200 flex flex-col items-center hover:border-indigo-600 transition">
                <i class="fas fa-boxes text-3xl mb-3 text-indigo-600"></i>
                <span class="font-black uppercase text-xs text-slate-500">Estoque</span>
            </a>
            <a href="settings.php" class="bg-white p-6 rounded-3xl text-slate-800 border border-slate-200 flex flex-col items-center hover:border-indigo-600 transition">
                <i class="fas fa-tools text-3xl mb-3 text-indigo-600"></i>
                <span class="font-black uppercase text-xs text-slate-500">Ajustes</span>
            </a>
            <?php endif; ?>
            <a href="menu.php" class="bg-slate-100 p-6 rounded-3xl text-slate-400 flex flex-col items-center hover:bg-slate-200 transition">
                <i class="fas fa-eye text-3xl mb-3"></i>
                <span class="font-black uppercase text-xs">Ver Loja</span>
            </a>
        </div>
    </div>
</body>
</html>