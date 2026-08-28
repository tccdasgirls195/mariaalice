<?php
// 1. Inicia a sessão PHP
session_start();

// 2. Trava de segurança da sessão
if (!isset($_SESSION['usuario_id'])) {
   header("Location: login.php");
   exit();
}

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

// Captura Mês e Ano definidos na URL (se não informados, pega o mês/ano atual)
$mes_atual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_atual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// Garante que o mês permaneça dentro dos limites de 1 a 12
if ($mes_atual < 1) {
    $mes_atual = 12;
    $ano_atual--;
} elseif ($mes_atual > 12) {
    $mes_atual = 1;
    $ano_atual++;
}

// Processa a Inserção de Novo Evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_evento'])) {
    $nome = mysqli_real_escape_string($conexao, $_POST['nome']);
    $data_evento = mysqli_real_escape_string($conexao, $_POST['data_evento']);
    $tipo = mysqli_real_escape_string($conexao, $_POST['tipo']);
    $id_turma = (int)$_POST['id_turma'];

    if (!empty($nome) && !empty($data_evento) && !empty($id_turma)) {
        $sql_evento = "INSERT INTO eventos (nome, descr, data_evento, tipo) VALUES ('$nome', '$nome', '$data_evento', '$tipo')";
        if (mysqli_query($conexao, $sql_evento)) {
            $id_evento = mysqli_insert_id($conexao);
            $sql_cal = "INSERT INTO calendario (id_eventos, id_turma) VALUES ($id_evento, $id_turma)";
            mysqli_query($conexao, $sql_cal);
            
            header("Location: calendario.php?turma=" . $id_turma . "&mes=" . $mes_atual . "&ano=" . $ano_atual);
            exit();
        }
    }
}

// Processa a Exclusão do Evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_evento'])) {
    $id_evento_del = (int)$_POST['id_evento'];
    
    // Deleta do calendário e depois do evento
    mysqli_query($conexao, "DELETE FROM calendario WHERE id_eventos = $id_evento_del");
    mysqli_query($conexao, "DELETE FROM eventos WHERE id_eventos = $id_evento_del");

    header("Location: calendario.php?turma=" . $id_turma_selecionada . "&mes=" . $mes_atual . "&ano=" . $ano_atual);
    exit();
}

// Processa a Edição do Evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_evento'])) {
    $id_evento_edit = (int)$_POST['id_evento'];
    $nome_edit = mysqli_real_escape_string($conexao, $_POST['nome']);
    $tipo_edit = mysqli_real_escape_string($conexao, $_POST['tipo']);

    if (!empty($nome_edit)) {
        $sql_update = "UPDATE eventos SET nome = '$nome_edit', descr = '$nome_edit', tipo = '$tipo_edit' WHERE id_eventos = $id_evento_edit";
        mysqli_query($conexao, $sql_update);

        header("Location: calendario.php?turma=" . $id_turma_selecionada . "&mes=" . $mes_atual . "&ano=" . $ano_atual);
        exit();
    }
}

// Buscar Eventos da Turma Selecionada
$eventos_cadastrados = [];
$nome_turma_atual = "";

if ($id_turma_selecionada) {
    $sql_t_atual = "SELECT serie, curso FROM turma WHERE id_turma = $id_turma_selecionada";
    $res_t_atual = mysqli_query($conexao, $sql_t_atual);
    if ($row_t = mysqli_fetch_assoc($res_t_atual)) {
        $nome_turma_atual = $row_t['serie'] . " " . $row_t['curso'];
    }

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
    // Se o período for 'N', vai para Noturno. Qualquer outro valor ('I' ou nulo) vai para Integral
    if (strtoupper($t['periodo']) === 'N') { 
        $turmas_noturno[] = $t;
    } else {
        $turmas_integral[] = $t;
    }
}

// Cálculos para montar a grade dinamicamente do mês escolhido
$dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_atual, $ano_atual);

// Identifica o dia da semana em que o dia 01 cai (1 = Segunda, 7 = Domingo)
$primeiro_dia_semana = (int)date('N', strtotime(sprintf('%04d-%02d-01', $ano_atual, $mes_atual)));

// Nomes dos Meses em Português
$meses_nome = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

// URLs para navegação de anterior e próximo mês
$mes_anterior = $mes_atual - 1;
$ano_anterior = $ano_atual;
if ($mes_anterior < 1) { $mes_anterior = 12; $ano_anterior--; }

