<?php
// 1. Inicia a sessão PHP
session_start();

// 2. Verifica se a variável de sessão do usuário existe
if (!isset($_SESSION['usuario_id'])) {
    // Se não estiver logado, interrompe o acesso e redireciona para a tela de login
    header("Location: login.php");
    exit();
}

// 3. (Opcional) Restrição por tipo de usuário
// Se apenas professores ou administradores puderem agendar:
if ($_SESSION['usuario_tipo'] !== 'professor' && $_SESSION['usuario_tipo'] !== 'administrador') {
    // Redireciona para o painel correto ou exibe erro de acesso negado
    header("Location: login.php");
    exit();
}

// 1. Processa o Logout via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    
    // Força o redirecionamento com substituição de histórico
    header("Location: login.php");
    exit();
}

// 2. Trava de segurança da sessão
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// 3. Cabeçalhos Anti-Cache agressivos
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Trava de segurança para usuários não logados
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

//Maria Alice - 06/08, 15h06 Alterações: Tudo acima, impedir acesso por url sem login, logout(provisório), não continuar logado depois que sair e voltar

include("conexao.php");

$ocupados = [];

if (isset($_GET["data"]) && isset($_GET["horario"])) {

    $data = $_GET["data"];
    $horario = $_GET["horario"];

    $sql = "SELECT id_ambientes
            FROM agendamentos
            WHERE data_agendamento = '$data'
            AND horario = '$horario'";

    $resultado = mysqli_query($conexao, $sql);

    while ($linha = mysqli_fetch_assoc($resultado)) {
        $ocupados[] = $linha["id_ambientes"];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Agendamento</title>

<link rel="stylesheet" href="agendamento.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>

<body>

<header>

    <div class="logo">
        <img src="logo.png">
    </div>

    <nav>
        <a href="">Home</a>
        <a href="">Cursos</a>
        <a href="">A Etec</a>
    </nav>

    <div class="menu">
        <i class="fa-solid fa-bars"></i>
    </div>

</header>

<section class="titulo">

    <h1>Agendamento - Laboratórios DS</h1>

</section>

<!-- Maria Alice - 06/08, 15h06 do form até o nav !-->
<form action="agendamento.php" method="POST" style="display:inline;"> 
        <input type="hidden" name="logout" value="1">
        <button type="submit" style="background:none; border:none; color:#ff4d4d; cursor:pointer; font:inherit;">
            <i class="fa-solid fa-right-from-bracket"></i> Sair
        </button>
    </form>
</nav>

<div class="container">
    <div class="filtros">

     <form method="POST">
        <div class="campo">

            <label>Data:</label>
            <input type="date" id="data">
      

       <div class="campo">
    <label for="horario">Horário:</label>

    <div class="campo-horario">
        <select id="horario" name="horario">
            <option value="" selected disabled></option>
            <option>7h30 - 8h20</option>
            <option>8h20 - 9h10</option>
            <option>9h10 - 10h</option>
            <option>10h20 - 11h10</option>
            <option>11h10 - 12h</option>
            <option>13h - 13h50</option>
            <option>13h50 - 14h40</option>
            <option>14h40 - 15h30</option>
        </select>

        <i class="fa-regular fa-clock"></i>
    </div>
</div>
</div>
</div>
</form>

    <div class="conteudo">
<div class="mapa">
    
<div class="lab <?= in_array(3,$ocupados) ? 'ocupado' : '' ?>"
     onclick="<?= in_array(3,$ocupados) ? '' : 'selecionarLab(3)' ?>">
    LAB 3
</div>

<div class="lab <?= in_array(4,$ocupados) ? 'ocupado' : '' ?>"
     onclick="<?= in_array(4,$ocupados) ? '' : 'selecionarLab(4)' ?>">
    LAB 4
</div>

<div class="lab <?= in_array(5,$ocupados) ? 'ocupado' : '' ?>"
     onclick="<?= in_array(5,$ocupados) ? '' : 'selecionarLab(5)' ?>">
    LAB 5
</div>

<div class="lab <?= in_array(2,$ocupados) ? 'ocupado' : '' ?>"
     onclick="<?= in_array(2,$ocupados) ? '' : 'selecionarLab(2)' ?>">
    LAB 2
</div>

<div class="vazio"></div>

<div class="lab <?= in_array(1,$ocupados) ? 'ocupado' : '' ?>"
     onclick="<?= in_array(1,$ocupados) ? '' : 'selecionarLab(1)' ?>">
    LAB 1
</div>

</div>

<div id="formReserva" style="display:none;">

    <h2>Solicitar reserva</h2>

    <p id="labEscolhido"></p>
    <p id="dataEscolhida"></p>
    <p id="horarioEscolhido"></p>

    <label>Nome do professor:</label>
    <input type="text">

    <label>Descrição:</label>
    <textarea></textarea>

    <button>Enviar solicitação</button>

</div>

        <div class="legenda">

            <h2>Legenda:</h2>

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
   document.addEventListener("DOMContentLoaded", function () {

    const data = document.getElementById("data");
    const horario = document.getElementById("horario");

    function atualizarPagina() {

        if (data.value !== "" && horario.value !== "") {

            // guarda os valores antes de atualizar
            sessionStorage.setItem("data", data.value);
            sessionStorage.setItem("horario", horario.value);

            window.location.href = 
"agendamento.php?data=" + data.value + "&horario=" + horario.value;
        }

    }


    data.addEventListener("change", atualizarPagina);
    horario.addEventListener("change", atualizarPagina);


    // recupera os valores depois do reload
    if(sessionStorage.getItem("data")){
        data.value = sessionStorage.getItem("data");
    }

    if(sessionStorage.getItem("horario")){
        horario.value = sessionStorage.getItem("horario");
    }

});

function selecionarLab(id){

    document.getElementById("formReserva").style.display = "block";

    document.getElementById("labEscolhido").innerHTML =
    "<strong>Laboratório:</strong> LAB " + id;

    document.getElementById("dataEscolhida").innerHTML =
    "<strong>Data:</strong> " + document.getElementById("data").value;

    document.getElementById("horarioEscolhido").innerHTML =
    "<strong>Horário:</strong> " + document.getElementById("horario").value;

}

</script>

<script>
window.onpageshow = function(event) {
    if (event.persisted || (performance && performance.navigation.type === 2)) {
        // Se veio do botão voltar, esconde o corpo do site e redireciona
        document.body.innerHTML = '';
        window.location.replace("login.php");
    }
};
</script>

</body>
</html>