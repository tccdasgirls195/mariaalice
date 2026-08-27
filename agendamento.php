
<?php
// ==========================================================
// 1. INICIA A SESSÃO
// ==========================================================
session_start();

// ==========================================================
// 2. VERIFICA SE O USUÁRIO ESTÁ LOGADO
// ==========================================================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// ==========================================================
// 3. VERIFICA O TIPO DE USUÁRIO
// Podem acessar o agendamento:
// professor, administrador, coordenador e gestão
// ==========================================================
$tiposPermitidos = [
    'professor',
    'administrador',
    'coordenador',
    'gestao'
];

if (!in_array($_SESSION['usuario_tipo'], $tiposPermitidos)) {
    header("Location: login.php");
    exit();
}

// ==========================================================
// 4. LOGOUT
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

// ==========================================================
// 5. CABEÇALHOS ANTI-CACHE
// ==========================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// ==========================================================
// 6. CONEXÃO COM O BANCO
// ==========================================================
include("conexao.php");

// ==========================================================
// MENSAGEM DE SUCESSO APÓS O AGENDAMENTO
// ==========================================================
$mensagem = "";

if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
    $mensagem = "Agendamento enviado com sucesso!";
}

$erro = "";
$ocupados = [];

// ==========================================================
// 7. DESCOBRE QUEM ESTÁ LOGADO
// ==========================================================
$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];

// ==========================================================
// 8. BUSCA O NOME DO USUÁRIO LOGADO
// ==========================================================

$nome_usuario = "";

$tabelasUsuarios = [
    'professor' => [
        'tabela' => 'professor',
        'id' => 'id_professor'
    ],
    'coordenador' => [
        'tabela' => 'coordenador',
        'id' => 'id_coordenador'
    ],
    'administrador' => [
        'tabela' => 'administrador',
        'id' => 'id_administrador'
    ],
    'gestao' => [
        'tabela' => 'gestao',
        'id' => 'id_gestao'
    ]
];

if (isset($tabelasUsuarios[$usuario_tipo])) {

    $tabela = $tabelasUsuarios[$usuario_tipo]['tabela'];
    $colunaId = $tabelasUsuarios[$usuario_tipo]['id'];

    $sqlUsuario = "SELECT nome FROM $tabela WHERE $colunaId = ?";

    $stmtUsuario = mysqli_prepare($conexao, $sqlUsuario);

    if ($stmtUsuario) {

        mysqli_stmt_bind_param(
            $stmtUsuario,
            "i",
            $usuario_id
        );

        mysqli_stmt_execute($stmtUsuario);

        $resultadoUsuario = mysqli_stmt_get_result($stmtUsuario);

        if ($linhaUsuario = mysqli_fetch_assoc($resultadoUsuario)) {
            $nome_usuario = $linhaUsuario['nome'];
        }

        mysqli_stmt_close($stmtUsuario);
    }
}

// ==========================================================
// 9. BUSCA O ID DA GESTÃO
// ==========================================================
// A Gestão é representada pela conta institucional
// gestao@email.com
// ==========================================================

$id_gestao = null;

$sqlGestao = "
    SELECT id_gestao
    FROM gestao
    WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
    LIMIT 1
";

$emailGestao = "gestao@email.com";

$stmtGestao = mysqli_prepare($conexao, $sqlGestao);

if ($stmtGestao) {

    mysqli_stmt_bind_param(
        $stmtGestao,
        "s",
        $emailGestao
    );

    mysqli_stmt_execute($stmtGestao);

    $resultadoGestao = mysqli_stmt_get_result($stmtGestao);

    if ($linhaGestao = mysqli_fetch_assoc($resultadoGestao)) {
        $id_gestao = $linhaGestao['id_gestao'];
    }

    mysqli_stmt_close($stmtGestao);
}

