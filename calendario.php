<?php
session_start();



header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

include("conexao.php");

/*
 * Permissão para gerenciamento:
 * somente o representante que passou pelo login específico
 * da página eventos_login.php pode adicionar, editar ou excluir.
 */
$modo_editor = isset($_SESSION['eventos_editor']) && $_SESSION['eventos_editor'] === true;
$modo_gestao = isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'gestao';

// Representante e Gestão possuem permissões diferentes:
// representante -> eventos somente da própria turma
// gestão -> eventos gerais para todas as turmas
$pode_gerenciar_eventos = $modo_editor || $modo_gestao;

$id_representante = isset($_SESSION['eventos_representante_id'])
    ? (int)$_SESSION['eventos_representante_id']
    : null;

/*
 * Turma selecionada.
 * Se for representante, ele só pode administrar a turma vinculada
 * ao seu cadastro.
 */
$id_turma_selecionada = isset($_GET['turma']) ? (int)$_GET['turma'] : null;

if ($modo_editor && $id_representante) {

    $stmt_rep = mysqli_prepare(
        $conexao,
        "SELECT id_turma FROM representante
         WHERE id_representante = ? AND status = 'Ativo'
         LIMIT 1"
    );

    if ($stmt_rep) {
        mysqli_stmt_bind_param($stmt_rep, "i", $id_representante);
        mysqli_stmt_execute($stmt_rep);
        $resultado_rep = mysqli_stmt_get_result($stmt_rep);
        $rep_atual = mysqli_fetch_assoc($resultado_rep);
        mysqli_stmt_close($stmt_rep);

        if (!$rep_atual) {
            $_SESSION['eventos_editor'] = false;
            unset($_SESSION['eventos_representante_id']);
            $modo_editor = false;
            $id_representante = null;
        } else {
            $turma_representante = (int)$rep_atual['id_turma'];

            if (!$id_turma_selecionada || $id_turma_selecionada !== $turma_representante) {
                $id_turma_selecionada = $turma_representante;
            }
        }
    }
}

/*
 * Mês e ano.
 */
$mes_atual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_atual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

if ($mes_atual < 1) {
    $mes_atual = 12;
    $ano_atual--;
} elseif ($mes_atual > 12) {
    $mes_atual = 1;
    $ano_atual++;
}

