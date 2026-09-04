<?php

// =========================================================
// SESSÃO
// =========================================================

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}


// =========================================================
// CABEÇALHOS ANTI-CACHE
// =========================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


// =========================================================
// LOGOUT
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}


// =========================================================
// CONEXÃO
// =========================================================

include("conexao.php");


// =========================================================
// TURMA SELECIONADA
// =========================================================

$id_turma_selecionada = isset($_GET['turma'])
    ? (int)$_GET['turma']
    : null;


// =========================================================
// MÊS E ANO
// =========================================================

$mes_atual = isset($_GET['mes'])
    ? (int)$_GET['mes']
    : (int)date('m');

$ano_atual = isset($_GET['ano'])
    ? (int)$_GET['ano']
    : (int)date('Y');


// Garante mês válido

if ($mes_atual < 1) {

    $mes_atual = 12;
    $ano_atual--;

} elseif ($mes_atual > 12) {

    $mes_atual = 1;
    $ano_atual++;
}


// =========================================================
// ADICIONAR EVENTO
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['salvar_evento'])
) {

    $nome = mysqli_real_escape_string(
        $conexao,
        $_POST['nome']
    );

    $data_evento = mysqli_real_escape_string(
        $conexao,
        $_POST['data_evento']
    );

    $tipo = mysqli_real_escape_string(
        $conexao,
        $_POST['tipo']
    );

    $id_turma = (int)$_POST['id_turma'];


    if (
        !empty($nome)
        && !empty($data_evento)
        && !empty($id_turma)
    ) {

        /*
         * IMPORTANTE:
         * Não existe nenhuma verificação impedindo
         * outro evento na mesma data.
         *
         * Cada evento recebe seu próprio ID.
         */

        $sql_evento = "
            INSERT INTO eventos
            (
                nome,
                descr,
                data_evento,
                tipo
            )
            VALUES
            (
                '$nome',
                '$nome',
                '$data_evento',
                '$tipo'
            )
        ";


        if (mysqli_query($conexao, $sql_evento)) {

            $id_evento = mysqli_insert_id($conexao);


            $sql_cal = "
                INSERT INTO calendario
                (
                    id_eventos,
                    id_turma
                )
                VALUES
                (
                    $id_evento,
                    $id_turma
                )
            ";

            mysqli_query($conexao, $sql_cal);


            header(
                "Location: calendario.php?turma="
                . $id_turma
                . "&mes="
                . $mes_atual
                . "&ano="
                . $ano_atual
            );

            exit();
        }
    }
}


// =========================================================
// EXCLUIR EVENTO
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['excluir_evento'])
) {

    $id_evento_del = (int)$_POST['id_evento'];


    // Primeiro remove a ligação com a turma

    mysqli_query(
        $conexao,
        "DELETE FROM calendario
         WHERE id_eventos = $id_evento_del"
    );


    // Depois remove o evento

    mysqli_query(
        $conexao,
        "DELETE FROM eventos
         WHERE id_eventos = $id_evento_del"
    );


    header(
        "Location: calendario.php?turma="
        . $id_turma_selecionada
        . "&mes="
        . $mes_atual
        . "&ano="
        . $ano_atual
    );

    exit();
}


// =========================================================
// EDITAR EVENTO
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['editar_evento'])
) {

    $id_evento_edit = (int)$_POST['id_evento'];

    $nome_edit = mysqli_real_escape_string(
        $conexao,
        $_POST['nome']
    );

    $tipo_edit = mysqli_real_escape_string(
        $conexao,
        $_POST['tipo']
    );


    if (!empty($nome_edit)) {

        $sql_update = "
            UPDATE eventos
            SET
                nome = '$nome_edit',
                descr = '$nome_edit',
                tipo = '$tipo_edit'
            WHERE id_eventos = $id_evento_edit
        ";


        mysqli_query(
            $conexao,
            $sql_update
        );


        header(
            "Location: calendario.php?turma="
            . $id_turma_selecionada
            . "&mes="
            . $mes_atual
            . "&ano="
            . $ano_atual
        );

        exit();
    }
}