// ==========================================================
// 10. PROCESSA O FORMULÁRIO DE AGENDAMENTO
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_agendamento'])) {

    $id_ambientes = isset($_POST['id_ambientes'])
        ? intval($_POST['id_ambientes'])
        : 0;

    $data_agendamento = isset($_POST['data_agendamento'])
        ? trim($_POST['data_agendamento'])
        : "";

    $horario = isset($_POST['horario'])
        ? trim($_POST['horario'])
        : "";

    $descr = isset($_POST['descr'])
        ? trim($_POST['descr'])
        : "";

    // ------------------------------------------------------
    // VALIDAÇÕES
    // ------------------------------------------------------

    if ($id_ambientes <= 0) {

        $erro = "Selecione um laboratório.";

    } elseif (empty($data_agendamento)) {

        $erro = "Selecione uma data.";

    } elseif (empty($horario)) {

        $erro = "Selecione um horário.";

    } elseif (empty($descr)) {

        $erro = "Digite uma descrição para o agendamento.";

    } elseif ($id_gestao === null) {

        $erro = "Não foi possível localizar a Gestão no sistema.";

    } elseif (empty($nome_usuario)) {

        $erro = "Não foi possível identificar o usuário logado.";

    } else {

        // --------------------------------------------------
        // VERIFICA SE O LABORATÓRIO JÁ ESTÁ OCUPADO
        // --------------------------------------------------

        $sqlVerifica = "
            SELECT id_agendamentos
            FROM agendamentos
            WHERE id_ambientes = ?
            AND data_agendamento = ?
            AND horario = ?
            LIMIT 1
        ";

        $stmtVerifica = mysqli_prepare(
            $conexao,
            $sqlVerifica
        );

        if ($stmtVerifica) {

            mysqli_stmt_bind_param(
                $stmtVerifica,
                "iss",
                $id_ambientes,
                $data_agendamento,
                $horario
            );

            mysqli_stmt_execute($stmtVerifica);

            $resultadoVerifica = mysqli_stmt_get_result(
                $stmtVerifica
            );

            if (mysqli_num_rows($resultadoVerifica) > 0) {

                $erro = "Este laboratório já está ocupado nessa data e horário.";

            }

            mysqli_stmt_close($stmtVerifica);

        } else {

            $erro = "Erro ao verificar disponibilidade.";
        }


        // --------------------------------------------------
        // SE ESTIVER LIVRE, SALVA O AGENDAMENTO
        // --------------------------------------------------

        if (empty($erro)) {

            /*
             * Se for professor, também preenche id_professor.
             *
             * Para coordenador, administrador e gestão,
             * id_professor ficará NULL.
             */

            $id_professor = null;

            if ($usuario_tipo === 'professor') {
                $id_professor = $usuario_id;
            }


            // --------------------------------------------------
            // INSERT
            // --------------------------------------------------

            $sqlInsert = "
                INSERT INTO agendamentos
                (
                    nome_prof,
                    descr,
                    data_agendamento,
                    id_gestao,
                    id_professor,
                    id_ambientes,
                    horario,
                    solicitante_id,
                    solicitante_tipo
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmtInsert = mysqli_prepare(
                $conexao,
                $sqlInsert
            );

            if ($stmtInsert) {

                mysqli_stmt_bind_param(
                    $stmtInsert,
                    "sssiiisis",
                    $nome_usuario,
                    $descr,
                    $data_agendamento,
                    $id_gestao,
                    $id_professor,
                    $id_ambientes,
                    $horario,
                    $usuario_id,
                    $usuario_tipo
                );

                if (mysqli_stmt_execute($stmtInsert)) {

    header("Location: agendamento.php?sucesso=1");
    exit();

}

                    $mensagem = "Agendamento enviado com sucesso!";

                    /*
                     * Limpa os valores do formulário
                     * depois de salvar.
                     */

                    $_POST = [];

                } else {

                    $erro = "Erro ao salvar o agendamento: " .
                            mysqli_stmt_error($stmtInsert);
                }

                mysqli_stmt_close($stmtInsert);

            } else {

                $erro = "Erro ao preparar o agendamento: " .
                        mysqli_error($conexao);
            }
        }
    }



// ==========================================================
// 11. BUSCA LABORATÓRIOS OCUPADOS
// ==========================================================

