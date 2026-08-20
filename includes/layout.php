<?php
/**
 * ConnectWork — Casca das páginas internas
 *
 * Estrutura visual do sistema: sidebar + topbar + main, com o menu
 * montado a partir do nível do usuário — cada perfil vê apenas as
 * funções que pode acessar.
 *
 * Esconder o item do menu é conforto. A restrição de verdade continua
 * sendo o Auth::exigirNivel() no topo de cada página.
 */

require_once __DIR__ . '/auth.php';

/**
 * Menu por nível de acesso.  [ rótulo, ícone, caminho, chave ]
 */
function menu_do_nivel(string $nivel): array
{
    switch ($nivel) {
        case 'master':
            return [
                ['Plataforma', 'master/index.php', 'painel'],
                ['Empresas', 'master/empresas.php', 'empresas'],
                ['Planos', 'master/planos.php', 'planos'],
                ['Auditoria', 'master/auditoria.php', 'auditoria'],
            ];

        case 'admin':
            return [
                ['Dashboard', 'admin/index.php', 'painel'],
                ['Ponto', 'admin/ponto.php', 'ponto'],
                ['Aprovações', 'admin/aprovacoes.php', 'aprovacoes'],
                ['Funcionários', 'admin/funcionarios.php', 'funcionarios'],
                ['Departamentos', 'admin/departamentos.php', 'departamentos'],
                ['Cercas virtuais', 'admin/cercas.php', 'cercas'],
                ['Mensagens', 'mensagens.php', 'mensagens'],
                ['Ouvidoria', 'admin/ouvidoria.php', 'ouvidoria'],
                ['Vagas', 'admin/vagas.php', 'vagas'],
                ['Sugestões', 'admin/sugestoes.php', 'sugestoes'],
                ['Comunicados', 'comunicados.php', 'comunicados'],
                ['Relatórios', 'admin/relatorios.php', 'relatorios'],
                ['Auditoria', 'admin/auditoria.php', 'auditoria'],
                ['Configurações', 'admin/configuracoes.php', 'configuracoes'],
                ['Assistente', 'ia.php', 'assistente'],
            ];

        case 'gerente':
            return [
                ['Dashboard', 'gerente/index.php', 'painel'],
                ['Ponto da equipe', 'gerente/ponto.php', 'ponto'],
                ['Minha equipe', 'gerente/equipe.php', 'equipe'],
                ['Meu ponto', 'ponto.php', 'meuponto'],
                ['Mensagens', 'mensagens.php', 'mensagens'],
                ['Comunicados', 'comunicados.php', 'comunicados'],
                ['Vagas', 'vagas.php', 'vagas'],
                ['Sugestões', 'sugestoes.php', 'sugestoes'],
                ['Disponibilidade', 'disponibilidade.php', 'disponibilidade'],
                ['Assistente', 'ia.php', 'assistente'],
            ];

        default:
            return [
                ['Dashboard', 'funcionario/index.php', 'painel'],
                ['Ponto', 'ponto.php', 'ponto'],
                ['Mensagens', 'mensagens.php', 'mensagens'],
                ['Ouvidoria', 'ouvidoria.php', 'ouvidoria'],
                ['Vagas', 'vagas.php', 'vagas'],
                ['Sugestões', 'sugestoes.php', 'sugestoes'],
                ['Disponibilidade', 'disponibilidade.php', 'disponibilidade'],
                ['Comunicados', 'comunicados.php', 'comunicados'],
                ['Assistente', 'ia.php', 'assistente'],
            ];
    }
}

function rotulo_nivel(string $nivel): string
{
    switch ($nivel) {
        case 'master':  return 'Administrador Master';
        case 'admin':   return 'Administrador';
        case 'gerente': return 'Gerente';
        default:        return 'Funcionário';
    }
}

/** Iniciais para o avatar circular. */
function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $ini = '';
    foreach ($partes as $p) {
        if ($p !== '') { $ini .= mb_strtoupper(mb_substr($p, 0, 1)); }
        if (mb_strlen($ini) >= 2) { break; }
    }
    return $ini !== '' ? $ini : 'CW';
}

/** Guarda uma mensagem para exibir depois do redirecionamento. */
function flash(string $tipo, string $texto): void
{
    sessao_iniciar();
    $_SESSION['cw_flash'][] = ['tipo' => $tipo, 'texto' => $texto];
}

function flash_render(): string
{
    sessao_iniciar();
    $itens = $_SESSION['cw_flash'] ?? [];
    unset($_SESSION['cw_flash']);
    $html = '';
    foreach ($itens as $i) {
        $html .= '<div class="alert alert-' . e($i['tipo']) . '" role="status">' . e($i['texto']) . '</div>';
    }
    return $html;
}

/** Redireciona para uma página do próprio sistema. */
function voltar_para(string $caminho): void
{
    header('Location: ' . url($caminho));
    exit;
}

/**
 * Retorna a URL da identidade visual da empresa atual.
 * A logo é armazenada por empresa em uploads/logos e não precisa
 * de coluna adicional no banco. O Master usa a logo padrão.
 */
function logo_empresa_url(): string
{
    $padrao = url('assets/logo.png');
    if (Auth::ehMaster()) {
        return $padrao;
    }

    $empresaId = Auth::empresaId();
    if (!$empresaId) {
        return $padrao;
    }

    $dir = CW_UPLOAD_DIR . '/logos';
    $arquivos = glob($dir . '/empresa_' . (int) $empresaId . '.*') ?: [];
    foreach ($arquivos as $arquivo) {
        if (is_file($arquivo)) {
            $nome = basename($arquivo);
            $versao = (int) @filemtime($arquivo);
            return url('uploads/logos/' . $nome) . ($versao ? '?v=' . $versao : '');
        }
    }

    return $padrao;
}