/*
 * ============================
 * ADICIONAR EVENTO
 * ============================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_evento'])) {

    if (!$pode_gerenciar_eventos) {
        header("Location: calendario.php");
        exit();
    }

    $nome = trim($_POST['nome'] ?? '');
    $data_evento = $_POST['data_evento'] ?? '';
    $tipo = $_POST['tipo'] ?? '';

    $tipos_validos = ['Prova', 'Trabalho', 'Evento'];

    // Gestão cria evento geral: id_turma = NULL.
    // Representante cria evento somente na própria turma.
    if ($modo_gestao) {
        $id_turma = null;
    } elseif ($modo_editor && isset($turma_representante)) {
        $id_turma = $turma_representante;
    } else {
        $id_turma = null;
    }

    if (
        $nome !== '' &&
        $data_evento !== '' &&
        in_array($tipo, $tipos_validos, true) &&
        ($modo_gestao || ($modo_editor && isset($turma_representante)))
    ) {

        $stmt_evento = mysqli_prepare(
            $conexao,
            "INSERT INTO eventos (nome, descr, data_evento, tipo)
             VALUES (?, ?, ?, ?)"
        );

        if ($stmt_evento) {

            mysqli_stmt_bind_param(
                $stmt_evento,
                "ssss",
                $nome,
                $nome,
                $data_evento,
                $tipo
            );

            if (mysqli_stmt_execute($stmt_evento)) {

                $id_evento = mysqli_insert_id($conexao);

                if ($id_turma === null) {
                    $stmt_cal = mysqli_prepare(
                        $conexao,
                        "INSERT INTO calendario (id_eventos, id_turma)
                         VALUES (?, NULL)"
                    );
                } else {
                    $stmt_cal = mysqli_prepare(
                        $conexao,
                        "INSERT INTO calendario (id_eventos, id_turma)
                         VALUES (?, ?)"
                    );
                }

                if ($stmt_cal) {
                    if ($id_turma === null) {
                        mysqli_stmt_bind_param($stmt_cal, "i", $id_evento);
                        mysqli_stmt_execute($stmt_cal);
                    } else {
                        mysqli_stmt_bind_param(
                            $stmt_cal,
                            "ii",
                            $id_evento,
                            $id_turma
                        );
                        mysqli_stmt_execute($stmt_cal);
                    }
                    mysqli_stmt_close($stmt_cal);
                }
            }

            mysqli_stmt_close($stmt_evento);
        }
    }

    header(
        "Location: calendario.php?turma=" .
        $id_turma_selecionada .
        "&mes=" . $mes_atual .
        "&ano=" . $ano_atual
    );
    exit();
}

/*
 * ============================
 * EXCLUIR EVENTO
 * ============================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_evento'])) {

    if (!$pode_gerenciar_eventos) {
        header("Location: calendario.php");
        exit();
    }

    $id_evento_del = (int)($_POST['id_evento'] ?? 0);

    if ($id_evento_del > 0) {

        // Gestão só pode excluir eventos gerais.
        // Representante só pode excluir eventos da própria turma.
        if ($modo_gestao) {
            $stmt_verifica = mysqli_prepare(
                $conexao,
                "SELECT id_eventos FROM calendario
                 WHERE id_eventos = ? AND id_turma IS NULL
                 LIMIT 1"
            );
        } elseif (isset($turma_representante)) {
            $stmt_verifica = mysqli_prepare(
                $conexao,
                "SELECT id_eventos FROM calendario
                 WHERE id_eventos = ? AND id_turma = ?
                 LIMIT 1"
            );
        } else {
            $stmt_verifica = false;
        }

        if ($stmt_verifica) {
            if ($modo_gestao) {
                mysqli_stmt_bind_param($stmt_verifica, "i", $id_evento_del);
            } else {
                mysqli_stmt_bind_param($stmt_verifica, "ii", $id_evento_del, $turma_representante);
            }

            mysqli_stmt_execute($stmt_verifica);
            $resultado = mysqli_stmt_get_result($stmt_verifica);
            $evento_existe = mysqli_fetch_assoc($resultado);
            mysqli_stmt_close($stmt_verifica);

            if ($evento_existe) {
                // Remove o vínculo correto.
                if ($modo_gestao) {
                    $stmt_del_cal = mysqli_prepare(
                        $conexao,
                        "DELETE FROM calendario WHERE id_eventos = ? AND id_turma IS NULL"
                    );
                    if ($stmt_del_cal) {
                        mysqli_stmt_bind_param($stmt_del_cal, "i", $id_evento_del);
                        mysqli_stmt_execute($stmt_del_cal);
                        mysqli_stmt_close($stmt_del_cal);
                    }
                } else {
                    $stmt_del_cal = mysqli_prepare(
                        $conexao,
                        "DELETE FROM calendario WHERE id_eventos = ? AND id_turma = ?"
                    );
                    if ($stmt_del_cal) {
                        mysqli_stmt_bind_param($stmt_del_cal, "ii", $id_evento_del, $turma_representante);
                        mysqli_stmt_execute($stmt_del_cal);
                        mysqli_stmt_close($stmt_del_cal);
                    }
                }

                $stmt_del_evento = mysqli_prepare(
                    $conexao,
                    "DELETE FROM eventos WHERE id_eventos = ?"
                );
                if ($stmt_del_evento) {
                    mysqli_stmt_bind_param($stmt_del_evento, "i", $id_evento_del);
                    mysqli_stmt_execute($stmt_del_evento);
                    mysqli_stmt_close($stmt_del_evento);
                }
            }
        }
    }

    header(
        "Location: calendario.php?turma=" .
        $id_turma_selecionada .
        "&mes=" . $mes_atual .
        "&ano=" . $ano_atual
    );

    exit();
}

/*
 * ============================
 * EDITAR EVENTO
 * ============================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_evento'])) {

    if (!$pode_gerenciar_eventos) {
        header("Location: calendario.php");
        exit();
    }

    $id_evento_edit = (int)($_POST['id_evento'] ?? 0);
    $nome_edit = trim($_POST['nome'] ?? '');
    $tipo_edit = $_POST['tipo'] ?? '';

    $tipos_validos = ['Prova', 'Trabalho', 'Evento'];

    if (
        $id_evento_edit > 0 &&
        $nome_edit !== '' &&
        in_array($tipo_edit, $tipos_validos, true) &&
        ($modo_gestao || isset($turma_representante))
    ) {

        if ($modo_gestao) {
            $stmt_edit = mysqli_prepare(
                $conexao,
                "UPDATE eventos e
                 INNER JOIN calendario c ON c.id_eventos = e.id_eventos
                 SET e.nome = ?, e.descr = ?, e.tipo = ?
                 WHERE e.id_eventos = ?
                 AND c.id_turma IS NULL"
            );
        } elseif (isset($turma_representante)) {
            $stmt_edit = mysqli_prepare(
                $conexao,
                "UPDATE eventos e
                 INNER JOIN calendario c ON c.id_eventos = e.id_eventos
                 SET e.nome = ?, e.descr = ?, e.tipo = ?
                 WHERE e.id_eventos = ?
                 AND c.id_turma = ?"
            );
        } else {
            $stmt_edit = false;
        }

        if ($stmt_edit) {
            if ($modo_gestao) {
                mysqli_stmt_bind_param(
                    $stmt_edit,
                    "sssi",
                    $nome_edit, $nome_edit, $tipo_edit, $id_evento_edit
                );
            } else {
                mysqli_stmt_bind_param(
                    $stmt_edit,
                    "sssii",
                    $nome_edit, $nome_edit, $tipo_edit, $id_evento_edit, $turma_representante
                );
            }

            mysqli_stmt_execute($stmt_edit);
            mysqli_stmt_close($stmt_edit);
        }
    }

    header(
        "Location: calendario.php?turma=" .
        $id_turma_selecionada .
        "&mes=" . $mes_atual .
        "&ano=" . $ano_atual
    );
    exit();
}

/*
 * ============================
 * BUSCAR EVENTOS
 * ============================
 */
