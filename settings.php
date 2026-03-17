<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

function getSettings() {
    if (!file_exists('data/settings.json')) return [];
    $content = file_get_contents('data/settings.json');
    return json_decode($content, true) ?? [];
}

function saveSettings($data) {
    file_put_contents('data/settings.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $newSettings = [
        'store_name' => $_POST['store_name'],
        'cep' => $_POST['cep'],
        'street' => $_POST['street'],
        'number' => $_POST['number'],
        'neighborhood' => $_POST['neighborhood'],
        'city' => $_POST['city'],
        'state' => $_POST['state'],
        'phone' => $_POST['phone'],
        'logo_url' => $_POST['logo_url'],
        'footer_message' => $_POST['footer_message'],
        'prescription_mode' => $_POST['prescription_mode'],
        'ors_key' => $_POST['ors_key'],
        'store_lat' => $_POST['store_lat'],
        'store_lng' => $_POST['store_lng'],
        'freight_type' => $_POST['freight_type'],
        'price_per_km' => $_POST['price_per_km'],
        'max_radius' => $_POST['max_radius'],
        'free_neighborhoods' => $_POST['free_neighborhoods'],
        'fixed_neighborhoods' => $_POST['fixed_neighborhoods']
    ];
    saveSettings($newSettings);
    header('Location: settings.php?status=success');
    exit;
}