// =========================================================
// BUSCAR EVENTOS
// =========================================================

$eventos_cadastrados = [];

$nome_turma_atual = "";


if ($id_turma_selecionada) {


    // -----------------------------------------
    // Nome da turma
    // -----------------------------------------

    $sql_t_atual = "
        SELECT serie, curso
        FROM turma
        WHERE id_turma = $id_turma_selecionada
    ";


    $res_t_atual = mysqli_query(
        $conexao,
        $sql_t_atual
    );


    if ($row_t = mysqli_fetch_assoc($res_t_atual)) {

        $nome_turma_atual =
            $row_t['serie']
            . " "
            . $row_t['curso'];
    }


    // -----------------------------------------
    // Eventos da turma
    // -----------------------------------------

    $sql_busca = "
        SELECT e.*
        FROM eventos e

        INNER JOIN calendario c
            ON e.id_eventos = c.id_eventos

        WHERE c.id_turma = $id_turma_selecionada

        ORDER BY
            e.data_evento ASC,
            e.id_eventos ASC
    ";


    $res = mysqli_query(
        $conexao,
        $sql_busca
    );


    while ($row = mysqli_fetch_assoc($res)) {

        /*
         * AQUI ESTÁ A PRINCIPAL CORREÇÃO.
         *
         * Antes:
         *
         * $eventos_cadastrados[$row['data_evento']] = $row;
         *
         * Isso fazia um evento substituir o outro.
         *
         * Agora:
         */

        $eventos_cadastrados[
            $row['data_evento']
        ][] = $row;
    }
}


// =========================================================
// BUSCAR TURMAS
// =========================================================

$sql_turmas = "
    SELECT *
    FROM turma
    ORDER BY serie ASC, curso ASC
";


$result_turmas = mysqli_query(
    $conexao,
    $sql_turmas
);


$turmas_integral = [];

$turmas_noturno = [];


while ($t = mysqli_fetch_assoc($result_turmas)) {

    if (
        strtoupper($t['periodo']) === 'N'
    ) {

        $turmas_noturno[] = $t;

    } else {

        $turmas_integral[] = $t;
    }
}


// =========================================================
// CALENDÁRIO
// =========================================================

$dias_no_mes = cal_days_in_month(
    CAL_GREGORIAN,
    $mes_atual,
    $ano_atual
);


$primeiro_dia_semana = (int)date(
    'N',
    strtotime(
        sprintf(
            '%04d-%02d-01',
            $ano_atual,
            $mes_atual
        )
    )
);


// =========================================================
// MESES
// =========================================================

$meses_nome = [

    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro'

];


// =========================================================
// MÊS ANTERIOR
// =========================================================

$mes_anterior = $mes_atual - 1;

$ano_anterior = $ano_atual;


if ($mes_anterior < 1) {

    $mes_anterior = 12;

    $ano_anterior--;
}


// =========================================================
// PRÓXIMO MÊS
// =========================================================

$mes_proximo = $mes_atual + 1;

$ano_proximo = $ano_atual;


if ($mes_proximo > 12) {

    $mes_proximo = 1;

    $ano_proximo++;
}


// =========================================================
// TURMA PARA URL
// =========================================================

$param_turma = $id_turma_selecionada
    ? "&turma=" . $id_turma_selecionada
    : "";

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Calendário de Eventos - Etec</title>


    <link
        rel="stylesheet"
        href="../css/calendario.css"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>


<body>


<!-- =====================================================
     CABEÇALHO
     ===================================================== -->

