<?php
session_start();
error_reporting(0);

function getOrder($id) {
    if (!file_exists('data/orders.json')) return null;
    $orders = json_decode(file_get_contents('data/orders.json'), true);
    foreach($orders as $o) if($o['id'] == $id) return $o;
    return null;
}

$id = $_GET['id'] ?? 0;
$order = getOrder($id);

$steps = [
    'Pendente' => 1,
    'Preparando' => 2,
    'Saiu para Entrega' => 3,
    'Entregue' => 4,
    'Concluído' => 4
];
$currentStep = $steps[$order['status'] ?? 'Pendente'] ?? 1;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Pedido #<?= $id ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen p-6">
    
    <div class="max-w-md mx-auto">
        <a href="menu.php?view=orders" class="text-slate-400 font-bold text-xs uppercase mb-6 inline-block"><i class="fas fa-arrow-left mr-2"></i>Voltar</a>

        <?php if(!$order): ?>
            <p class="text-center font-bold text-slate-400">Pedido não encontrado.</p>
        <?php else: ?>
            <div class="bg-white rounded-[2.5rem] p-8 shadow-sm border border-slate-100 mb-6 text-center">
                <div class="w-20 h-20 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-truck-loading text-3xl"></i>
                </div>
                <h1 class="text-2xl font-black text-slate-800">Pedido #<?= $id ?></h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1"><?= $order['status'] ?></p>
            </div>

            <!-- Timeline -->
            <div class="bg-white rounded-[2.5rem] p-8 shadow-sm border border-slate-100 space-y-8">
                <div class="flex gap-4 items-start relative">
                    <div class="z-10 w-8 h-8 rounded-full flex items-center justify-center <?= $currentStep >= 1 ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-300' ?>">
                        <i class="fas fa-check text-[10px]"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-sm <?= $currentStep >= 1 ? 'text-slate-800' : 'text-slate-300' ?>">Pedido Recebido</p>
                        <p class="text-[10px] text-slate-400 uppercase">Aguardando confirmação</p>
                    </div>
                    <div class="absolute left-4 top-8 w-0.5 h-8 <?= $currentStep > 1 ? 'bg-red-600' : 'bg-slate-100' ?>"></div>
                </div>

                <div class="flex gap-4 items-start relative">
                    <div class="z-10 w-8 h-8 rounded-full flex items-center justify-center <?= $currentStep >= 2 ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-300' ?>">
                        <i class="fas fa-box text-[10px]"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-sm <?= $currentStep >= 2 ? 'text-slate-800' : 'text-slate-300' ?>">Preparando</p>
                        <p class="text-[10px] text-slate-400 uppercase">Separando seus produtos</p>
                    </div>
                    <div class="absolute left-4 top-8 w-0.5 h-8 <?= $currentStep > 2 ? 'bg-red-600' : 'bg-slate-100' ?>"></div>
                </div>

                <div class="flex gap-4 items-start relative">
                    <div class="z-10 w-8 h-8 rounded-full flex items-center justify-center <?= $currentStep >= 3 ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-300' ?>">
                        <i class="fas fa-motorcycle text-[10px]"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-sm <?= $currentStep >= 3 ? 'text-slate-800' : 'text-slate-300' ?>">Em Rota</p>
                        <p class="text-[10px] text-slate-400 uppercase">O motoboy já saiu!</p>
                    </div>
                    <div class="absolute left-4 top-8 w-0.5 h-8 <?= $currentStep > 3 ? 'bg-red-600' : 'bg-slate-100' ?>"></div>
                </div>

                <div class="flex gap-4 items-start">
                    <div class="z-10 w-8 h-8 rounded-full flex items-center justify-center <?= $currentStep >= 4 ? 'bg-green-500 text-white' : 'bg-slate-100 text-slate-300' ?>">
                        <i class="fas fa-home text-[10px]"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-sm <?= $currentStep >= 4 ? 'text-slate-800' : 'text-slate-300' ?>">Entregue</p>
                        <p class="text-[10px] text-slate-400 uppercase">Pedido finalizado</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 bg-medical p-6 rounded-3xl text-white">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-bold opacity-70">Valor Total</span>
                    <span class="text-xl font-black">R$ <?= number_format($order['total'], 2, ',', '.') ?></span>
                </div>
                <p class="text-[10px] font-bold uppercase opacity-70 mb-1">Endereço de Entrega</p>
                <p class="text-xs font-bold leading-tight"><?= $order['address'] ?></p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>