$settings = getSettings();
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmaCium - Configurações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen">

    <nav class="bg-indigo-700 text-white p-4 shadow-md flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center space-x-3">
            <i class="fas fa-cogs text-2xl"></i>
            <h1 class="text-xl font-bold uppercase tracking-tighter">Configurações Avançadas</h1>
        </div>
        <div class="flex items-center space-x-4">
            <a href="dashboard.php" class="text-sm bg-white/10 px-3 py-1 rounded hover:bg-white/20 transition">Voltar</a>
        </div>
    </nav>

    <div class="container mx-auto p-6 max-w-5xl">
        <?php if ($status === 'success'): ?>
            <div class="bg-green-500 text-white p-4 mb-6 rounded-2xl shadow-lg flex items-center gap-3 animate-pulse">
                <i class="fas fa-check-circle text-xl"></i>
                <span class="font-bold">Alterações salvas com sucesso!</span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Dados Básicos e Endereço -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 space-y-6">
                    <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight flex items-center gap-2">
                        <i class="fas fa-store text-indigo-500"></i> Localização da Loja
                    </h2>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Nome Comercial</label>
                            <input type="text" name="store_name" value="<?= htmlspecialchars($settings['store_name'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700" required>
                        </div>

                        <!-- Busca por CEP -->
                        <div class="bg-indigo-50 p-4 rounded-2xl">
                            <label class="block text-indigo-500 text-[10px] font-black uppercase mb-1">Buscar Endereço por CEP</label>
                            <div class="flex gap-2">
                                <input type="text" id="cep_input" name="cep" value="<?= htmlspecialchars($settings['cep'] ?? '') ?>" placeholder="00000-000" class="flex-1 px-4 py-2 bg-white border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700">
                                <button type="button" onclick="searchAddress()" class="bg-indigo-600 text-white px-4 rounded-xl hover:bg-indigo-700 transition">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Rua / Logradouro</label>
                                <input type="text" id="street" name="street" value="<?= htmlspecialchars($settings['street'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl font-bold text-slate-700">
                            </div>
                            <div>
                                <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Número</label>
                                <input type="text" id="number" name="number" value="<?= htmlspecialchars($settings['number'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl font-bold text-slate-700">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Bairro</label>
                                <input type="text" id="neighborhood" name="neighborhood" value="<?= htmlspecialchars($settings['neighborhood'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl font-bold text-slate-700">
                            </div>
                            <div>
                                <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Cidade</label>
                                <input type="text" id="city" name="city" value="<?= htmlspecialchars($settings['city'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl font-bold text-slate-700">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Estado (UF)</label>
                            <input type="text" id="state" name="state" value="<?= htmlspecialchars($settings['state'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl font-bold text-slate-700">
                        </div>

                        <!-- Geolocalização Gerada -->
                        <div class="bg-slate-900 p-4 rounded-2xl text-white">
                            <p class="text-[10px] font-black uppercase text-slate-500 mb-2">Coordenadas Geográficas (Auto)</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[9px] text-slate-400 mb-1">Latitude</label>
                                    <input type="text" id="store_lat" name="store_lat" value="<?= htmlspecialchars($settings['store_lat'] ?? '') ?>" class="w-full bg-slate-800 border-none rounded-lg py-1 px-3 text-sm text-green-400 font-mono">
                                </div>
                                <div>
                                    <label class="block text-[9px] text-slate-400 mb-1">Longitude</label>
                                    <input type="text" id="store_lng" name="store_lng" value="<?= htmlspecialchars($settings['store_lng'] ?? '') ?>" class="w-full bg-slate-800 border-none rounded-lg py-1 px-3 text-sm text-green-400 font-mono">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Telefone / WhatsApp</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($settings['phone'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700" required>
                        </div>
                    </div>
                </div>

                <!-- Logística e Preferências -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 space-y-6">
                    <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight flex items-center gap-2">
                        <i class="fas fa-truck text-indigo-500"></i> Regras de Negócio
                    </h2>

                    <div>
                        <label class="block text-slate-500 text-[10px] font-black uppercase mb-2">Modo de Receita</label>
                        <select name="prescription_mode" class="w-full bg-slate-100 border-none rounded-xl py-3 px-4 font-bold text-slate-700">
                            <option value="image" <?= ($settings['prescription_mode'] ?? '') == 'image' ? 'selected' : '' ?>>Foto via Sistema (Upload)</option>
                            <option value="motoboy" <?= ($settings['prescription_mode'] ?? '') == 'motoboy' ? 'selected' : '' ?>>Recolher física com Motoboy</option>
                            <option value="pdv" <?= ($settings['prescription_mode'] ?? '') == 'pdv' ? 'selected' : '' ?>>Apresentar no Balcão</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">ORS API Key (Opcional para cálculo KM)</label>
                        <input type="password" name="ors_key" value="<?= htmlspecialchars($settings['ors_key'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700">
                    </div>

                    <div class="pt-4 border-t">
                        <label class="block text-slate-500 text-[10px] font-black uppercase mb-2">Cálculo de Frete</label>
                        <select name="freight_type" class="w-full bg-slate-100 border-none rounded-xl py-3 px-4 font-bold text-slate-700 mb-4">
                            <option value="km" <?= ($settings['freight_type'] ?? '') == 'km' ? 'selected' : '' ?>>Por KM Rodado</option>
                            <option value="radius" <?= ($settings['freight_type'] ?? '') == 'radius' ? 'selected' : '' ?>>Por Raio (Linear)</option>
                        </select>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">R$ por KM</label>
                                <input type="number" step="0.01" name="price_per_km" value="<?= $settings['price_per_km'] ?? '0.00' ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl font-bold text-indigo-600">
                            </div>
                            <div>
                                <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Raio Máx (KM)</label>
                                <input type="number" name="max_radius" value="<?= $settings['max_radius'] ?? '10' ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl font-bold">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-500 text-[10px] font-black uppercase mb-1">Bairros Grátis (Vírgula)</label>
                        <input type="text" name="free_neighborhoods" value="<?= htmlspecialchars($settings['free_neighborhoods'] ?? '') ?>" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm">
                    </div>

                    <input type="hidden" name="fixed_neighborhoods" value="<?= htmlspecialchars($settings['fixed_neighborhoods'] ?? '{}') ?>">
                    <input type="hidden" name="logo_url" value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>">
                    <input type="hidden" name="footer_message" value="<?= htmlspecialchars($settings['footer_message'] ?? '') ?>">
                </div>
            </div>

            <div class="mt-10">
                <button type="submit" name="save_settings" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-5 rounded-3xl shadow-xl transition transform active:scale-[0.98] uppercase tracking-widest">
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>

    <script>
        async function searchAddress() {
            const cep = document.getElementById('cep_input').value.replace(/\D/g, '');
            if (cep.length !== 8) return alert('CEP Inválido');

            try {
                const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                const data = await response.json();

                if (data.erro) {
                    alert('CEP não encontrado');
                    return;
                }

                document.getElementById('street').value = data.logradouro;
                document.getElementById('neighborhood').value = data.bairro;
                document.getElementById('city').value = data.localidade;
                document.getElementById('state').value = data.uf;

                // Busca Lat/Long automática baseada no endereço
                const fullAddress = `${data.logradouro}, ${data.localidade}, ${data.uf}, Brazil`;
                getCoordinates(fullAddress);

            } catch (e) {
                alert('Erro ao buscar CEP');
            }
        }

        async function getCoordinates(address) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`);
                const data = await response.json();
                if (data.length > 0) {
                    document.getElementById('store_lat').value = data[0].lat;
                    document.getElementById('store_lng').value = data[0].lon;
                }
            } catch (e) {
                console.error("Erro na geolocalização", e);
            }
        }
    </script>
</body>
</html>