$mes_proximo = $mes_atual + 1;
$ano_proximo = $ano_atual;
if ($mes_proximo > 12) { $mes_proximo = 1; $ano_proximo++; }

$param_turma = $id_turma_selecionada ? "&turma=" . $id_turma_selecionada : "";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário de Eventos - Etec</title>

    <link rel="stylesheet" href="../css/calendario.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<header>
    <div class="logo">
        <img src="../logo.png" alt="Logo Etec">
    </div>

    <nav>
        <a href="">Home</a>
        <a href="#" class="has-submenu">Cursos</a>
        <a href="#" class="has-submenu">A Etec</a>
        <a href="#" class="has-submenu">Equipe Etec</a>
        
        <li>
            <a href="../selecionar_lab.html" class="has-submenu">Agendamento</a>
            <ul class="submenu">
                <li><a href="meus-agendamentos.php">Meus agendamentos</a></li>
            </ul>
        </li>

        <a href="#" class="has-submenu">Notícias</a>
        <a href="">Empregos & Estágios</a>
        <a href="">Parceiros</a>
        <a href="">TCC</a>
    </nav>

    <div class="menu">
        <form action="calendario.php" method="POST" class="form-logout"> 
            <input type="hidden" name="logout" value="1">
            <button type="submit" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    </div>
</header>

