<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

include("conexao.php");

/*
 * Ao abrir a tela de Eventos, o acesso especial anterior é apagado.
 * Assim, voltar a clicar em "Eventos" sempre mostra o login.
 */
unset($_SESSION["eventos_editor"]);
unset($_SESSION["eventos_representante_id"]);

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["apenas_visualizar"])) {

        $_SESSION["eventos_editor"] = false;

        header("Location: calendario.php");
        exit();
    }

    if (isset($_POST["entrar_representante"])) {

        $email = trim($_POST["email"] ?? "");
        $senha = $_POST["senha"] ?? "";

        if ($email === "" || $senha === "") {
            $erro = "Preencha o e-mail e a senha.";
        } else {

            $sql = "SELECT id_representante, nome, email, senha, id_turma
                    FROM representante
                    WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
                    LIMIT 1";

            $stmt = mysqli_prepare($conexao, $sql);

            if ($stmt) {

                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);

                $resultado = mysqli_stmt_get_result($stmt);

                if ($resultado && mysqli_num_rows($resultado) === 1) {

                    $representante = mysqli_fetch_assoc($resultado);
                    $senha_valida = false;

                    if (!empty($representante["senha"])) {

                        $senha_valida = password_verify(
                            $senha,
                            $representante["senha"]
                        );

                        /* Compatibilidade com cadastros antigos. */
                        if (
                            !$senha_valida &&
                            hash_equals(
                                (string)$representante["senha"],
                                (string)$senha
                            )
                        ) {
                            $senha_valida = true;
                        }
                    }

                    if ($senha_valida) {

                        $_SESSION["eventos_editor"] = true;
                        $_SESSION["eventos_representante_id"] =
                            (int)$representante["id_representante"];

                        $id_turma = (int)$representante["id_turma"];

                        mysqli_stmt_close($stmt);

                        header(
                            "Location: calendario.php?turma=" . $id_turma
                        );
                        exit();
                    }
                }

                mysqli_stmt_close($stmt);
            }

            $erro = "E-mail ou senha de representante inválidos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/eventos_login.css">
</head>

<body>

    <main class="container-login">
        <div class="card-login">

            <h2>Entrar</h2>

            <?php if (!empty($erro)): ?>
                <div class="mensagem-erro">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="campo">
                    <label for="email">E-mail:</label>

                    <div class="input-com-icone">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Digite seu e-mail"
                            autocomplete="username"
                            required
                        >
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                </div>

                <div class="campo">
                    <label for="senha">Senha:</label>

                    <div class="input-com-icone senha-container">
                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            placeholder="Digite sua senha"
                            autocomplete="current-password"
                            required
                        >

                        <i
                            class="fa-solid fa-eye olho-senha"
                            id="mostrarSenha"
                            aria-label="Mostrar senha"
                        ></i>
                    </div>
                </div>

                <button type="submit" name="entrar_representante" class="btn-entrar">
                    Entrar <i class="fa-solid fa-right-to-bracket"></i>
                </button>

            </form>

            <div class="eventos-separador">
                <span>ou</span>
            </div>

            <form method="POST">
                <button type="submit" name="apenas_visualizar" class="btn-visualizar">
                    <i class="fa-solid fa-eye"></i>
                    Apenas visualizar
                </button>
            </form>

        </div>
    </main>

    <script src="../js/eventos_login.js"></script>

</body>
</html>