$eventos_cadastrados = [];
$nome_turma_atual = "";

if ($id_turma_selecionada && !$modo_gestao) {

    $stmt_turma = mysqli_prepare(
        $conexao,
        "SELECT serie, curso FROM turma WHERE id_turma = ?"
    );

    if ($stmt_turma) {
        mysqli_stmt_bind_param($stmt_turma, "i", $id_turma_selecionada);
        mysqli_stmt_execute($stmt_turma);

        $res_turma = mysqli_stmt_get_result($stmt_turma);
        $row_turma = mysqli_fetch_assoc($res_turma);

        if ($row_turma) {
            $nome_turma_atual =
                $row_turma['serie'] . " " . $row_turma['curso'];
        }

        mysqli_stmt_close($stmt_turma);
    }

    /*
     * IMPORTANTE:
     * Cada data agora guarda um ARRAY de eventos.
     * Assim, vários eventos podem existir no mesmo dia.
     */
    $stmt_busca = mysqli_prepare(
        $conexao,
        "SELECT e.id_eventos, e.nome, e.descr, e.data_evento, e.tipo, c.id_turma
         FROM eventos e
         INNER JOIN calendario c ON e.id_eventos = c.id_eventos
         WHERE c.id_turma = ? OR c.id_turma IS NULL
         ORDER BY e.data_evento ASC, e.id_eventos ASC"
    );

    if ($stmt_busca) {

        mysqli_stmt_bind_param(
            $stmt_busca,
            "i",
            $id_turma_selecionada
        );

        mysqli_stmt_execute($stmt_busca);

        $res_eventos = mysqli_stmt_get_result($stmt_busca);

        while ($row = mysqli_fetch_assoc($res_eventos)) {

            $data_evento = $row['data_evento'];

            if (!isset($eventos_cadastrados[$data_evento])) {
                $eventos_cadastrados[$data_evento] = [];
            }

            $eventos_cadastrados[$data_evento][] = $row;
        }

        mysqli_stmt_close($stmt_busca);
    }
}

// Gestão visualiza somente os eventos gerais.
if ($modo_gestao) {
    $stmt_busca_gerais = mysqli_prepare(
        $conexao,
        "SELECT e.id_eventos, e.nome, e.descr, e.data_evento, e.tipo, c.id_turma
         FROM eventos e
         INNER JOIN calendario c ON e.id_eventos = c.id_eventos
         WHERE c.id_turma IS NULL
         ORDER BY e.data_evento ASC, e.id_eventos ASC"
    );

    if ($stmt_busca_gerais) {
        mysqli_stmt_execute($stmt_busca_gerais);
        $res_gerais = mysqli_stmt_get_result($stmt_busca_gerais);

        while ($row = mysqli_fetch_assoc($res_gerais)) {
            $data_evento = $row['data_evento'];
            if (!isset($eventos_cadastrados[$data_evento])) {
                $eventos_cadastrados[$data_evento] = [];
            }
            $eventos_cadastrados[$data_evento][] = $row;
        }
        mysqli_stmt_close($stmt_busca_gerais);
    }
}