/**
 * @param string $titulo nome da página (aba do navegador)

 * @param string $ativo  chave do item de menu em destaque
 * @param string $h1     título grande da página
 * @param string $sub    linha de apoio abaixo do título
 * @param string $acoes  HTML dos botões no canto direito do cabeçalho
 */
function cabecalho(string $titulo, string $ativo = '', string $h1 = '', string $sub = '', string $acoes = ''): void
{
    cabecalhos_seguranca();
    $nivel = Auth::nivel() ?? 'funcionario';
    $itens = menu_do_nivel($nivel);

    $naoLidas = (Auth::id() && Auth::empresaId())
        ? Db::contar('notificacoes', 'usuario_id = :u AND lida_em IS NULL', ['u' => Auth::id()])
        : 0;
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($titulo) ?> — <?= e(CW_NOME) ?></title>
<link rel="icon" type="image/png" href="<?= e(logo_empresa_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('css/complementos.css')) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a class="logo" href="<?= e(Auth::paginaInicial()) ?>">
      <img src="<?= e(logo_empresa_url()) ?>" alt="Logo da empresa" class="logo-img">
      <div class="logo-text">
        <span class="logo-title">ConnectWork</span>
        <span class="logo-sub">Gestão empresarial</span>
      </div>
    </a>
  </div>

  <nav class="sidebar-nav" aria-label="Menu principal">
    <?php foreach ($itens as [$rotulo, $href, $chave]): ?>
      <a href="<?= e(url($href)) ?>" class="nav-item<?= $chave === $ativo ? ' active' : '' ?>"
         <?= $chave === $ativo ? 'aria-current="page"' : '' ?>>
        <span><?= e($rotulo) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= e(iniciais(Auth::nome())) ?></div>
      <div class="user-info">
        <span class="user-name"><?= e(Auth::nome()) ?></span>
        <span class="user-role"><?= e(rotulo_nivel($nivel)) ?></span>
        <?php if (Auth::empresaNome() !== '' && !Auth::ehMaster()): ?>
          <span class="user-company"><?= e(Auth::empresaNome()) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</aside>

<main class="main" id="appMain">
  <header class="topbar">
    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Abrir menu">Menu</button>

    <form class="search" method="get" action="<?= e(url('busca.php')) ?>">
      <input type="text" name="q" placeholder="Buscar funcionário, vaga, sugestão, relato..."
             value="<?= e(entrada('q', 'get')) ?>" autocomplete="off">
    </form>

    <div class="topbar-actions">
      <a class="topbar-link" href="<?= e(url('notificacoes.php')) ?>">Notificações<?php
        if ($naoLidas > 0) { echo '<span class="dot"></span>'; } ?></a>
      <a class="topbar-link" href="<?= e(url('logout.php')) ?>">Sair</a>
      <div class="avatar-sm"><?= e(iniciais(Auth::nome())) ?></div>
    </div>
  </header>

  <?php if ($h1 !== ''): ?>
  <div class="page-header">
    <div>
      <h1><?= $h1 ?></h1>
      <?php if ($sub !== ''): ?><p><?= $sub ?></p><?php endif; ?>
    </div>
    <?php if ($acoes !== ''): ?><div class="header-actions"><?= $acoes ?></div><?php endif; ?>
  </div>
  <?php endif; ?>

  <?= flash_render() ?>
<?php
}

function rodape(): void
{
    ?>
</main>

<div class="toast" id="toast"></div>
<script src="<?= e(url('js/app.js')) ?>"></script>
</body>
</html>
<?php
}

// ---------------------------------------------------------------------
// Auxiliares de tela
// ---------------------------------------------------------------------

function badge(string $texto, string $cor = 'gray'): string
{
    return '<span class="badge ' . e($cor) . '">' . e($texto) . '</span>';
}

function badge_status_ouvidoria(string $status): string
{
    $cor  = ['aberta' => 'red', 'em_analise' => 'yellow', 'respondida' => 'blue', 'encerrada' => 'green'];
    $nome = ['aberta' => 'Aberta', 'em_analise' => 'Em análise', 'respondida' => 'Respondida', 'encerrada' => 'Encerrada'];
    return badge($nome[$status] ?? $status, $cor[$status] ?? 'gray');
}

function badge_status_funcionario(string $status): string
{
    $cor = ['ativo' => 'green', 'afastado' => 'yellow', 'desligado' => 'red'];
    return badge(ucfirst($status), $cor[$status] ?? 'gray');
}

function vazio(string $titulo, string $texto = ''): string
{
    return '<div class="empty"><b>' . e($titulo) . '</b>' . e($texto) . '</div>';
}

function data_br(?string $dataHora, bool $comHora = false): string
{
    if (!$dataHora) { return '—'; }
    return date($comHora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($dataHora));
}

/** Data por extenso em português (strftime foi descontinuado no PHP 8.1). */
function data_extenso(?int $ts = null): string
{
    $ts = $ts ?? time();
    $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
              'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return $dias[(int) date('w', $ts)] . ', ' . date('d', $ts)
         . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

/** Nome curto para exibir em listas. */
function primeiro_nome(string $nome): string
{
    $p = explode(' ', trim($nome));
    return $p[0] ?: $nome;
}
