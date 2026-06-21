<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <h2>Bem-vindo ao painel SINAL, {{ $ownerName }}!</h2>
    <p>A loja <strong>{{ $storeName }}</strong> já está cadastrada. Use os dados abaixo para acessar o painel:</p>
    <p>
        <strong>E-mail:</strong> {{ $email }}<br>
        <strong>Senha temporária:</strong> {{ $password }}
    </p>
    <p>No primeiro acesso você vai precisar trocar essa senha por uma definitiva.</p>
</body>
</html>
