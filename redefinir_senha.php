
<?php

include("conexao.php");

if (!isset($_GET['token'])) {
    die("Token de recuperação não informado.");
}

$token = $_GET['token'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>

<main class="container-login">

    <div class="card-login">

        <h2>Nova senha</h2>

        <form action="salvar_senha.php" method="POST">

            <input 
                type="hidden" 
                name="token" 
                value="<?php echo htmlspecialchars($token); ?>"
            >

            <div class="campo">

                <label for="senha">Nova senha:</label>

                <div class="input-com-icone">

                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="Digite sua nova senha"
                        required
                    >

                    <i class="fa-solid fa-lock"></i>

                </div>

            </div>

            <button type="submit" class="btn-entrar">

                Alterar senha

                <i class="fa-solid fa-key"></i>

            </button>

        </form>

    </div>

</main>
<!-- Maria A. - Alterações: não aparecer a senha e tudo mais ao voltar !-->
<script>
// Evita que o navegador salve os dados do formulário no histórico de navegação
document.querySelector("form").addEventListener("submit", function() {
    // Substitui o estado atual do histórico por uma versão "limpa" antes do envio
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Agenda a limpeza dos campos logo após o disparo da requisição
    setTimeout(function() {
        document.getElementById("senha").value = "";
    }, 10);
});
</script>
<!-- Maria A. Câmbio desligo !-->
</body>
</html>