<!-- Banner Superior com Seletor de Período/Turma -->
<section class="dropdown-container">
    <button class="btn-dropdown">
        <?= $nome_turma_atual ? "Eventos - " . htmlspecialchars($nome_turma_atual) : "Eventos" ?>
        <i class="fa-solid fa-chevron-down"></i>
    </button>

    <div class="dropdown-menu">
        <!-- INTEGRAL -->
        <div class="menu-item-periodo">
            <span>Integral</span>
            <i class="fa-solid fa-chevron-right seta"></i>
            <div class="submenu-turmas">
                <?php foreach ($turmas_integral as $t): ?>
                    <a href="calendario.php?turma=<?= $t['id_turma'] ?>&mes=<?= $mes_atual ?>&ano=<?= $ano_atual ?>">
                        <?= htmlspecialchars($t['serie'] . ' ' . $t['curso']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- NOTURNO -->
        <div class="menu-item-periodo">
            <span>Noturno</span>
            <i class="fa-solid fa-chevron-right seta"></i>
            <div class="submenu-turmas">
                <?php foreach ($turmas_noturno as $t): ?>
                    <a href="calendario.php?turma=<?= $t['id_turma'] ?>&mes=<?= $mes_atual ?>&ano=<?= $ano_atual ?>">
                        <?= htmlspecialchars($t['serie'] . ' ' . $t['curso']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Estrutura Principal do Calendário -->
<div class="container">

    <!-- Barra de Navegação entre os Meses do Ano -->
    <div class="calendar-month-nav">
        <a href="calendario.php?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?><?= $param_turma ?>" class="nav-month-btn">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <h2 class="calendar-month-title"><?= $meses_nome[$mes_atual] . " " . $ano_atual ?></h2>
        <a href="calendario.php?mes=<?= $mes_proximo ?>&ano=<?= $ano_proximo ?><?= $param_turma ?>" class="nav-month-btn">
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </div>

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
            // 1. Células vazias de offset (alinha o dia 1 ao dia correto da semana)
            for ($offset = 1; $offset < $primeiro_dia_semana; $offset++): 
            ?>
                <div class="calendar-day empty-day"></div>
            <?php endfor; ?>

            <?php 
            // 2. Renderização dos dias do mês
            for ($dia = 1; $dia <= $dias_no_mes; $dia++): 
                $data_formatada = sprintf("%04d-%02d-%02d", $ano_atual, $mes_atual, $dia);
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
    <div class="modal-card modal-card-centered">
        <!-- Botão fechar -->
        <button class="btn-close-corner" onclick="fecharModais()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <br>
        <h3>Selecione uma Turma!</h3>
        <br>
        <p>Por favor, selecione primeiro uma turma no menu <strong>"Eventos"</strong> para interagir com o calendário.</p>

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
    <div class="modal-card modal-card-centered">
        <p class="txt-confirmacao">Tem certeza que deseja adicionar o evento?</p>
        
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

<!-- Modal 3: Visualizar Evento Existente com Ações -->
<div class="modal-overlay" id="modalVer">
    <div class="modal-card">
        <button class="btn-close-corner" onclick="fecharModais()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <br>
        <div class="ver-evento-box">
            <h3 id="verNome" class="ver-nome"></h3>
            <p id="verData" class="ver-data"></p>
        </div>

        <div id="verTipoContainer" class="ver-tipo-container"></div>

        <!-- Botões de Ação (Editar e Excluir) -->
        <div class="modal-actions ver-actions">
            <button type="button" class="btn-action btn-edit" onclick="abrirModalEditar()">
                <i class="fa-solid fa-pen"></i> Editar
            </button>

            <form method="POST" class="form-inline" onsubmit="return confirm('Tem certeza que deseja excluir este evento?');">
                <input type="hidden" name="excluir_evento" value="1">
                <input type="hidden" name="id_evento" id="delIdEvento">
                <button type="submit" class="btn-cancel btn-delete">
                    <i class="fa-solid fa-trash"></i> Excluir
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Editar Evento -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal-card">
        <button class="btn-close-corner" onclick="fecharModais()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <br>
        <form method="POST">
            <input type="hidden" name="editar_evento" value="1">
            <input type="hidden" name="id_evento" id="editIdEvento">

            <textarea id="editNome" name="nome" placeholder="Descrição do evento.."></textarea>

            <div class="radio-options">
                <label>
                    <input type="radio" name="tipo" id="editTipoProva" value="Prova"> 
                    <span class="dot dot-red"></span> Prova
                </label>
                <label>
                    <input type="radio" name="tipo" id="editTipoTrabalho" value="Trabalho"> 
                    <span class="dot dot-yellow"></span> Trabalho
                </label>
                <label>
                    <input type="radio" name="tipo" id="editTipoEvento" value="Evento"> 
                    <span class="dot dot-blue"></span> Evento
                </label>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn-action">Salvar</button>
                <button type="button" class="btn-cancel" onclick="fecharModais()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
let turmaSelecionada = <?= json_encode($id_turma_selecionada) ?>;
let dataSelecionada = null;
let eventoAtual = null; // Declarada no escopo global para o editor acessar

function clicarDia(data, evento) {
    if (!turmaSelecionada) {
        document.getElementById('modalAviso').style.display = 'flex';
        return;
    }

    dataSelecionada = data;

    if (evento) {
        eventoAtual = evento;
        document.getElementById('verNome').innerText = evento.nome;
        
        const partesData = evento.data_evento.split('-');
        const dataFormatada = `${partesData[2]}/${partesData[1]}/${partesData[0]}`;
        
        document.getElementById('verData').innerText = "Data: " + dataFormatada;
        
        let dotClass = '';
        if (evento.tipo === 'Prova') dotClass = 'dot-red-solid';
        else if (evento.tipo === 'Trabalho') dotClass = 'dot-yellow-solid';
        else if (evento.tipo === 'Evento') dotClass = 'dot-blue-solid';

        document.getElementById('verTipoContainer').innerHTML = `
            <span class="dot ${dotClass}"></span>
            <span>${evento.tipo}</span>
        `;
        
        // Atribui o ID ao form de exclusão
        document.getElementById('delIdEvento').value = evento.id_eventos;

        document.getElementById('modalVer').style.display = 'flex';
    } else {
        document.getElementById('tempNome').value = '';
        document.getElementById('alertaForm').style.display = 'none';
        document.getElementById('modalCriar').style.display = 'flex';
    }
}

function abrirModalEditar() {
    if (!eventoAtual) return;

    document.getElementById('editIdEvento').value = eventoAtual.id_eventos;
    document.getElementById('editNome').value = eventoAtual.nome;

    if (eventoAtual.tipo === 'Prova') document.getElementById('editTipoProva').checked = true;
    else if (eventoAtual.tipo === 'Trabalho') document.getElementById('editTipoTrabalho').checked = true;
    else if (eventoAtual.tipo === 'Evento') document.getElementById('editTipoEvento').checked = true;

    document.getElementById('modalVer').style.display = 'none';
    document.getElementById('modalEditar').style.display = 'flex';
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

window.onpageshow = function(event) {
    if (event.persisted || (performance && performance.navigation.type === 2)) {
        document.body.innerHTML = '';
        window.location.replace("login.php");
    }
};
</script>

</body>
</html>