<header>

    <div class="logo">

        <img
            src="../logo.png"
            alt="Logo Etec"
        >

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
                class="has-submenu"
            >
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


        <a href="#" class="has-submenu">
            Notícias
        </a>

        <a href="">
            Empregos & Estágios
        </a>

        <a href="">
            Parceiros
        </a>

        <a href="">
            TCC
        </a>

    </nav>


    <div class="menu">

        <form
            action="calendario.php"
            method="POST"
            class="form-logout"
        >

            <input
                type="hidden"
                name="logout"
                value="1"
            >


            <button
                type="submit"
                class="btn-logout"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

            </button>

        </form>

    </div>

</header>



<!-- =====================================================
     SELETOR DE TURMA
     ===================================================== -->

<section class="dropdown-container">

    <button class="btn-dropdown">

        <?= $nome_turma_atual
            ? "Eventos - "
                . htmlspecialchars($nome_turma_atual)
            : "Eventos"
        ?>

        <i class="fa-solid fa-chevron-down"></i>

    </button>


    <div class="dropdown-menu">


        <!-- INTEGRAL -->

        <div class="menu-item-periodo">

            <span>
                Integral
            </span>

            <i class="fa-solid fa-chevron-right seta"></i>


            <div class="submenu-turmas">

                <?php foreach ($turmas_integral as $t): ?>

                    <a
                        href="calendario.php?turma=<?= $t['id_turma'] ?>&mes=<?= $mes_atual ?>&ano=<?= $ano_atual ?>"
                    >

                        <?= htmlspecialchars(
                            $t['serie']
                            . ' '
                            . $t['curso']
                        ) ?>

                    </a>

                <?php endforeach; ?>

            </div>

        </div>



        <!-- NOTURNO -->

        <div class="menu-item-periodo">

            <span>
                Noturno
            </span>

            <i class="fa-solid fa-chevron-right seta"></i>


            <div class="submenu-turmas">

                <?php foreach ($turmas_noturno as $t): ?>

                    <a
                        href="calendario.php?turma=<?= $t['id_turma'] ?>&mes=<?= $mes_atual ?>&ano=<?= $ano_atual ?>"
                    >

                        <?= htmlspecialchars(
                            $t['serie']
                            . ' '
                            . $t['curso']
                        ) ?>

                    </a>

                <?php endforeach; ?>

            </div>

        </div>


    </div>

</section>



<!-- =====================================================
     CALENDÁRIO
     ===================================================== -->

<div class="container">


    <div class="calendar-month-nav">


        <a
            href="calendario.php?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?><?= $param_turma ?>"
            class="nav-month-btn"
        >

            <i class="fa-solid fa-chevron-left"></i>

        </a>


        <h2 class="calendar-month-title">

            <?= $meses_nome[$mes_atual] ?>
            <?= $ano_atual ?>

        </h2>


        <a
            href="calendario.php?mes=<?= $mes_proximo ?>&ano=<?= $ano_proximo ?><?= $param_turma ?>"
            class="nav-month-btn"
        >

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


            <!-- ESPAÇOS ANTES DO PRIMEIRO DIA -->

            <?php

            for (
                $offset = 1;
                $offset < $primeiro_dia_semana;
                $offset++
            ):

            ?>

                <div class="calendar-day empty-day"></div>

            <?php endfor; ?>



            <!-- DIAS DO MÊS -->

            <?php

            for (
                $dia = 1;
                $dia <= $dias_no_mes;
                $dia++
            ):

                $data_formatada = sprintf(
                    "%04d-%02d-%02d",
                    $ano_atual,
                    $mes_atual,
                    $dia
                );


                /*
                 * CORREÇÃO:
                 *
                 * Agora pegamos uma LISTA de eventos.
                 */

                $eventos_do_dia =
                    $eventos_cadastrados[$data_formatada]
                    ?? [];


                $tem_evento =
                    !empty($eventos_do_dia);

            ?>


                <div
                    class="calendar-day"
                    onclick="clicarDia(
                        '<?= $data_formatada ?>',
                        <?= htmlspecialchars(
                            json_encode($eventos_do_dia),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    )"
                >


                    <span
                        class="day-number <?= $tem_evento ? 'circled' : '' ?>"
                    >

                        <?= $dia ?>

                    </span>



                    <?php if ($tem_evento): ?>


                        <?php foreach (
                            $eventos_do_dia
                            as $evento
                        ): ?>


                            <div
                                class="tag-event tag-<?= htmlspecialchars($evento['tipo']) ?>"
                            >

                                <?= htmlspecialchars(
                                    $evento['tipo']
                                ) ?>

                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            <?php endfor; ?>


        </div>

    </div>