/*
 * Buscar turmas.
 */
$sql_turmas = "SELECT * FROM turma ORDER BY serie ASC, curso ASC";
$result_turmas = mysqli_query($conexao, $sql_turmas);

$turmas_integral = [];
$turmas_noturno = [];

while ($t = mysqli_fetch_assoc($result_turmas)) {

    if (strtoupper((string)$t['periodo']) === 'N') {
        $turmas_noturno[] = $t;
    } else {
        $turmas_integral[] = $t;
    }
}

/*
 * Calendário.
 */
$dias_no_mes = cal_days_in_month(
    CAL_GREGORIAN,
    $mes_atual,
    $ano_atual
);

$primeiro_dia_semana = (int)date(
    'N',
    strtotime(sprintf('%04d-%02d-01', $ano_atual, $mes_atual))
);

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

$mes_anterior = $mes_atual - 1;
$ano_anterior = $ano_atual;

if ($mes_anterior < 1) {
    $mes_anterior = 12;
    $ano_anterior--;
}

$mes_proximo = $mes_atual + 1;
$ano_proximo = $ano_atual;

if ($mes_proximo > 12) {
    $mes_proximo = 1;
    $ano_proximo++;
}

$param_turma = (!$modo_gestao && $id_turma_selecionada)
    ? "&turma=" . $id_turma_selecionada
    : "";
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Calendário de Eventos - Etec</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/calendario.css">
    <link rel="stylesheet"href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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

