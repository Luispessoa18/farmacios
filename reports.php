<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

function getJsonData($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?? [];
}

$orders = getJsonData('data/orders.json');
$products = getJsonData('data/products.json');

// Agrupamentos
$vendasPorDia = [];
$vendasPorMeio = [
    'Dinheiro' => 0,
    'Pix' => 0,
    'Debito' => 0,
    'Credito' => 0
];
$topProdutos = [];

foreach ($orders as $o) {
    if(($o['status'] ?? '') === 'Cancelado') continue;

    $date = date('d/m/Y', strtotime($o['date'] ?? 'now'));
    $total = (float)($o['total'] ?? 0);

    // Vendas Dia
    $vendasPorDia[$date] = ($vendasPorDia[$date] ?? 0) + $total;

    // Meios de Pagamento (Trata split do PDV e pagamento único da Loja)
    if (isset($o['payments'])) {
        $vendasPorMeio['Dinheiro'] += $o['payments']['dinheiro'] ?? 0;
        $vendasPorMeio['Pix'] += $o['payments']['pix'] ?? 0;
        $vendasPorMeio['Debito'] += $o['payments']['debito'] ?? 0;
        $vendasPorMeio['Credito'] += $o['payments']['credito'] ?? 0;
    } else {
        $metodo = $o['payment'] ?? 'Desconhecido';
        if(strpos($metodo, 'Dinheiro') !== false) $vendasPorMeio['Dinheiro'] += $total;
        elseif(strpos($metodo, 'Pix') !== false) $vendasPorMeio['Pix'] += $total;
        elseif(strpos($metodo, 'Débito') !== false) $vendasPorMeio['Debito'] += $total;
        elseif(strpos($metodo, 'Crédito') !== false) $vendasPorMeio['Credito'] += $total;
    }

    // Top Produtos
    foreach($o['items'] as $it) {
        $name = $it['name'];
        $topProdutos[$name] = ($topProdutos[$name] ?? 0) + ($it['qty'] ?? 1);
    }
}

arsort($topProdutos);
$topProdutos = array_slice($topProdutos, 0, 5);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>FarmaCium - Relatórios Profissionais</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 p-6 min-h-screen">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-10">
            <a href="dashboard.php" class="bg-white px-6 py-3 rounded-2xl shadow-sm font-black text-xs uppercase tracking-widest text-slate-400 hover:text-indigo-600 transition">
                <i class="fas fa-arrow-left mr-2"></i> Voltar
            </a>
            <h1 class="text-2xl font-black text-slate-800 italic uppercase">Inteligência de Vendas</h1>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <!-- Vendas por Meio -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <h3 class="font-black text-slate-400 uppercase text-xs mb-6 tracking-widest">Faturamento por Meio</h3>
                <div class="space-y-4">
                    <?php foreach($vendasPorMeio as $meio => $val): 
                        $perc = array_sum($vendasPorMeio) > 0 ? ($val / array_sum($vendasPorMeio)) * 100 : 0;
                    ?>
                        <div>
                            <div class="flex justify-between text-sm font-bold mb-1 uppercase tracking-tighter">
                                <span class="text-slate-600"><?= $meio ?></span>
                                <span class="text-indigo-600">R$ <?= number_format($val, 2, ',', '.') ?></span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-indigo-500 h-full" style="width: <?= $perc ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Produtos -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <h3 class="font-black text-slate-400 uppercase text-xs mb-6 tracking-widest">Produtos Mais Vendidos</h3>
                <div class="space-y-4">
                    <?php if(empty($topProdutos)): ?>
                        <p class="text-center text-slate-300 py-10 font-bold uppercase text-xs">Nenhum dado disponível</p>
                    <?php endif; ?>
                    <?php foreach($topProdutos as $nome => $qtd): ?>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl">
                            <span class="font-bold text-slate-700 text-sm"><?= $nome ?></span>
                            <span class="bg-indigo-600 text-white px-3 py-1 rounded-lg font-black text-xs"><?= $qtd ?> un</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Evolução Diária -->
            <div class="bg-slate-900 p-8 rounded-[2.5rem] shadow-2xl text-white col-span-1 md:col-span-2">
                <h3 class="font-black text-slate-500 uppercase text-xs mb-6 tracking-widest">Evolução Diária de Vendas</h3>
                <div class="space-y-2 max-h-60 overflow-y-auto pr-4">
                    <?php foreach(array_reverse($vendasPorDia) as $data => $total): ?>
                        <div class="flex justify-between items-center py-3 border-b border-white/5">
                            <span class="font-bold text-slate-400"><?= $data ?></span>
                            <span class="font-black text-xl text-green-400">R$ <?= number_format($total, 2, ',', '.') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>