</div>



<!-- =====================================================
     MODAL - AVISO SEM TURMA
     ===================================================== -->

<div
    class="modal-overlay"
    id="modalAviso"
>

    <div class="modal-card modal-card-centered">


        <button
            class="btn-close-corner"
            onclick="fecharModais()"
        >

            <i class="fa-solid fa-xmark"></i>

        </button>


        <br>


        <h3>
            Selecione uma Turma!
        </h3>


        <br>


        <p>

            Por favor, selecione primeiro uma turma
            no menu <strong>"Eventos"</strong>
            para interagir com o calendário.

        </p>


    </div>

</div>



<!-- =====================================================
     MODAL - CRIAR EVENTO
     ===================================================== -->

<div
    class="modal-overlay"
    id="modalCriar"
>

    <div class="modal-card">


        <div
            id="alertaForm"
            class="alert-box"
        >

            Por favor, digite a descrição do evento.

        </div>


        <textarea
            id="tempNome"
            placeholder="Evento, atividade.."
        ></textarea>



        <div class="radio-options">


            <label>

                <input
                    type="radio"
                    name="tempTipo"
                    value="Prova"
                    checked
                >

                <span class="dot dot-red"></span>

                Prova

            </label>



            <label>

                <input
                    type="radio"
                    name="tempTipo"
                    value="Trabalho"
                >

                <span class="dot dot-yellow"></span>

                Trabalho

            </label>



            <label>

                <input
                    type="radio"
                    name="tempTipo"
                    value="Evento"
                >

                <span class="dot dot-blue"></span>

                Evento

            </label>


        </div>



        <div class="modal-actions">


            <button
                class="btn-action"
                onclick="abrirConfirmacao()"
            >

                Adicionar

            </button>


            <button
                class="btn-cancel"
                onclick="fecharModais()"
            >

                Cancelar

            </button>


        </div>


    </div>

</div>



<!-- =====================================================
     MODAL - CONFIRMAR ADIÇÃO
     ===================================================== -->

<div
    class="modal-overlay"
    id="modalConfirmar"
>

    <div class="modal-card modal-card-centered">


        <p class="txt-confirmacao">

            Tem certeza que deseja adicionar o evento?

        </p>



        <form method="POST">


            <input
                type="hidden"
                name="salvar_evento"
                value="1"
            >


            <input
                type="hidden"
                name="id_turma"
                value="<?= $id_turma_selecionada ?>"
            >


            <input
                type="hidden"
                name="data_evento"
                id="finalData"
            >


            <input
                type="hidden"
                name="nome"
                id="finalNome"
            >


            <input
                type="hidden"
                name="tipo"
                id="finalTipo"
            >



            <div class="modal-actions">


                <button
                    type="submit"
                    class="btn-action"
                >

                    Adicionar

                </button>


                <button
                    type="button"
                    class="btn-cancel"
                    onclick="fecharModais()"
                >

                    Cancelar

                </button>


            </div>


        </form>


    </div>

</div>



<!-- =====================================================
     MODAL - EVENTOS DO DIA
     ===================================================== -->

<div
    class="modal-overlay"
    id="modalVer"
