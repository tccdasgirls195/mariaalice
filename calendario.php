<?php
// 1. Inicia a sessão PHP
session_start();

// 2. Trava de segurança da sessão
//if (!isset($_SESSION['usuario_id'])) {
   // header("Location: login.php");
    //exit();
//}

// Cabeçalhos Anti-Cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Processa o Logout via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

include("conexao.php");

// Captura a turma selecionada via URL
$id_turma_selecionada = isset($_GET['turma']) ? (int)$_GET['turma'] : null;

// Processa a Inserção de Novo Evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_evento'])) {
    $nome = mysqli_real_escape_string($conexao, $_POST['nome']);
    $data_evento = mysqli_real_escape_string($conexao, $_POST['data_evento']);
    $tipo = mysqli_real_escape_string($conexao, $_POST['tipo']);
    $id_turma = (int)$_POST['id_turma'];

    if (!empty($nome) && !empty($data_evento) && !empty($id_turma)) {
        // Insere na tabela eventos
        $sql_evento = "INSERT INTO eventos (nome, descr, data_evento, tipo) VALUES ('$nome', '$nome', '$data_evento', '$tipo')";
        if (mysqli_query($conexao, $sql_evento)) {
            $id_evento = mysqli_insert_id($conexao);
            // Relaciona no calendário com a turma
            $sql_cal = "INSERT INTO calendario (id_eventos, id_turma) VALUES ($id_evento, $id_turma)";
            mysqli_query($conexao, $sql_cal);
            
            header("Location: calendario.php?turma=" . $id_turma);
            exit();
        }
    }
}

// Buscar Eventos da Turma Selecionada
$eventos_cadastrados = [];
$nome_turma_atual = "";

if ($id_turma_selecionada) {
    // Nome da turma atual
    $sql_t_atual = "SELECT serie, curso FROM turma WHERE id_turma = $id_turma_selecionada";
    $res_t_atual = mysqli_query($conexao, $sql_t_atual);
    if ($row_t = mysqli_fetch_assoc($res_t_atual)) {
        $nome_turma_atual = $row_t['serie'] . " " . $row_t['curso'];
    }

    // Busca os eventos
    $sql_busca = "SELECT e.* FROM eventos e 
                  INNER JOIN calendario c ON e.id_eventos = c.id_eventos 
                  WHERE c.id_turma = $id_turma_selecionada";
    $res = mysqli_query($conexao, $sql_busca);
    while ($row = mysqli_fetch_assoc($res)) {
        $eventos_cadastrados[$row['data_evento']] = $row;
    }
}

// Buscar turmas cadastradas para alimentar os submenus
$sql_turmas = "SELECT * FROM turma ORDER BY serie ASC, curso ASC";
$result_turmas = mysqli_query($conexao, $sql_turmas);

$turmas_integral = [];
$turmas_noturno = [];

while ($t = mysqli_fetch_assoc($result_turmas)) {
    // Separação lógica para exibição nos submenus (Noturno vs Integral)
    if ($t['curso'] == 'RH') { 
        $turmas_noturno[] = $t;
    } else {
        $turmas_integral[] = $t;
    }
}

// Definição de Mês/Ano para a grade
$mes_atual = date('m');
$ano_atual = date('Y');
$dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_atual, $ano_atual);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário de Eventos - Etec</title>

    <link rel="stylesheet" href="calendario.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<header>
    <div class="logo">
        <img src="logo.png" alt="Logo Etec">
    </div>

    <nav>
        <a href="home.php">Home</a>
        <a href="">Cursos</a>
        <a href="">A Etec</a>
    </nav>

    <div class="menu">
        <form action="calendario.php" method="POST" style="display:inline;"> 
            <input type="hidden" name="logout" value="1">
            <button type="submit" style="background:none; border:none; color:#ff4d4d; cursor:pointer; font-size: 24px;">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    </div>
</header>