<section class="dropdown-container">

    <?php if ($modo_gestao): ?>

        <div class="btn-dropdown eventos-gestao">
            Eventos
        </div>

    <?php else: ?>

        <button class="btn-dropdown">
            <?= $nome_turma_atual
                ? "Eventos - " . htmlspecialchars($nome_turma_atual)
                : "Eventos" ?>

            <i class="fa-solid fa-chevron-down"></i>
        </button>

        <div class="dropdown-menu">

            <div class="menu-item-periodo">
                <span>Integral</span>
                <i class="fa-solid fa-chevron-right seta"></i>
                <div class="submenu-turmas">
                    <?php foreach ($turmas_integral as $t): ?>
                        <a href="calendario.php?turma=<?= (int)$t['id_turma'] ?>&mes=<?= $mes_atual ?>&ano=<?= $ano_atual ?>">
                            <?= htmlspecialchars($t['serie'] . ' ' . $t['curso']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="menu-item-periodo">
                <span>Noturno</span>
                <i class="fa-solid fa-chevron-right seta"></i>
                <div class="submenu-turmas">
                    <?php foreach ($turmas_noturno as $t): ?>
                        <a href="calendario.php?turma=<?= (int)$t['id_turma'] ?>&mes=<?= $mes_atual ?>&ano=<?= $ano_atual ?>">
                            <?= htmlspecialchars($t['serie'] . ' ' . $t['curso']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    <?php endif; ?>

</section>

<div class="container">

    <div class="calendar-month-nav">

        <a href="calendario.php?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?><?= $param_turma ?>"
           class="nav-month-btn">
            <i class="fa-solid fa-chevron-left"></i>
        </a>

        <h2 class="calendar-month-title">
            <?= $meses_nome[$mes_atual] . " " . $ano_atual ?>
        </h2>

        <a href="calendario.php?mes=<?= $mes_proximo ?>&ano=<?= $ano_proximo ?><?= $param_turma ?>"
           class="nav-month-btn">
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

            <?php for ($offset = 1; $offset < $primeiro_dia_semana; $offset++): ?>

                <div class="calendar-day empty-day"></div>

            <?php endfor; ?>

            <?php for ($dia = 1; $dia <= $dias_no_mes; $dia++): ?>

                <?php
                $data_formatada = sprintf(
                    "%04d-%02d-%02d",
                    $ano_atual,
                    $mes_atual,
                    $dia
                );

                $eventos_do_dia =
                    $eventos_cadastrados[$data_formatada] ?? [];

                $tem_evento = !empty($eventos_do_dia);
                ?>

                <div
                    class="calendar-day <?= $tem_evento ? 'dia-com-evento' : '' ?>"
                    data-date="<?= $data_formatada ?>"
                    data-events="<?= htmlspecialchars(
                        json_encode(
                            $eventos_do_dia,
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                    <span class="day-number <?= $tem_evento ? 'circled' : '' ?>">
                        <?= $dia ?>
                    </span>

                    <?php foreach ($eventos_do_dia as $evt): ?>

                        <div class="tag-event tag-<?= htmlspecialchars($evt['tipo']) ?>">
                            <?= htmlspecialchars($evt['tipo']) ?>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endfor; ?>

        </div>

    </div>

</div>

<div class="modal-overlay" id="modalAviso">

    <div class="modal-card modal-card-centered">

        <button class="btn-close-corner" data-fechar-modal>
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="icone-aviso">
            <i class="fa-solid fa-calendar-xmark"></i>
        </div>

        <h3>Selecione uma turma!</h3>

        <p>
            Selecione primeiro uma turma no menu
            <strong>"Eventos"</strong>.
        </p>

    </div>

</div>

<div class="modal-overlay" id="modalCriar">

    <div class="modal-card">

        <button class="btn-close-corner" data-fechar-modal>
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="modal-titulo">
            Adicionar evento
        </h3>

        <div id="alertaForm" class="alert-box">
            Por favor, preencha a descrição do evento.
        </div>

        <textarea
            id="tempNome"
            placeholder="Evento, atividade.."
        ></textarea>

        <div class="radio-options">

            <label>
                <input type="radio" name="tempTipo" value="Prova" checked>
                <span class="dot dot-red"></span>
                Prova
            </label>

            <label>
                <input type="radio" name="tempTipo" value="Trabalho">
                <span class="dot dot-yellow"></span>
                Trabalho
            </label>

            <label>
                <input type="radio" name="tempTipo" value="Evento">
                <span class="dot dot-blue"></span>
                Evento
            </label>

        </div>

        <div class="modal-actions">

            <button type="button" class="btn-action" id="btnAbrirConfirmacao">
                Adicionar
            </button>

            <button type="button" class="btn-cancel" data-fechar-modal>
                Cancelar
            </button>

        </div>

    </div>

</div>

<div class="modal-overlay" id="modalConfirmar">

    <div class="modal-card modal-card-centered">

        <div class="icone-confirmacao">
            <i class="fa-solid fa-circle-question"></i>
        </div>

        <p class="txt-confirmacao">
            Tem certeza que deseja adicionar o evento?
        </p>

        <form method="POST">

            <input type="hidden" name="salvar_evento" value="1">
            <input type="hidden" name="id_turma" value="<?= $modo_gestao ? "" : (int)$id_turma_selecionada ?>">
            <input type="hidden" name="data_evento" id="finalData">
            <input type="hidden" name="nome" id="finalNome">
            <input type="hidden" name="tipo" id="finalTipo">

            <div class="modal-actions">

                <button type="submit" class="btn-action">
                    Adicionar
                </button>

                <button type="button" class="btn-cancel" data-fechar-modal>
                    Cancelar
                </button>

            </div>

        </form>

    </div>

</div>

<div class="modal-overlay" id="modalVer">

    <div class="modal-card">

        <button class="btn-close-corner" data-fechar-modal>
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="modal-titulo">
            Evento
        </h3>

        <div id="listaEventos" class="lista-eventos"></div>

        <?php if ($pode_gerenciar_eventos): ?>

            <div class="modal-actions ver-actions">

                <button
                    type="button"
                    class="btn-action btn-edit"
                    id="btnEditarEvento"
                >
                    <i class="fa-solid fa-pen"></i>
                    Editar
                </button>

                <button
                    type="button"
                    class="btn-action btn-delete"
                    id="btnExcluirEvento"
                >
                    <i class="fa-solid fa-trash"></i>
                    Excluir
                </button>

            </div>

        <?php else: ?>

        <?php endif; ?>

    </div>

</div>

<div class="modal-overlay" id="modalEditar">

    <div class="modal-card">

        <button class="btn-close-corner" data-fechar-modal>
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="modal-titulo">
            Editar evento
        </h3>

        <form method="POST">

            <input type="hidden" name="editar_evento" value="1">
            <input type="hidden" name="id_evento" id="editIdEvento">

            <textarea
                id="editNome"
                name="nome"
                placeholder="Descrição do evento.."
                required
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

                <button type="submit" class="btn-action">
                    Salvar
                </button>

                <button type="button" class="btn-cancel" data-fechar-modal>
                    Cancelar
                </button>

            </div>

        </form>

    </div>

</div>

<div class="modal-overlay" id="modalExcluir">

    <div class="modal-card modal-card-centered">

    <button class="btn-close-corner" data-fechar-modal>
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="icone-confirmacao-exclusao">
            <i class="fa-solid fa-trash"></i>
        </div>

        <h3 class="titulo-confirmacao-exclusao">
            Excluir evento
        </h3>

        <p class="txt-confirmacao">
            Tem certeza que deseja excluir este evento?
        </p>

        <form method="POST">

            <input type="hidden" name="excluir_evento" value="1">
            <input type="hidden" name="id_evento" id="delIdEvento">

            <div class="modal-actions">

                <button
                    type="submit"
                    class="btn-action btn-delete-confirmar"
                >
                    Excluir
                </button>

                <button type="button" class="btn-cancel" data-fechar-modal>
                    Cancelar
                </button>

            </div>

        </form>

    </div>

</div>

<script>
    window.CALENDARIO_CONFIG = {
        turmaSelecionada: <?= json_encode($id_turma_selecionada) ?>,
        modoEditor: <?= $modo_editor ? 'true' : 'false' ?>,
        modoGestao: <?= $modo_gestao ? 'true' : 'false' ?>
    };

    document.addEventListener("DOMContentLoaded", function () {

        /*
         * ==========================================
         * CONFIGURAÇÃO
         * ==========================================
         */

        const config = window.CALENDARIO_CONFIG;


        /*
         * ==========================================
         * MODAIS
         * ==========================================
         */

        const modalAviso = document.getElementById("modalAviso");
        const modalCriar = document.getElementById("modalCriar");
        const modalConfirmar = document.getElementById("modalConfirmar");
        const modalVer = document.getElementById("modalVer");
        const modalEditar = document.getElementById("modalEditar");
        const modalExcluir = document.getElementById("modalExcluir");


        /*
         * ==========================================
         * FUNÇÕES PARA ABRIR E FECHAR MODAIS
         * ==========================================
         */

        function abrirModal(modal) {

            if (!modal) {
                return;
            }

            modal.style.display = "flex";

        }


        function fecharModal(modal) {

            if (!modal) {
                return;
            }

            modal.style.display = "none";

        }


        /*
         * ==========================================
         * GARANTE QUE OS MODAIS COMEÇAM FECHADOS
         * ==========================================
         */

        [modalAviso, modalCriar, modalConfirmar, modalVer, modalEditar, modalExcluir]
            .forEach(function (modal) {

                if (modal) {
                    modal.style.display = "none";
                }

            });


        /*
         * ==========================================
         * BOTÕES DE FECHAR
         * ==========================================
         */

        document.querySelectorAll("[data-fechar-modal]").forEach(function (botao) {

            botao.addEventListener("click", function () {

                const modal = botao.closest(".modal-overlay");

                fecharModal(modal);

            });

        });


        /*
         * ==========================================
         * CLICAR FORA DA CAIXA PARA FECHAR
         * ==========================================
         */

        document.querySelectorAll(".modal-overlay").forEach(function (modal) {

            modal.addEventListener("click", function (evento) {

                if (evento.target === modal) {

                    fecharModal(modal);

                }

            });

        });


        /*
         * ==========================================
         * DROPDOWN DE TURMAS
         * ==========================================
         */

        const btnDropdown = document.querySelector(".btn-dropdown");
        const dropdownMenu = document.querySelector(".dropdown-menu");

        if (btnDropdown && dropdownMenu) {

            btnDropdown.addEventListener("click", function (evento) {

                evento.stopPropagation();

                dropdownMenu.classList.toggle("ativo");

            });

        }


        /*
         * ==========================================
         * VARIÁVEIS DOS FORMULÁRIOS
         * ==========================================
         */

        const tempNome = document.getElementById("tempNome");

        const alertaForm = document.getElementById("alertaForm");

        const btnAbrirConfirmacao =
            document.getElementById("btnAbrirConfirmacao");

        const finalData =
            document.getElementById("finalData");

        const finalNome =
            document.getElementById("finalNome");

        const finalTipo =
            document.getElementById("finalTipo");


        /*
         * Guarda a data que o representante clicou.
         */

        let dataSelecionada = null;


        /*
         * Guarda os eventos do dia selecionado.
         */

        let eventosSelecionados = [];
        let eventoAcaoSelecionado = null;


        /*
         * ==========================================
         * CLIQUE NOS DIAS DO CALENDÁRIO
         * ==========================================
         */

        const diasCalendario =
            document.querySelectorAll(".calendar-day:not(.empty-day)");


        diasCalendario.forEach(function (dia) {

            dia.addEventListener("click", function () {

                /*
                 * Pega a data do dia.
                 */

                dataSelecionada = dia.getAttribute("data-date");


                /*
                 * Pega os eventos daquele dia.
                 */

                let eventos = [];

                const dadosEventos =
                    dia.getAttribute("data-events");


                if (dadosEventos) {

                    try {

                        eventos = JSON.parse(dadosEventos);

                    } catch (erro) {

                        console.error(
                            "Erro ao carregar os eventos:",
                            erro
                        );

                        eventos = [];

                    }

                }


                eventosSelecionados = eventos;


                /*
                 * ==================================
                 * SE NÃO EXISTE TURMA SELECIONADA
                 * ==================================
                 */

                if (!config.turmaSelecionada && !config.modoGestao) {

                    abrirModal(modalAviso);

                    return;

                }


                /*
                 * ==================================
                 * SE JÁ EXISTE EVENTO
                 * ==================================
                 */

                if (eventos.length > 0) {

                    abrirModalVer(eventos);

                    return;

                }


                /*
                 * ==================================
                 * SE NÃO É REPRESENTANTE
                 * ==================================
                 */

                if (!config.modoEditor && !config.modoGestao) {
                    return;
                }


                /*
                 * ==================================
                 * REPRESENTANTE PODE ADICIONAR
                 * ==================================
                 */

                if (tempNome) {
                    tempNome.value = "";
                }

                if (alertaForm) {
                    alertaForm.style.display = "none";
                }


                /*
                 * Coloca o tipo Prova como padrão.
                 */

                const tipoPadrao =
                    document.querySelector(
                        'input[name="tempTipo"][value="Prova"]'
                    );

                if (tipoPadrao) {
                    tipoPadrao.checked = true;
                }


                /*
                 * Abre o modal de criação.
                 */

                abrirModal(modalCriar);

            });

        });


        /*
         * ==========================================
         * ABRIR MODAL DE VISUALIZAÇÃO
         * ==========================================
         */

        function abrirModalVer(eventos) {

            const listaEventos =
                document.getElementById("listaEventos");


            if (!listaEventos) {
                return;
            }


            listaEventos.innerHTML = "";

            // Define qual evento poderá ser editado/excluído.
            // Gestão -> somente eventos gerais.
            // Representante -> somente eventos da própria turma.
            eventoAcaoSelecionado = eventos.find(function (evento) {
                if (config.modoGestao) {
                    return evento.id_turma === null || evento.id_turma === undefined || evento.id_turma === "";
                }

                if (config.modoEditor) {
                    return String(evento.id_turma) === String(config.turmaSelecionada);
                }

                return false;
            }) || null;

            if (btnEditarEvento) {
                btnEditarEvento.style.display = eventoAcaoSelecionado ? "inline-flex" : "none";
            }

            if (btnExcluirEvento) {
                btnExcluirEvento.style.display = eventoAcaoSelecionado ? "inline-flex" : "none";
            }

            eventos.forEach(function (evento) {

                const item =
                    document.createElement("div");

                item.className = "evento-item";


                const titulo =
                    document.createElement("strong");

                titulo.textContent =
                    evento.tipo;


                const descricao =
                    document.createElement("p");

                descricao.textContent =
                    evento.nome;


                item.appendChild(titulo);
                item.appendChild(descricao);


                listaEventos.appendChild(item);

            });


            abrirModal(modalVer);

        }


        /*
         * ==========================================
         * BOTÃO "ADICIONAR" DO PRIMEIRO MODAL
         * ==========================================
         */

        if (btnAbrirConfirmacao) {

            btnAbrirConfirmacao.addEventListener(
                "click",
                function () {

                    const nome =
                        tempNome.value.trim();


                    /*
                     * Verifica descrição.
                     */

                    if (nome === "") {

                        if (alertaForm) {

                            alertaForm.textContent =
                                "Por favor, preencha a descrição do evento.";

                            alertaForm.style.display = "block";

                        }

                        return;

                    }


                    /*
                     * Verifica a data.
                     */

                    if (!dataSelecionada) {

                        return;

                    }


                    /*
                     * Pega o tipo escolhido.
                     */

                    const tipoSelecionado =
                        document.querySelector(
                            'input[name="tempTipo"]:checked'
                        );


                    if (!tipoSelecionado) {

                        if (alertaForm) {

                            alertaForm.textContent =
                                "Selecione o tipo do evento.";

                            alertaForm.style.display = "block";

                        }

                        return;

                    }


                    /*
                     * Coloca os valores no formulário
                     * de confirmação.
                     */

                    finalData.value =
                        dataSelecionada;

                    finalNome.value =
                        nome;

                    finalTipo.value =
                        tipoSelecionado.value;


                    /*
                     * Fecha o primeiro modal.
                     */

                    fecharModal(modalCriar);


                    /*
                     * Abre confirmação.
                     */

                    abrirModal(modalConfirmar);

                }
            );

        }


        /*
         * ==========================================
         * EDITAR EVENTO
         * ==========================================
         */

        const btnEditarEvento =
            document.getElementById("btnEditarEvento");

        if (btnEditarEvento) {

            btnEditarEvento.addEventListener(
                "click",
                function () {

                    if (!config.modoEditor && !config.modoGestao) {
                        return;
                    }


                    if (eventosSelecionados.length === 0) {
                        return;
                    }


                    /*
                     * Pega o primeiro evento.
                     */

                    const evento = eventoAcaoSelecionado;

                    if (!evento) {
                        return;
                    }

                    document.getElementById("editIdEvento").value =
                        evento.id_eventos;


                    document.getElementById("editNome").value =
                        evento.nome;


                    /*
                     * Limpa os radio buttons.
                     */

                    document.getElementById("editTipoProva").checked =
                        false;

                    document.getElementById("editTipoTrabalho").checked =
                        false;

                    document.getElementById("editTipoEvento").checked =
                        false;


                    /*
                     * Marca o tipo correto.
                     */

                    if (evento.tipo === "Prova") {

                        document.getElementById("editTipoProva").checked =
                            true;

                    }

                    if (evento.tipo === "Trabalho") {

                        document.getElementById("editTipoTrabalho").checked =
                            true;

                    }

                    if (evento.tipo === "Evento") {

                        document.getElementById("editTipoEvento").checked =
                            true;

                    }


                    fecharModal(modalVer);

                    abrirModal(modalEditar);

                }
            );

        }


        /*
         * ==========================================
         * EXCLUIR EVENTO
         * ==========================================
         */

        const btnExcluirEvento =
            document.getElementById("btnExcluirEvento");

        if (btnExcluirEvento) {

            btnExcluirEvento.addEventListener(
                "click",
                function () {

                    if (!config.modoEditor && !config.modoGestao) {
                        return;
                    }


                    if (eventosSelecionados.length === 0) {
                        return;
                    }


                    const evento = eventoAcaoSelecionado;

                    if (!evento) {
                        return;
                    }

                    document.getElementById("delIdEvento").value =
                        evento.id_eventos;


                    fecharModal(modalVer);

                    abrirModal(modalExcluir);

                }
            );

        }


        /*
         * ==========================================
         * ESC FECHA O MODAL
         * ==========================================
         */

        document.addEventListener(
            "keydown",
            function (evento) {

                if (evento.key === "Escape") {

                    [
                        modalAviso,
                        modalCriar,
                        modalConfirmar,
                        modalVer,
                        modalEditar,
                        modalExcluir
                    ].forEach(function (modal) {

                        fecharModal(modal);

                    });

                }

            }
        );

    });
</script>

<section>
      <div class="container">
        <div class="row">
          <div class="col-sm-2 offset-sm-5 text-center">
            <button class="btn btn-warning text-white btn-block" onclick="voltar()">Voltar</button>
            <script>
              function voltar(){
                history.back();
              }
            </script>
          </div>
        </div>
      </div>
    </section><!-- End About Section -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