>

    <div class="modal-card">


        <button
            class="btn-close-corner"
            onclick="fecharModais()"
        >

            <i class="fa-solid fa-xmark"></i>

        </button>


        <br>


        <h3
            id="tituloEventosDia"
            class="titulo-eventos-dia"
        >
            Eventos
        </h3>


        <p
            id="dataEventosDia"
            class="ver-data"
        ></p>


        <div
            id="listaEventosDia"
            class="lista-eventos-dia"
        ></div>



        <!-- BOTÃO PARA ADICIONAR OUTRO EVENTO -->

        <div class="modal-actions">


            <button
                type="button"
                class="btn-action"
                onclick="adicionarOutroEvento()"
            >

                <i class="fa-solid fa-plus"></i>

                Adicionar evento

            </button>


            <button
                type="button"
                class="btn-cancel"
                onclick="fecharModais()"
            >

                Fechar

            </button>


        </div>


    </div>

</div>



<!-- =====================================================
     MODAL - EDITAR EVENTO
     ===================================================== -->

<div
    class="modal-overlay"
    id="modalEditar"
>

    <div class="modal-card">


        <button
            class="btn-close-corner"
            onclick="fecharModais()"
        >

            <i class="fa-solid fa-xmark"></i>

        </button>


        <br>


        <form method="POST">


            <input
                type="hidden"
                name="editar_evento"
                value="1"
            >


            <input
                type="hidden"
                name="id_evento"
                id="editIdEvento"
            >


            <textarea
                id="editNome"
                name="nome"
                placeholder="Descrição do evento.."
            ></textarea>



            <div class="radio-options">


                <label>

                    <input
                        type="radio"
                        name="tipo"
                        id="editTipoProva"
                        value="Prova"
                    >

                    <span class="dot dot-red"></span>

                    Prova

                </label>



                <label>

                    <input
                        type="radio"
                        name="tipo"
                        id="editTipoTrabalho"
                        value="Trabalho"
                    >

                    <span class="dot dot-yellow"></span>

                    Trabalho

                </label>



                <label>

                    <input
                        type="radio"
                        name="tipo"
                        id="editTipoEvento"
                        value="Evento"
                    >

                    <span class="dot dot-blue"></span>

                    Evento

                </label>


            </div>



            <div class="modal-actions">


                <button
                    type="submit"
                    class="btn-action"
                >

                    Salvar

                </button>


                <button
                    type="button"
                    class="btn-cancel"
                    onclick="fecharModais()"
                >

                    Cancelar

                </button>


            </div>


        </form>


    </div>

</div>



<!-- =====================================================
     MODAL - CONFIRMAR EXCLUSÃO
     ===================================================== -->

<div
    class="modal-overlay"
    id="modalConfirmarExclusao"
>

    <div class="modal-card modal-card-centered">


        <button
            type="button"
            class="btn-close-corner"
            onclick="fecharModais()"
        >

            <i class="fa-solid fa-xmark"></i>

        </button>


        <div class="icone-confirmacao-exclusao">

            <i class="fa-solid fa-trash"></i>

        </div>


        <h3 class="titulo-confirmacao-exclusao">

            Excluir evento

        </h3>


        <p class="txt-confirmacao">

            Tem certeza que deseja excluir esse evento?

        </p>


        <form
            method="POST"
            id="formExcluirEvento"
        >

            <input
                type="hidden"
                name="excluir_evento"
                value="1"
            >


            <input
                type="hidden"
                name="id_evento"
                id="delIdEvento"
            >


            <div class="modal-actions">


                <button
                    type="button"
                    class="btn-cancel"
                    onclick="fecharModais()"
                >

                    Cancelar

                </button>


                <button
                    type="submit"
                    class="btn-action btn-delete-confirmar"
                >

                    <i class="fa-solid fa-trash"></i>

                    Excluir

                </button>


            </div>

        </form>


    </div>

</div>



<!-- =====================================================
     JAVASCRIPT
     ===================================================== -->

<script>


// =========================================================
// VARIÁVEIS
// =========================================================

