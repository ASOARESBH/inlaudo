<?php
session_start();
require_once 'config.php';
require_once 'funcoes_auxiliares.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$pageTitle = 'Relatórios';

// Verificar se tipo foi passado via GET
$tipoPreSelecionado = $_GET['tipo'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - ERP INLAUDO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            color: #666;
            font-weight: 500;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #c82333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .card h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-icon {
            font-size: 24px;
        }

        .card p {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .btn-relatorio {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            transition: background 0.3s;
            width: 100%;
            text-align: center;
        }

        .btn-relatorio:hover {
            background: #5568d3;
        }

        .filters-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filters-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        .btn-filtrar {
            background: #667eea;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            flex: 1;
        }

        .btn-filtrar:hover {
            background: #5568d3;
        }

        .btn-limpar {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            flex: 1;
        }

        .btn-limpar:hover {
            background: #5a6268;
        }

        .results-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: none;
        }

        .results-section.show {
            display: block;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .results-header h2 {
            color: #667eea;
            font-size: 22px;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-export {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-pdf {
            background: #dc3545;
            color: white;
        }

        .btn-pdf:hover {
            background: #c82333;
        }

        .btn-excel {
            background: #28a745;
            color: white;
        }

        .btn-excel:hover {
            background: #218838;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table thead {
            background: #667eea;
            color: white;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card h4 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card p {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: white;
        }

        .back-link {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <a href="index.php" class="back-link">← Voltar ao Dashboard</a>
                <h1>📊 <?php echo $pageTitle; ?></h1>
            </div>
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($usuario_nome); ?></span>
                <a href="logout.php" class="btn-logout">Sair</a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <h2>🔍 Filtros de Pesquisa</h2>
            <form id="formFiltros" class="filters-form">
                <div class="form-group">
                    <label for="data_inicial">Data Inicial</label>
                    <input type="date" id="data_inicial" name="data_inicial" value="<?php echo date('Y-m-01'); ?>">
                </div>

                <div class="form-group">
                    <label for="data_final">Data Final</label>
                    <input type="date" id="data_final" name="data_final" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="tipo_relatorio">Tipo de Relatório</label>
                    <select id="tipo_relatorio" name="tipo_relatorio" required>
                        <option value="">Selecione...</option>
                        <option value="clientes" <?php echo $tipoPreSelecionado === 'clientes' ? 'selected' : ''; ?>>Clientes</option>
                        <option value="contratos" <?php echo $tipoPreSelecionado === 'contratos' ? 'selected' : ''; ?>>Contratos</option>
                        <option value="contas_pagar" <?php echo $tipoPreSelecionado === 'contas_pagar' ? 'selected' : ''; ?>>Contas a Pagar</option>
                        <option value="contas_vencer" <?php echo $tipoPreSelecionado === 'contas_vencer' ? 'selected' : ''; ?>>Contas a Vencer</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="btn-group">
                        <button type="submit" class="btn-filtrar">🔍 Gerar Relatório</button>
                        <button type="button" class="btn-limpar" onclick="limparFiltros()">🔄 Limpar</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Cards de Relatórios -->
        <div class="cards-grid">
            <div class="card">
                <h3><span class="card-icon">👥</span> Relatório de Clientes</h3>
                <p>Lista completa de clientes cadastrados com informações de contato, documentos e status.</p>
                <button class="btn-relatorio" onclick="selecionarRelatorio('clientes')">Gerar Relatório</button>
            </div>

            <div class="card">
                <h3><span class="card-icon">📄</span> Relatório de Contratos</h3>
                <p>Contratos ativos e inativos com valores, datas de vigência e informações dos clientes.</p>
                <button class="btn-relatorio" onclick="selecionarRelatorio('contratos')">Gerar Relatório</button>
            </div>

            <div class="card">
                <h3><span class="card-icon">💰</span> Contas a Pagar</h3>
                <p>Todas as contas a pagar com status, valores, datas de vencimento e fornecedores.</p>
                <button class="btn-relatorio" onclick="selecionarRelatorio('contas_pagar')">Gerar Relatório</button>
            </div>

            <div class="card">
                <h3><span class="card-icon">⏰</span> Contas a Vencer</h3>
                <p>Contas a receber pendentes com datas de vencimento próximas para acompanhamento.</p>
                <button class="btn-relatorio" onclick="selecionarRelatorio('contas_vencer')">Gerar Relatório</button>
            </div>
        </div>

        <!-- Resultados -->
        <div class="results-section" id="resultsSection">
            <div class="results-header">
                <h2 id="resultsTitle">Resultados</h2>
                <div class="export-buttons">
                    <a href="#" class="btn-export btn-pdf" id="btnExportPDF">📄 Exportar PDF</a>
                    <a href="#" class="btn-export btn-excel" id="btnExportExcel">📊 Exportar Excel</a>
                </div>
            </div>

            <div id="statsContainer"></div>

            <div class="table-responsive">
                <div id="tableContainer"></div>
            </div>
        </div>
    </div>

    <script>
        // Auto-submit se tipo foi pré-selecionado via GET
        window.addEventListener('DOMContentLoaded', function() {
            const tipoPreSelecionado = '<?php echo $tipoPreSelecionado; ?>';
            if (tipoPreSelecionado) {
                // Aguardar 500ms para garantir que página carregou
                setTimeout(function() {
                    document.getElementById('formFiltros').dispatchEvent(new Event('submit'));
                }, 500);
            }
        });

        // Selecionar relatório pelos cards
        function selecionarRelatorio(tipo) {
            document.getElementById('tipo_relatorio').value = tipo;
            document.getElementById('formFiltros').scrollIntoView({ behavior: 'smooth' });
        }

        // Limpar filtros
        function limparFiltros() {
            document.getElementById('formFiltros').reset();
            document.getElementById('data_inicial').value = '<?php echo date('Y-m-01'); ?>';
            document.getElementById('data_final').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('resultsSection').classList.remove('show');
        }

        // Gerar relatório
        document.getElementById('formFiltros').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const tipo = formData.get('tipo_relatorio');
            const dataInicial = formData.get('data_inicial');
            const dataFinal = formData.get('data_final');

            if (!tipo) {
                alert('Por favor, selecione um tipo de relatório');
                return;
            }

            try {
                const response = await fetch('gerar_relatorio.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    mostrarResultados(data);
                    
                    // Atualizar links de exportação
                    const params = new URLSearchParams({
                        tipo: tipo,
                        data_inicial: dataInicial,
                        data_final: dataFinal
                    });
                    
                    document.getElementById('btnExportPDF').href = 'exportar_relatorio_pdf.php?' + params.toString();
                    document.getElementById('btnExportExcel').href = 'exportar_relatorio_excel.php?' + params.toString();
                } else {
                    alert('Erro ao gerar relatório: ' + data.message);
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao gerar relatório. Tente novamente.');
            }
        });

        // Mostrar resultados
        function mostrarResultados(data) {
            const resultsSection = document.getElementById('resultsSection');
            const resultsTitle = document.getElementById('resultsTitle');
            const statsContainer = document.getElementById('statsContainer');
            const tableContainer = document.getElementById('tableContainer');

            resultsTitle.textContent = data.titulo;

            // Estatísticas
            if (data.stats) {
                let statsHtml = '<div class="stats-grid">';
                for (const [key, value] of Object.entries(data.stats)) {
                    statsHtml += `
                        <div class="stat-card">
                            <h4>${key}</h4>
                            <p>${value}</p>
                        </div>
                    `;
                }
                statsHtml += '</div>';
                statsContainer.innerHTML = statsHtml;
            } else {
                statsContainer.innerHTML = '';
            }

            // Tabela
            if (data.dados && data.dados.length > 0) {
                let tableHtml = '<table><thead><tr>';
                
                // Cabeçalhos
                for (const col of data.colunas) {
                    tableHtml += `<th>${col}</th>`;
                }
                tableHtml += '</tr></thead><tbody>';

                // Dados
                for (const row of data.dados) {
                    tableHtml += '<tr>';
                    for (const value of Object.values(row)) {
                        tableHtml += `<td>${value || '-'}</td>`;
                    }
                    tableHtml += '</tr>';
                }

                tableHtml += '</tbody></table>';
                tableContainer.innerHTML = tableHtml;
            } else {
                tableContainer.innerHTML = '<div class="no-data">Nenhum registro encontrado para os filtros selecionados.</div>';
            }

            resultsSection.classList.add('show');
            resultsSection.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