if (
    isset($_GET["data"]) &&
    isset($_GET["horario"]) &&
    !empty($_GET["data"]) &&
    !empty($_GET["horario"])
) {

    $data = $_GET["data"];
    $horarioSelecionado = $_GET["horario"];

    $sqlOcupados = "
        SELECT id_ambientes
        FROM agendamentos
        WHERE data_agendamento = ?
        AND horario = ?
    ";

    $stmtOcupados = mysqli_prepare(
        $conexao,
        $sqlOcupados
    );

    if ($stmtOcupados) {
        mysqli_stmt_bind_param(
            $stmtOcupados,
            "ss",
            $data,
            $horarioSelecionado
        );
        mysqli_stmt_execute($stmtOcupados);

        $resultadoOcupados = mysqli_stmt_get_result(
            $stmtOcupados
        );
        while ($linha = mysqli_fetch_assoc($resultadoOcupados)) {
            $ocupados[] = $linha["id_ambientes"];
        }
        mysqli_stmt_close($stmtOcupados);
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>Agendamento</title>

<link
    rel="stylesheet"
    href="../css/agendamento.css">

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>

<body>

<header>
    <div class="logo">
        <img src="../logo.png">
    </div>

    <nav>
        <a href="">Home</a>
        <a href="#" class="has-submenu">
            Cursos
        </a>
        <a href="#" class="has-submenu">
            A Etec
        </a>
        <a href="#" class="has-submenu">
            Equipe Etec
        </a>
        <li>
            <a
                href="../selecionar_lab.html"
                class="has-submenu">
                Agendamento
            </a>
            <ul class="submenu">
                <li>
                    <a href="meus-agendamentos.php">
                        Meus agendamentos
                    </a>
                </li>
            </ul>
        </li>
        <a href="#" class="has-submenu">Notícias</a>
        <a href="">Empregos & Estágios</a>
        <a href="">Parceiros</a>
        <a href=""> TCC</a>

    </nav>

    <!-- BOTÕES DA BARRA LATERAL -->
    <div class="acoes-header">

        <button
            type="button"
            class="botao-header botao-menu"
            aria-label="Abrir menu lateral"
            onclick="abrirBarraLateral()">
            <i class="fa-solid fa-bars"></i>
        </button>

    </div>

</header>


<section class="titulo">
    <h1>Agendamento - Laboratórios DS</h1>
</section>


<!-- =====================================================
     BARRA LATERAL
====================================================== -->

<div
    id="fundoBarraLateral"
    class="fundo-barra"
    onclick="fecharBarraLateral()">
</div>

<aside id="barraLateral" class="barra-lateral">

    <div class="barra-topo"></div>

    <!-- OPÇÃO FIXA DE CIMA -->
    <div class="barra-opcao-fixa">

        <?php if ($usuario_tipo === 'coordenador'): ?>

            <a href="#" class="opcao-lateral">
                <i class="fa-solid fa-user-plus"></i>
                <span>Cadastro representante</span>
            </a>

        <?php else: ?>

            <a href="#" class="opcao-lateral">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Solicitações</span>
            </a>

        <?php endif; ?>

    </div>


    <!-- NOTIFICAÇÕES: ESTA ÁREA ROLA -->
    <div class="area-notificacoes">

        <h2>Notificações</h2>

        <div class="lista-notificacoes">

            <div class="notificacao">
                <i class="fa-solid fa-circle-check notificacao-ok"></i>
                <span>Agendamento</span>
            </div>

            <div class="notificacao">
                <i class="fa-solid fa-circle-xmark notificacao-erro"></i>
                <span>Cancelamento</span>
            </div>

            <div class="notificacao">
                <i class="fa-solid fa-circle-xmark notificacao-erro"></i>
                <span>Cancelamento</span>
            </div>

            <div class="notificacao">
                <i class="fa-solid fa-circle-check notificacao-ok"></i>
                <span>Agendamento</span>
            </div>

            <div class="notificacao">
                <i class="fa-solid fa-circle-check notificacao-ok"></i>
                <span>Agendamento</span>
            </div>

            <div class="notificacao">
                <i class="fa-solid fa-circle-check notificacao-ok"></i>
                <span>Agendamento</span>
            </div>

            <div class="notificacao">
                <i class="fa-solid fa-circle-xmark notificacao-erro"></i>
                <span>Cancelamento</span>
            </div>

        </div>

    </div>


    <!-- LOGOUT: FICA SEMPRE EMBAIXO -->
    <div class="barra-logout">

        <form action="agendamento.php" method="POST">

            <input type="hidden" name="logout" value="1">

            <button type="submit">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sair</span>
            </button>

        </form>

    </div>

</aside>

<div class="container">

    <!-- =================================================
         FILTROS
    ================================================== -->

    <div class="filtros">
        <div class="campo">
            <label for="data">Data:</label>
            <input type="date"id="data">
        </div>

        <div class="campo">
            <label for="horario">Horário: </label>

            <div class="campo-horario">

                <select id="horario"name="horario">
                    <option value=""selected disabled></option>
                    <option>7h30 - 8h20</option>
                    <option>8h20 - 9h10</option>
                    <option> 9h10 - 10h</option>
                    <option>10h20 - 11h10</option>
                    <option> 11h10 - 12h </option>
                    <option>13h - 13h50</option>
                    <option>13h50 - 14h40 </option>
                    <option>14h40 - 15h30 </option>
                    <option>15h30 - 16h20 </option>
                    <option>16h20 - 17h10</option>
                    <option>18h - 18h50 </option>
                    <option>18h50 - 19h40 </option>
                    <option> 19h40 - 20h</option>
                    <option>20h - 20h50</option>
                    <option>20h50 - 21h40 </option>
                    <option>21h40 - 22h30</option>
                </select>
                <i class="fa-regular fa-clock"></i>
            </div>
        </div>
    </div>

    <!-- =================================================
         MENSAGENS
    ================================================== -->

    <?php if (!empty($mensagem)): ?>

        <div
            style="
                background:#d4edda;
                color:#155724;
                padding:12px;
                border-radius:8px;
                margin:15px 0;">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($mensagem) ?>
        </div>

    <?php endif; ?>

    <?php if (!empty($erro)): ?>

        <div
            style="
                background:#f8d7da;
                color:#721c24;
                padding:12px;
                border-radius:8px;
                margin:15px 0;">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <div class="conteudo">


        <!-- =================================================
             MAPA DOS LABORATÓRIOS
        ================================================== -->

        <div class="mapa">

            <div
                class="lab <?= in_array(3,$ocupados) ? 'ocupado' : '' ?>"
                onclick="<?= in_array(3,$ocupados) ? '' : 'selecionarLab(3)' ?>">
                LAB 3
            </div>

            <div
                class="lab <?= in_array(4,$ocupados) ? 'ocupado' : '' ?>"
                onclick="<?= in_array(4,$ocupados) ? '' : 'selecionarLab(4)' ?>">
                LAB 4
            </div>

            <div
                class="lab <?= in_array(5,$ocupados) ? 'ocupado' : '' ?>"
                onclick="<?= in_array(5,$ocupados) ? '' : 'selecionarLab(5)' ?>">
                LAB 5
            </div>

            <div
                class="lab <?= in_array(2,$ocupados) ? 'ocupado' : '' ?>"
                onclick="<?= in_array(2,$ocupados) ? '' : 'selecionarLab(2)' ?>">
                LAB 2
            </div>

            <div class="vazio"></div>

            <div
                class="lab <?= in_array(1,$ocupados) ? 'ocupado' : '' ?>"
                onclick="<?= in_array(1,$ocupados) ? '' : 'selecionarLab(1)' ?>">
                LAB 1
            </div>

        </div>

        <!-- =================================================
             FORMULÁRIO
        ================================================== -->

        <div
            id="formReserva"
            style="display:none;">

            <button
                type="button"
                class="fechar-formulario"
                onclick="fecharFormulario()">
                &times;
            </button>

            <h2>
                Solicitar reserva
            </h2>

            <form
                action="agendamento.php"
                method="POST">

                <!-- LABORATÓRIO -->
                <p id="labEscolhido"></p>

                <!-- DATA -->
                <p id="dataEscolhida"></p>

                <!-- HORÁRIO -->
                <p id="horarioEscolhido"></p>

                <!-- CAMPOS OCULTOS -->
                <input
                    type="hidden"
                    name="id_ambientes"
                    id="id_ambientes">

                <input
                    type="hidden"
                    name="data_agendamento"
                    id="data_agendamento">

                <input
                    type="hidden"
                    name="horario"
                    id="horario_form">

                <!-- NOME -->

                <label>Nome do solicitante:</label>

                <input type="text"value="<?= htmlspecialchars($nome_usuario) ?>"readonly>

                <!-- DESCRIÇÃO -->

                <label>Descrição:</label>

                <textarea
                    name="descr" maxlength="120" required placeholder="Descrição..."></textarea>

                <!-- BOTÃO -->
                <button type="submit" name="enviar_agendamento">
                    Enviar solicitação
                </button>
            </form>
        </div>

        <!-- =================================================
             LEGENDA
        ================================================== -->

        <div class="legenda">
            <h2> Legenda:</h2>

            <div class="item">
                <span class="ocupado"></span>
                Indisponível
            </div>

            <div class="item">
                <span class="livre"></span>
                Disponível
            </div>
        </div>
    </div>
</div>

<script>

// ==========================================================
// RECUPERA ELEMENTOS
// ==========================================================

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const data = document.getElementById("data");
        const horario = document.getElementById("horario");

// Limpa data e horário depois de um agendamento realizado
    const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get("sucesso") === "1") {
        sessionStorage.removeItem("data");
        sessionStorage.removeItem("horario");
}

        // ==================================================
        // ATUALIZA A PÁGINA
        // ==================================================

        function atualizarPagina() {
            if (data.value !== "" && horario.value !== "") {

                sessionStorage.setItem("data",data.value);

                sessionStorage.setItem("horario",horario.value);


                window.location.href ="agendamento.php?data=" +encodeURIComponent(data.value) +"&horario=" + encodeURIComponent(horario.value);
            }
        }

        data.addEventListener("change",atualizarPagina);

        horario.addEventListener("change",atualizarPagina);

        // ==================================================
        // RECUPERA DATA
        // ==================================================

        if (sessionStorage.getItem("data")) {
            data.value =sessionStorage.getItem("data");
        }

        // ==================================================
        // RECUPERA HORÁRIO
        // ==================================================

        if (sessionStorage.getItem("horario")) {
             horario.value = sessionStorage.getItem("horario");
        }
    }
);