let turmaSelecionada =
    <?= json_encode($id_turma_selecionada) ?>;


let dataSelecionada = null;


// Evento atualmente selecionado para editar/excluir

let eventoAtual = null;


// Lista de eventos do dia atualmente aberto

let eventosDoDiaAtual = [];


// =========================================================
// CLICAR NO DIA
// =========================================================

function clicarDia(data, eventos) {


    // Se não existe turma selecionada

    if (!turmaSelecionada) {

        document.getElementById(
            'modalAviso'
        ).style.display = 'flex';

        return;
    }


    dataSelecionada = data;


    /*
     * Agora eventos é sempre um ARRAY.
     *
     * Se não houver eventos:
     *
     * []
     *
     * Se houver 1:
     *
     * [evento]
     *
     * Se houver 3:
     *
     * [evento1, evento2, evento3]
     */

    eventosDoDiaAtual = Array.isArray(eventos)
        ? eventos
        : [];


    // -----------------------------------------------------
    // SEM EVENTOS
    // -----------------------------------------------------

    if (eventosDoDiaAtual.length === 0) {

        document.getElementById(
            'tempNome'
        ).value = '';


        document.getElementById(
            'alertaForm'
        ).style.display = 'none';


        document.getElementById(
            'modalCriar'
        ).style.display = 'flex';


        return;
    }


    // -----------------------------------------------------
    // DATA FORMATADA
    // -----------------------------------------------------

    const partesData = data.split('-');


    const dataFormatada =
        `${partesData[2]}/${partesData[1]}/${partesData[0]}`;


    document.getElementById(
        'dataEventosDia'
    ).innerText =
        "Data: " + dataFormatada;


    document.getElementById(
        'tituloEventosDia'
    ).innerText =
        eventosDoDiaAtual.length === 1
            ? "Evento do dia"
            : "Eventos do dia";


    // -----------------------------------------------------
    // LISTA DOS EVENTOS
    // -----------------------------------------------------

    const lista =
        document.getElementById(
            'listaEventosDia'
        );


    lista.innerHTML = '';


    eventosDoDiaAtual.forEach(
        function(evento, index) {


            let dotClass = '';


            if (evento.tipo === 'Prova') {

                dotClass = 'dot-red-solid';

            } else if (
                evento.tipo === 'Trabalho'
            ) {

                dotClass = 'dot-yellow-solid';

            } else if (
                evento.tipo === 'Evento'
            ) {

                dotClass = 'dot-blue-solid';

            }


            /*
             * Cria a caixa individual de cada evento.
             */

            const div =
                document.createElement('div');


            div.className =
                'evento-item';


            div.innerHTML = `

                <div class="evento-item-info">

                    <div class="evento-item-titulo">

                        <span class="dot ${dotClass}"></span>

                        <strong>
                            ${escapeHtml(evento.nome)}
                        </strong>

                    </div>

                    <div class="evento-item-tipo">

                        ${escapeHtml(evento.tipo)}

                    </div>

                </div>


                <div class="evento-item-acoes">

                    <button
                        type="button"
                        class="btn-action btn-edit"
                        onclick="selecionarEvento(${index})"
                    >

                        <i class="fa-solid fa-pen"></i>

                        Editar

                    </button>


                    <button
                        type="button"
                        class="btn-cancel btn-delete"
                        onclick="selecionarEventoParaExcluir(${index})"
                    >

                        <i class="fa-solid fa-trash"></i>

                        Excluir

                    </button>

                </div>

            `;


            lista.appendChild(div);

        }
    );


    // -----------------------------------------------------
    // ABRE MODAL
    // -----------------------------------------------------

    document.getElementById(
        'modalVer'
    ).style.display = 'flex';

}



// =========================================================
// SELECIONAR EVENTO PARA EDITAR
// =========================================================

