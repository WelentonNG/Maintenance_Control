<?php
/**
 * Gerador de Hash Bcrypt
 * Este arquivo permite gerar hashes bcrypt para senhas
 */

// Impede acesso direto ao arquivo em produção (remova comentário se necessário)
// if (!defined('ALLOW_HASH_GENERATOR')) {
//     die('Acesso negado');
// }

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Hash Bcrypt</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .result {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        
        .result h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .hash-output {
            background: white;
            padding: 15px;
            border-radius: 5px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #333;
            border: 1px solid #e1e1e1;
            margin-bottom: 10px;
        }
        
        .copy-btn {
            background: #28a745;
            padding: 8px 16px;
            width: auto;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .copy-btn:hover {
            background: #218838;
        }
        
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #2196F3;
        }
        
        .info h4 {
            color: #1976D2;
            margin-bottom: 8px;
        }
        
        .info p {
            color: #555;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .cost-info {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Gerador de Hash Bcrypt</h1>
        <p class="subtitle">Gere hashes seguros para suas senhas</p>
        
        <div class="alert alert-warning">
            ⚠️ <strong>Atenção:</strong> Este arquivo deve ser removido ou protegido em ambiente de produção!
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">Senha para gerar hash:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Digite a senha">
            </div>
            
            <div class="form-group">
                <label for="cost">Custo do algoritmo (4-12 recomendado):</label>
                <select id="cost" name="cost">
                    <option value="10" selected>10 (Padrão - Recomendado)</option>
                    <option value="8">8 (Mais rápido)</option>
                    <option value="12">12 (Mais seguro)</option>
                    <option value="4">4 (Desenvolvimento/Teste)</option>
                </select>
            </div>
            
            <button type="submit" name="generate">Gerar Hash</button>
        </form>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
            $password = $_POST['password'];
            $cost = isset($_POST['cost']) ? intval($_POST['cost']) : 10;
            
            // Valida o custo (deve estar entre 4 e 31)
            if ($cost < 4) $cost = 4;
            if ($cost > 31) $cost = 31;
            
            // Gera o hash usando bcrypt
            $options = ['cost' => $cost];
            $hash = password_hash($password, PASSWORD_BCRYPT, $options);
            
            // Verifica se o hash foi gerado com sucesso
            if ($hash) {
                echo '<div class="result">';
                echo '<h3>✅ Hash gerado com sucesso!</h3>';
                echo '<div class="hash-output" id="hashOutput">' . htmlspecialchars($hash) . '</div>';
                echo '<button class="copy-btn" onclick="copyHash()">📋 Copiar Hash</button>';
                echo '<div class="cost-info">';
                echo '<strong>Informações:</strong><br>';
                echo '• Algoritmo: BCRYPT<br>';
                echo '• Custo: ' . $cost . '<br>';
                echo '• Comprimento: ' . strlen($hash) . ' caracteres';
                echo '</div>';
                echo '</div>';
                
                // Teste de verificação
                if (password_verify($password, $hash)) {
                    echo '<div class="info">';
                    echo '<h4>✓ Verificação de Hash</h4>';
                    echo '<p>O hash foi testado e está funcionando corretamente. ';
                    echo 'Use a função <code>password_verify($senha, $hash)</code> no PHP para verificar senhas.</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="result" style="border-left-color: #dc3545;">';
                echo '<h3>❌ Erro ao gerar hash</h3>';
                echo '<p>Não foi possível gerar o hash. Verifique os parâmetros.</p>';
                echo '</div>';
            }
        }
        ?>
        
        <div class="info">
            <h4>ℹ️ Como usar no seu código PHP</h4>
            <p>
                <strong>Para criar hash:</strong><br>
                <code>$hash = password_hash($senha, PASSWORD_BCRYPT);</code><br><br>
                
                <strong>Para verificar senha:</strong><br>
                <code>if (password_verify($senha_digitada, $hash_do_banco)) {<br>
                &nbsp;&nbsp;// Senha correta<br>
                }</code>
            </p>
        </div>
        
        <div class="info">
            <h4>📊 Sobre o custo (cost)</h4>
            <p>
                • <strong>Custo 4:</strong> Rápido, apenas para testes<br>
                • <strong>Custo 10:</strong> Equilibrado (padrão)<br>
                • <strong>Custo 12:</strong> Mais seguro, mas mais lento<br>
                • Cada aumento dobra o tempo de processamento<br>
                • Maior custo = mais proteção contra ataques de força bruta
            </p>
        </div>
    </div>
    
    <script>
        function copyHash() {
            const hashText = document.getElementById('hashOutput').textContent;
            
            // Cria um elemento temporário para copiar
            const tempInput = document.createElement('textarea');
            tempInput.value = hashText;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            
            // Feedback visual
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '✓ Copiado!';
            btn.style.background = '#218838';
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.background = '#28a745';
            }, 2000);
        }
    </script>
</body>
</html>