// ==========================================================
// SELECIONA LABORATÓRIO
// ==========================================================

function selecionarLab(id) {
    const data = document.getElementById("data").value;

    const horario = document.getElementById("horario").value;


    if (data === "" || horario === "") {
        alert("Selecione a data e o horário antes de escolher um laboratório.");
        return;
    }

    // Mostra o formulário
    document.getElementById("formReserva").style.display = "block";

    // Texto do laboratório
    document.getElementById("labEscolhido" ).innerHTML = "<strong>Laboratório:</strong> LAB " + id;

    // Texto da data
    document.getElementById("dataEscolhida").innerHTML ="<strong>Data:</strong> " + data;

    // Texto do horário
    document.getElementById("horarioEscolhido" ).innerHTML = "<strong>Horário:</strong> " + horario;

    // Preenche campos escondidos
    document.getElementById( "id_ambientes").value = id;
    document.getElementById( "data_agendamento").value = data;
    document.getElementById("horario_form" ).value = horario;
}


// ==========================================================
// FECHA FORMULÁRIO
// ==========================================================

function fecharFormulario() {
    document.getElementById("formReserva").style.display = "none";
}


// ==========================================================
// BARRA LATERAL
// ==========================================================

function abrirBarraLateral() {

    document.getElementById("barraLateral").classList.add("aberta");
    document.getElementById("fundoBarraLateral").classList.add("ativo");

    document.body.classList.add("barra-aberta");
}


function fecharBarraLateral() {

    document.getElementById("barraLateral").classList.remove("aberta");
    document.getElementById("fundoBarraLateral").classList.remove("ativo");

    document.body.classList.remove("barra-aberta");
}


document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        fecharBarraLateral();
    }

});


// ==========================================================
// VOLTAR PARA LOGIN
// ==========================================================

window.onpageshow = function(event) {

    if (event.persisted ||
        (performance &&performance.navigation.type === 2)
) {
        document.body.innerHTML = ''; window.location.replace("login.php");
    }
};

</script>

</body>

</html>