function selecionarEvento(index) {


    const evento =
        eventosDoDiaAtual[index];


    if (!evento) {
        return;
    }


    eventoAtual = evento;


    document.getElementById(
        'editIdEvento'
    ).value =
        evento.id_eventos;


    document.getElementById(
        'editNome'
    ).value =
        evento.nome;


    // Limpa os radios

    document.getElementById(
        'editTipoProva'
    ).checked = false;


    document.getElementById(
        'editTipoTrabalho'
    ).checked = false;


    document.getElementById(
        'editTipoEvento'
    ).checked = false;


    // Marca o tipo atual

    if (evento.tipo === 'Prova') {

        document.getElementById(
            'editTipoProva'
        ).checked = true;

    } else if (
        evento.tipo === 'Trabalho'
    ) {

        document.getElementById(
            'editTipoTrabalho'
        ).checked = true;

    } else if (
        evento.tipo === 'Evento'
    ) {

        document.getElementById(
            'editTipoEvento'
        ).checked = true;
    }


    document.getElementById(
        'modalVer'
    ).style.display = 'none';


    document.getElementById(
        'modalEditar'
    ).style.display = 'flex';

}



// =========================================================
// SELECIONAR EVENTO PARA EXCLUIR
// =========================================================

function selecionarEventoParaExcluir(index) {


    const evento =
        eventosDoDiaAtual[index];


    if (!evento) {
        return;
    }


    eventoAtual = evento;


    // Coloca o ID no formulário

    document.getElementById(
        'delIdEvento'
    ).value =
        evento.id_eventos;


    document.getElementById(
        'modalVer'
    ).style.display = 'none';


    document.getElementById(
        'modalConfirmarExclusao'
    ).style.display = 'flex';

}



// =========================================================
// ADICIONAR OUTRO EVENTO NO MESMO DIA
// =========================================================

function adicionarOutroEvento() {


    /*
     * IMPORTANTE:
     *
     * Mantemos a dataSelecionada.
     *
     * Assim o novo evento será cadastrado
     * exatamente no mesmo dia.
     */

    document.getElementById(
        'tempNome'
    ).value = '';


    document.getElementById(
        'alertaForm'
    ).style.display = 'none';


    document.getElementById(
        'modalVer'
    ).style.display = 'none';


    document.getElementById(
        'modalCriar'
    ).style.display = 'flex';

}



// =========================================================
// CONFIRMAR NOVO EVENTO
// =========================================================

function abrirConfirmacao() {


    const nome =
        document.getElementById(
            'tempNome'
        ).value;


    if (!nome.trim()) {

        document.getElementById(
            'alertaForm'
        ).style.display = 'block';

        return;
    }


    const tipo =
        document.querySelector(
            'input[name="tempTipo"]:checked'
        ).value;


    document.getElementById(
        'finalData'
    ).value =
        dataSelecionada;


    document.getElementById(
        'finalNome'
    ).value =
        nome;


    document.getElementById(
        'finalTipo'
    ).value =
        tipo;


    document.getElementById(
        'modalCriar'
    ).style.display = 'none';


    document.getElementById(
        'modalConfirmar'
    ).style.display = 'flex';

}



// =========================================================
// FECHAR MODAIS
// =========================================================

function fecharModais() {


    document
        .querySelectorAll('.modal-overlay')
        .forEach(
            function(modal) {

                modal.style.display = 'none';

            }
        );

}



// =========================================================
// ESCAPAR HTML
// =========================================================

function escapeHtml(text) {


    const div =
        document.createElement('div');


    div.textContent =
        text;


    return div.innerHTML;

}



// =========================================================
// VOLTAR PELO NAVEGADOR
// =========================================================

window.onpageshow =
    function(event) {


        if (
            event.persisted
            ||
            (
                performance
                &&
                performance.navigation.type === 2
            )
        ) {

            document.body.innerHTML = '';

            window.location.replace(
                "login.php"
            );

        }

    };


</script>


</body>

</html>