<!-- Banner Superior com Seletor de Período/Turma -->
<section class="dropdown-container">
    <button class="btn-dropdown">
        <?= $nome_turma_atual ? "Eventos - " . htmlspecialchars($nome_turma_atual) : "Eventos" ?> 
        <i class="fa-solid fa-chevron-down" style="font-size: 24px;"></i>
    </button>
    
    <div class="dropdown-menu">
        <!-- Período Integral -->
        <div class="menu-item-periodo">
            <span>Integral</span>
            <i class="fa-solid fa-chevron-right" style="font-size: 14px;"></i>
            <div class="submenu-turmas">
                <?php foreach ($turmas_integral as $t): ?>
                    <a href="calendario.php?turma=<?= $t['id_turma'] ?>">
                        <?= $t['serie'] . ' ' . $t['curso'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Período Noturno -->
        <div class="menu-item-periodo">
            <span>Noturno</span>
            <i class="fa-solid fa-chevron-right" style="font-size: 14px;"></i>
            <div class="submenu-turmas">
                <?php foreach ($turmas_noturno as $t): ?>
                    <a href="calendario.php?turma=<?= $t['id_turma'] ?>">
                        <?= $t['serie'] . ' ' . $t['curso'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Estrutura Principal do Calendário -->
<div class="container">
    <div class="calendar-box">
        <div class="calendar-header-days">
            <div>Segunda</div>
            <div>Terça</div>
            <div>Quarta</div>
            <div>Quinta</div>
            <div>Sexta</div>
            <div>Sábado</div>
            <div>Domingo</div>
        </div>

        <div class="calendar-grid">
            <?php 
            for ($dia = 1; $dia <= $dias_no_mes; $dia++): 
                $data_formatada = sprintf("%s-%s-%02d", $ano_atual, $mes_atual, $dia);
                $tem_evento = isset($eventos_cadastrados[$data_formatada]);
                $evt = $tem_evento ? $eventos_cadastrados[$data_formatada] : null;
            ?>
                <div class="calendar-day" onclick="clicarDia('<?= $data_formatada ?>', <?= htmlspecialchars(json_encode($evt)) ?>)">
                    <span class="day-number <?= $tem_evento ? 'circled' : '' ?>"><?= $dia ?></span>
                    
                    <?php if ($tem_evento): ?>
                        <div class="tag-event tag-<?= $evt['tipo'] ?>">
                            <?= htmlspecialchars($evt['tipo']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Modal de Alerta: Sem turma selecionada -->
<div class="modal-overlay" id="modalAviso">
    <div class="modal-card" style="text-align: center;">
        <i class="fa-solid fa-circle-exclamation" style="font-size: 40px; color: #e65100; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px; color: #333;">Selecione uma Turma</h3>
        <p style="color: #666; font-size: 16px; margin-bottom: 20px;">Por favor, selecione primeiro uma turma no menu <strong>"Eventos"</strong> para interagir com o calendário.</p>
        <button class="btn-action" onclick="fecharModais()">Entendido</button>
    </div>
</div>

<!-- Modal 1: Criar Evento -->
<div class="modal-overlay" id="modalCriar">
    <div class="modal-card">
        <div id="alertaForm" class="alert-box">Por favor, digite a descrição do evento.</div>

        <textarea id="tempNome" placeholder="Evento, atividade.."></textarea>
        
        <div class="radio-options">
            <label>
                <input type="radio" name="tempTipo" value="Prova" checked> 
                <span class="dot dot-red"></span> Prova
            </label>
            <label>
                <input type="radio" name="tempTipo" value="Trabalho"> 
                <span class="dot dot-yellow"></span> Trabalho
            </label>
            <label>
                <input type="radio" name="tempTipo" value="Evento"> 
                <span class="dot dot-blue"></span> Evento
            </label>
        </div>

        <div class="modal-actions">
            <button class="btn-action" onclick="abrirConfirmacao()">Adicionar</button>
            <button class="btn-cancel" onclick="fecharModais()">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal 2: Confirmar Envio -->
<div class="modal-overlay" id="modalConfirmar">
    <div class="modal-card" style="text-align: center;">
        <p style="font-size: 20px; font-weight: bold; margin-bottom: 20px;">Tem certeza que deseja adicionar o evento?</p>
        
        <form method="POST">
            <input type="hidden" name="salvar_evento" value="1">
            <input type="hidden" name="id_turma" value="<?= $id_turma_selecionada ?>">
            <input type="hidden" name="data_evento" id="finalData">
            <input type="hidden" name="nome" id="finalNome">
            <input type="hidden" name="tipo" id="finalTipo">

            <div class="modal-actions">
                <button type="submit" class="btn-action">Adicionar</button>
                <button type="button" class="btn-cancel" onclick="fecharModais()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Visualizar Evento Existente -->
<div class="modal-overlay" id="modalVer">
    <div class="modal-card">
        <div style="border: 2px solid #2e7d32; border-radius: 12px; padding: 15px; margin-bottom: 15px;">
            <h3 id="verNome" style="margin-bottom: 10px; font-size: 20px;"></h3>
            <p id="verData" style="color: #555;"></p>
        </div>

        <!-- Exibe apenas o tipo selecionado com a bolinha colorida preenchida -->
        <div id="verTipoContainer" style="margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px;"></div>

        <button class="btn-action" onclick="fecharModais()">Fechar</button>
    </div>

<div class="radio-options">
    <label>
        <input type="radio" name="tempTipo" value="Prova" checked> 
        <span class="dot dot-red"></span> Prova
    </label>
    <label>
        <input type="radio" name="tempTipo" value="Trabalho"> 
        <span class="dot dot-yellow"></span> Trabalho
    </label>
    <label>
        <input type="radio" name="tempTipo" value="Evento"> 
        <span class="dot dot-blue"></span> Evento
    </label>
</div>

        <button class="btn-action" onclick="fecharModais()">Fechar</button>
    </div>
</div>

<script>
let turmaSelecionada = <?= json_encode($id_turma_selecionada) ?>;
let dataSelecionada = null;

function clicarDia(data, evento) {
    if (!turmaSelecionada) {
        document.getElementById('modalAviso').style.display = 'flex';
        return;
    }

    dataSelecionada = data;

    if (evento) {
        document.getElementById('verNome').innerText = evento.nome;
        
        // Converte o formato "YYYY-MM-DD" para "DD/MM/YYYY"
        const partesData = evento.data_evento.split('-');
        const dataFormatada = `${partesData[2]}/${partesData[1]}/${partesData[0]}`;
        
        document.getElementById('verData').innerText = "Data: " + dataFormatada;
        
        // Mapeia a cor da bolinha sólida de acordo com o tipo do evento
        let dotClass = '';
        if (evento.tipo === 'Prova') dotClass = 'dot-red-solid';
        else if (evento.tipo === 'Trabalho') dotClass = 'dot-yellow-solid';
        else if (evento.tipo === 'Evento') dotClass = 'dot-blue-solid';

        // Renderiza apenas o tipo do evento clicado
        document.getElementById('verTipoContainer').innerHTML = `
            <span class="dot ${dotClass}"></span>
            <span>${evento.tipo}</span>
        `;
        
        document.getElementById('modalVer').style.display = 'flex';
    } else {
        document.getElementById('tempNome').value = '';
        document.getElementById('alertaForm').style.display = 'none';
        document.getElementById('modalCriar').style.display = 'flex';
    }
}

function abrirConfirmacao() {
    const nome = document.getElementById('tempNome').value;
    if (!nome.trim()) {
        document.getElementById('alertaForm').style.display = 'block';
        return;
    }

    const tipo = document.querySelector('input[name="tempTipo"]:checked').value;

    document.getElementById('finalData').value = dataSelecionada;
    document.getElementById('finalNome').value = nome;
    document.getElementById('finalTipo').value = tipo;

    document.getElementById('modalCriar').style.display = 'none';
    document.getElementById('modalConfirmar').style.display = 'flex';
}

function fecharModais() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.style.display = 'none';
    });
}

// Proteção contra retorno via navegador pós-logout
window.onpageshow = function(event) {
    if (event.persisted || (performance && performance.navigation.type === 2)) {
        document.body.innerHTML = '';
        window.location.replace("login.php");
    }
};
</script>

</body>
</html>