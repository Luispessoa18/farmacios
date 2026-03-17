<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

function getJsonData($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?? [];
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = getJsonData('data/users.json');
    $inputUser = $_POST['username'] ?? '';
    $inputPass = $_POST['password'] ?? '';

    foreach ($users as $user) {
        if ($user['username'] === $inputUser && $user['password'] === $inputPass) {
            $_SESSION['user_id'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: dashboard.php');
            exit;
        }
    }
    $message = "Usuário ou senha inválidos!";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmaCium - Plataforma de Gestão para Farmácias</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100">
    <div class="min-h-screen flex flex-col">
        <!-- NAVBAR -->
        <header class="border-b border-slate-800/80 bg-slate-950/80 backdrop-blur sticky top-0 z-20">
            <div class="mx-auto max-w-6xl px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-emerald-400/10 border border-emerald-400/30 flex items-center justify-center">
                        <span class="text-emerald-400 font-black text-lg">Fc</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold tracking-tight">FarmaCium</span>
                            <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-300 border border-emerald-500/30">CRM Farmacêutico</span>
                        </div>
                        <p class="text-[11px] text-slate-400">Operação enxuta, controle total do balcão ao delivery.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="#features" class="hidden sm:inline-flex text-xs font-medium text-slate-300 hover:text-white transition">Funcionalidades</a>
                    <a href="#login" class="hidden sm:inline-flex text-xs font-medium text-slate-300 hover:text-white transition">Entrar</a>
                    <button onclick="document.getElementById('login').scrollIntoView({behavior:'smooth'})" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-2 text-xs font-semibold text-slate-950 shadow-lg shadow-emerald-500/30 hover:bg-emerald-400 transition">
                        Entrar no sistema
                        <span class="text-[10px]">→</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- HERO + LOGIN -->
        <main class="flex-1">
            <section class="mx-auto max-w-6xl px-4 py-10 lg:py-16 grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] gap-10 items-center">
                <!-- HERO COPY -->
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-[11px] font-medium text-emerald-200 mb-4">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Painel único para balcão, estoque e delivery
                    </div>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-white mb-4">
                        Centralize o atendimento da sua farmácia em uma única tela.
                    </h1>
                    <p class="text-sm sm:text-base text-slate-300 max-w-xl mb-6">
                        O FarmaCium conecta balcão, pedidos online, delivery e estoque em um fluxo simples.
                        Menos retrabalho, mais controle e atendimento mais rápido para cada cliente.
                    </p>

                    <div class="flex flex-wrap items-center gap-3 mb-6">
                        <button onclick="document.getElementById('login').scrollIntoView({behavior:'smooth'})" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-2 text-xs sm:text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-500/30 hover:bg-emerald-400 transition">
                            Acessar painel
                            <span class="text-[11px]">(usuário interno)</span>
                        </button>
                        <div class="flex items-center gap-2 text-[11px] text-slate-400">
                            <div class="flex -space-x-2">
                                <div class="h-7 w-7 rounded-full border border-slate-900 bg-emerald-400/70"></div>
                                <div class="h-7 w-7 rounded-full border border-slate-900 bg-sky-400/70"></div>
                                <div class="h-7 w-7 rounded-full border border-slate-900 bg-indigo-400/70"></div>
                            </div>
                            <span>Farmácias usando diariamente o fluxo do FarmaCium.</span>
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs sm:text-sm">
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-3">
                            <dt class="text-slate-400">Atendimento mais ágil</dt>
                            <dd class="text-lg font-semibold text-emerald-400">-35% tempo</dd>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-3">
                            <dt class="text-slate-400">Controle de estoque</dt>
                            <dd class="text-lg font-semibold text-emerald-400">em tempo real</dd>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-3">
                            <dt class="text-slate-400">Múltiplos canais</dt>
                            <dd class="text-lg font-semibold text-emerald-400">balcão + delivery</dd>
                        </div>
                    </dl>
                </div>

                <!-- LOGIN CARD -->
                <div id="login" class="lg:justify-self-end">
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl shadow-xl shadow-black/40 p-6 sm:p-7">
                        <div class="mb-4">
                            <p class="text-xs font-semibold text-emerald-400 uppercase tracking-[0.2em] mb-1">Acesso ao painel</p>
                            <h2 class="text-lg font-semibold text-white">Login para equipe interna</h2>
                            <p class="text-xs text-slate-400 mt-1">Use seu usuário cadastrado para acessar o dashboard da farmácia.</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="bg-red-500/10 border border-red-500/40 text-red-200 text-xs font-medium p-3 rounded-xl mb-4 flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-red-400"></span>
                                <span><?= htmlspecialchars($message) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Usuário</label>
                                <input type="text" name="username" class="w-full rounded-xl border border-slate-700 bg-slate-900/70 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/80 focus:border-emerald-500/80 transition" placeholder="admin ou caixa1" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Senha</label>
                                <input type="password" name="password" class="w-full rounded-xl border border-slate-700 bg-slate-900/70 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/80 focus:border-emerald-500/80 transition" placeholder="123" required>
                            </div>
                            <button type="submit" class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-emerald-500 text-slate-950 text-sm font-semibold py-2.5 shadow-lg shadow-emerald-500/30 hover:bg-emerald-400 transition">
                                Entrar no painel
                            </button>
                            <p class="text-[11px] text-slate-500 text-center">
                                Acesso restrito à equipe autorizada da farmácia.
                            </p>
                        </form>
                    </div>
                </div>
            </section>

            <!-- FEATURES SECTION -->
            <section id="features" class="border-t border-slate-900/80 bg-slate-950/90">
                <div class="mx-auto max-w-6xl px-4 py-10 lg:py-14">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-xl sm:text-2xl font-semibold text-white mb-1">Pensado para a rotina da farmácia</h2>
                            <p class="text-sm text-slate-400 max-w-xl">Da chegada da receita ao fechamento do caixa, o FarmaCium organiza cada etapa para reduzir erros e manter tudo sob controle.</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 flex flex-col gap-2">
                            <div class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-300 text-sm">01</div>
                            <h3 class="font-semibold text-slate-50">Painel de atendimento em tempo real</h3>
                            <p class="text-slate-400 text-xs">Veja pedidos, balcão e delivery em uma única fila, com prioridade e status claros para toda a equipe.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 flex flex-col gap-2">
                            <div class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-300 text-sm">02</div>
                            <h3 class="font-semibold text-slate-50">Estoque conectado ao balcão</h3>
                            <p class="text-slate-400 text-xs">Evite vendas sem estoque e produtos vencidos com alertas e posição de estoque em cada atendimento.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 flex flex-col gap-2">
                            <div class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-300 text-sm">03</div>
                            <h3 class="font-semibold text-slate-50">Indicadores prontos para decisão</h3>
                            <p class="text-slate-400 text-xs">Acompanhe itens mais vendidos, ticket médio, entrega e tempo de atendimento sem planilhas manuais.</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-900/80 bg-slate-950 py-4">
            <div class="mx-auto max-w-6xl px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-[11px] text-slate-500">© <?php echo date('Y'); ?> FarmaCium. Todos os direitos reservados.</p>
                <p class="text-[11px] text-slate-500">Painel interno para rede de farmácias.</p>
            </div>
        </footer>
    </div>
</body>
</html>