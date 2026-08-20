-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 11/08/2026 às 13:03
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `connectwork`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `arquivos`
--

CREATE TABLE `arquivos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `categoria` varchar(60) DEFAULT NULL,
  `nome_original` varchar(255) NOT NULL,
  `caminho` varchar(255) NOT NULL COMMENT 'relativo a uploads/, com nome gerado',
  `mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` int(10) UNSIGNED DEFAULT NULL,
  `enviado_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria`
--

CREATE TABLE `auditoria` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED DEFAULT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `acao` varchar(60) NOT NULL,
  `entidade` varchar(60) DEFAULT NULL,
  `entidade_id` bigint(20) UNSIGNED DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `auditoria`
--

INSERT INTO `auditoria` (`id`, `empresa_id`, `usuario_id`, `acao`, `entidade`, `entidade_id`, `detalhes`, `ip`, `user_agent`, `criado_em`) VALUES
(1, NULL, 1, 'login', 'usuarios', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:37:34'),
(2, NULL, 1, 'logout', 'usuarios', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:38:23'),
(3, 1, 2, 'login', 'usuarios', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:38:44'),
(4, 1, 2, 'login', 'usuarios', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:38:44'),
(5, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:40:38'),
(6, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:43:14'),
(7, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:45:27'),
(8, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:48:16'),
(9, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:49:46'),
(10, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:50:48'),
(11, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:51:40'),
(12, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:53:37'),
(13, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:54:32'),
(14, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:55:52'),
(15, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:56:48'),
(16, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:57:39'),
(17, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:58:44'),
(18, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 08:00:26'),
(19, 1, 2, 'funcionario_criado', 'funcionarios', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 08:01:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `candidaturas`
--

CREATE TABLE `candidaturas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `vaga_id` bigint(20) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `carta` text DEFAULT NULL,
  `status` enum('inscrita','triagem','entrevista','aprovada','reprovada') NOT NULL DEFAULT 'inscrita',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cercas_virtuais`
--

CREATE TABLE `cercas_virtuais` (
  `id` int(10) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `raio_metros` int(10) UNSIGNED NOT NULL DEFAULT 150,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `comunicados`
--

CREATE TABLE `comunicados` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `autor_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `corpo` text NOT NULL,
  `alcance` enum('empresa','departamento','equipe') NOT NULL DEFAULT 'empresa',
  `departamento_id` int(10) UNSIGNED DEFAULT NULL,
  `gestor_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'quando alcance = equipe',
  `fixado` tinyint(1) NOT NULL DEFAULT 0,
  `publicado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `comunicado_leituras`
--

CREATE TABLE `comunicado_leituras` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `comunicado_id` bigint(20) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `lido_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int(10) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `disponibilidade`
--

CREATE TABLE `disponibilidade` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `data` date NOT NULL,
  `periodo` enum('manha','tarde','noite','integral') NOT NULL DEFAULT 'integral',
  `disponivel` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('pendente','aprovada','recusada') NOT NULL DEFAULT 'pendente',
  `observacao` varchar(255) DEFAULT NULL,
  `decidido_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `decidido_em` datetime DEFAULT NULL,
  `motivo_decisao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `empresas`
--

CREATE TABLE `empresas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `razao_social` varchar(160) DEFAULT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `segmento` varchar(80) DEFAULT NULL,
  `plano_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('ativa','suspensa','cancelada') NOT NULL DEFAULT 'ativa',
  `fuso_horario` varchar(40) NOT NULL DEFAULT 'America/Sao_Paulo',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `empresas`
--

INSERT INTO `empresas` (`id`, `nome`, `razao_social`, `cnpj`, `segmento`, `plano_id`, `status`, `fuso_horario`, `criado_em`, `atualizado_em`) VALUES
(1, 'Escola Carolina', NULL, NULL, NULL, 1, 'ativa', 'America/Sao_Paulo', '2026-08-11 07:36:49', '2026-08-11 07:36:49');

-- --------------------------------------------------------

--
-- Estrutura para tabela `empresa_config`
--

CREATE TABLE `empresa_config` (
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `exigir_cerca` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'bloqueia batida fora da cerca',
  `precisao_maxima_metros` int(10) UNSIGNED NOT NULL DEFAULT 100 COMMENT 'acima disso a batida vai para revisão',
  `jornada_diaria_minutos` smallint(5) UNSIGNED NOT NULL DEFAULT 480,
  `tolerancia_atraso_minutos` smallint(5) UNSIGNED NOT NULL DEFAULT 10,
  `exigir_gps` tinyint(1) NOT NULL DEFAULT 1,
  `cerca_padrao_id` int(10) UNSIGNED DEFAULT NULL,
  `ia_provedor` enum('nenhum','openai','gemini') NOT NULL DEFAULT 'nenhum',
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `empresa_config`
--

INSERT INTO `empresa_config` (`empresa_id`, `exigir_cerca`, `precisao_maxima_metros`, `jornada_diaria_minutos`, `tolerancia_atraso_minutos`, `exigir_gps`, `ia_provedor`, `atualizado_em`) VALUES
(1, 1, 100, 480, 10, 1, 'nenhum', '2026-08-11 07:36:49');

-- --------------------------------------------------------

--
-- Estrutura para tabela `feriados`
--

CREATE TABLE `feriados` (
  `id` int(10) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `data` date NOT NULL,
  `nome` varchar(120) NOT NULL,
  `tipo` enum('nacional','estadual','municipal','empresa') NOT NULL DEFAULT 'empresa',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `funcionarios`
--

CREATE TABLE `funcionarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'conta de acesso; NULL = sem login',
  `matricula` varchar(20) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cargo` varchar(80) DEFAULT NULL,
  `departamento_id` int(10) UNSIGNED DEFAULT NULL,
  `gestor_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'auto-referência: gerente responsável',
  `data_admissao` date DEFAULT NULL,
  `data_desligamento` date DEFAULT NULL,
  `jornada_diaria_minutos` smallint(5) UNSIGNED NOT NULL DEFAULT 480,
  `salario` decimal(10,2) DEFAULT NULL,
  `status` enum('ativo','afastado','desligado') NOT NULL DEFAULT 'ativo',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `funcionarios`
--

INSERT INTO `funcionarios` (`id`, `empresa_id`, `usuario_id`, `matricula`, `nome`, `cpf`, `email`, `telefone`, `cargo`, `departamento_id`, `gestor_id`, `data_admissao`, `data_desligamento`, `jornada_diaria_minutos`, `salario`, `status`, `criado_em`) VALUES
(1, 1, 3, '0001', 'Eloah', '56476987012', 'eloah@gmail.com', '34984011230', 'Rh', NULL, NULL, '2009-04-20', NULL, 480, NULL, 'ativo', '2026-08-11 07:40:38'),
(2, 1, 4, '0002', 'Hevellyn', '45321234598', 'hevellyn@gmail.com', '34521123908', 'Marketing', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:43:14'),
(3, 1, 5, '0003', 'Nayni', '34123987654', 'nayni@gmail.com', '34521345321', 'Marketing', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:45:27'),
(4, 1, 6, '0004', 'Carol', '57438057231', 'carolynne@gmail.com', '34981647528', 'Marketing', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:48:16'),
(5, 1, 7, '0005', 'Isabelly', '34985174869', 'isabelly@gmail.com', '34685497859', 'Marketing', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:49:46'),
(6, 1, 8, '0006', 'Kettelyn', '13597842659', 'kettelyn@gmail.com', '34158426989', 'Projetos', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:50:48'),
(7, 1, 9, '0007', 'Marina', '23598634571', 'marina@gmail.com', '34157482569', 'Marketing', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:51:40'),
(8, 1, 10, '0008', 'Duda', '48567231548', 'maria@gmail.com', '34598624875', 'Marketing', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:53:37'),
(9, 1, 11, '0009', 'Lorena', '15489675832', 'lorena@gmail.com', '34158962458', 'Desenvolvimento', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:54:32'),
(10, 1, 12, '0010', 'Yasmin', '31257964251', 'yasmin@gmail.com', '34152687495', 'Desenvolvimento', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:55:52'),
(11, 1, 13, '0011', 'Nikolly', '17181493604', 'nikolly@gmail.com', '3499028745', 'Financeiro', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:56:48'),
(12, 1, 14, '0012', 'Camilly', '31548562644', 'camilly@gmail.com', '34154784459', 'Projetos', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:57:39'),
(13, 1, 15, '0013', 'Rayssa', '31245875933', 'rayssa@gmail.com', '34984751289', 'Projetos', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 07:58:44'),
(14, 1, 16, '0014', 'Luismar', '12456323456', 'luismar@gmail.com', '34219078654', 'Financeiro', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 08:00:26'),
(15, 1, 17, '0015', 'Daniella', '32167854678', 'daniella@gmail.com', '34671094589', 'Projetos', NULL, NULL, '2026-08-11', NULL, 480, NULL, 'ativo', '2026-08-11 08:01:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `ia_conversas`
--

CREATE TABLE `ia_conversas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(160) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ia_mensagens`
--

CREATE TABLE `ia_mensagens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `conversa_id` bigint(20) UNSIGNED NOT NULL,
  `papel` enum('sistema','usuario','assistente') NOT NULL,
  `conteudo` mediumtext NOT NULL,
  `provedor` varchar(30) DEFAULT NULL COMMENT 'openai, gemini ou local',
  `tokens` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `localizacoes`
--

CREATE TABLE `localizacoes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `origem` enum('ponto','login','visita','manual') NOT NULL DEFAULT 'ponto',
  `ponto_id` bigint(20) UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `precisao_gps` decimal(8,2) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `login_tentativas`
--

CREATE TABLE `login_tentativas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `identificador` varchar(160) NOT NULL COMMENT 'usuário ou e-mail digitado',
  `ip` varchar(45) NOT NULL,
  `sucesso` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `login_tentativas`
--

INSERT INTO `login_tentativas` (`id`, `identificador`, `ip`, `sucesso`, `criado_em`) VALUES
(1, 'guto', '::1', 0, '2026-08-11 07:26:27'),
(2, 'guto', '::1', 0, '2026-08-11 07:26:33'),
(3, 'tony', '::1', 0, '2026-08-11 07:30:50'),
(4, 'gustavo', '::1', 1, '2026-08-11 07:37:34'),
(5, 'thaisson', '::1', 1, '2026-08-11 07:38:44'),
(6, 'thaisson', '::1', 1, '2026-08-11 07:38:44');

-- --------------------------------------------------------

--
-- Estrutura para tabela `mensagens`
--

CREATE TABLE `mensagens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `remetente_id` int(10) UNSIGNED NOT NULL,
  `destinatario_id` int(10) UNSIGNED NOT NULL,
  `corpo` text NOT NULL,
  `lida_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `corpo` varchar(500) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `lida_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ouvidoria`
--

CREATE TABLE `ouvidoria` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `protocolo_hash` char(64) NOT NULL COMMENT 'sha256 do código entregue ao denunciante',
  `anonimo` tinyint(1) NOT NULL DEFAULT 0,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'sempre NULL quando anonimo = 1',
  `categoria` enum('assedio','seguranca','etica','financeiro','discriminacao','outro') NOT NULL DEFAULT 'outro',
  `assunto` varchar(160) NOT NULL,
  `descricao` text NOT NULL,
  `prioridade` enum('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
  `status` enum('aberta','em_analise','respondida','encerrada') NOT NULL DEFAULT 'aberta',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ouvidoria_respostas`
--

CREATE TABLE `ouvidoria_respostas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `ouvidoria_id` bigint(20) UNSIGNED NOT NULL,
  `autor_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = resposta do próprio denunciante anônimo',
  `corpo` text NOT NULL,
  `visivel_denunciante` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = nota interna da apuração',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos`
--

CREATE TABLE `planos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(60) NOT NULL,
  `limite_funcionarios` int(10) UNSIGNED NOT NULL DEFAULT 25,
  `preco_mensal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `recursos` text DEFAULT NULL COMMENT 'JSON com flags de recursos liberados',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `planos`
--

INSERT INTO `planos` (`id`, `nome`, `limite_funcionarios`, `preco_mensal`, `recursos`, `ativo`, `criado_em`) VALUES
(1, 'Essencial', 25, 0.00, '{\"ponto\":true,\"ouvidoria\":true,\"chat\":false,\"vagas\":false,\"ia\":false}', 1, '2026-08-11 07:26:15'),
(2, 'Profissional', 150, 249.00, '{\"ponto\":true,\"ouvidoria\":true,\"chat\":true,\"vagas\":true,\"ia\":false}', 1, '2026-08-11 07:26:15'),
(3, 'Corporativo', 1000, 799.00, '{\"ponto\":true,\"ouvidoria\":true,\"chat\":true,\"vagas\":true,\"ia\":true}', 1, '2026-08-11 07:26:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pontos`
--

CREATE TABLE `pontos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('entrada','pausa','retorno','saida') NOT NULL,
  `data` date NOT NULL,
  `data_hora` datetime NOT NULL COMMENT 'carimbado pelo servidor, nunca pelo cliente',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `precisao_gps` decimal(8,2) DEFAULT NULL COMMENT 'metros informados pelo navegador',
  `endereco` varchar(255) DEFAULT NULL,
  `cerca_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'cerca mais próxima avaliada',
  `dentro_cerca` tinyint(1) DEFAULT NULL COMMENT 'calculado no servidor',
  `distancia_metros` decimal(10,2) DEFAULT NULL COMMENT 'distância até a cerca avaliada',
  `precisao_suficiente` tinyint(1) NOT NULL DEFAULT 1,
  `origem` enum('web','totem','app') NOT NULL DEFAULT 'web',
  `ip` varchar(45) DEFAULT NULL,
  `dispositivo` varchar(255) DEFAULT NULL,
  `cliente_token` char(36) DEFAULT NULL COMMENT 'idempotência: reenvio da mesma batida não duplica',
  `justificativa` varchar(255) DEFAULT NULL,
  `status` enum('valido','pendente_revisao','ajustado','rejeitado') NOT NULL DEFAULT 'valido',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `relatorios`
--

CREATE TABLE `relatorios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `tipo` varchar(60) NOT NULL COMMENT 'ponto_mensal, atrasos, banco_horas, ouvidoria...',
  `titulo` varchar(160) NOT NULL,
  `parametros` text DEFAULT NULL COMMENT 'JSON com filtros usados',
  `periodo_ini` date DEFAULT NULL,
  `periodo_fim` date DEFAULT NULL,
  `gerado_por` int(10) UNSIGNED DEFAULT NULL,
  `arquivo_id` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sugestoes`
--

CREATE TABLE `sugestoes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `anonima` tinyint(1) NOT NULL DEFAULT 0,
  `titulo` varchar(160) NOT NULL,
  `descricao` text NOT NULL,
  `area` varchar(80) DEFAULT NULL,
  `status` enum('recebida','em_analise','aprovada','implementada','recusada') NOT NULL DEFAULT 'recebida',
  `retorno` text DEFAULT NULL COMMENT 'devolutiva da empresa',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL somente para o nível master',
  `nome` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `usuario` varchar(60) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `nivel` enum('master','admin','gerente','funcionario') NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `trocar_senha` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_login_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `empresa_id`, `nome`, `email`, `usuario`, `senha_hash`, `nivel`, `ativo`, `trocar_senha`, `ultimo_login_em`, `criado_em`) VALUES
(1, NULL, 'Guto', 'gustavo@gmail.com', 'gustavo', '$2y$10$vZu2hnstWrQbTDs9Jn6F3.zoKQnlljjap0eeF0urNN2IqMtHNxWBm', 'master', 1, 0, '2026-08-11 07:37:34', '2026-08-11 07:36:49'),
(2, 1, 'Thaisson', 'thaisson@gmail.com', 'thaisson', '$2y$10$dHju30s03lJu6uGMhjoVYe8wmH2ConxUpovUPDqsPh/WPQ2ZUXNdK', 'admin', 1, 0, '2026-08-11 07:38:44', '2026-08-11 07:36:49'),
(3, 1, 'Eloah', 'eloah@gmail.com', 'eloah', '$2y$10$H6Q4IiEPItZFpjwFiHjgPeOW7XNxC1xxuZe1y3WMF5gBkzJZ1pNe.', 'funcionario', 1, 0, NULL, '2026-08-11 07:40:38'),
(4, 1, 'Hevellyn', 'hevellyn@gmail.com', 'hevellyn', '$2y$10$pSnE2F5Zt1HnQuTnsb2ZYuCr5QPzaXHMZ5HWkEDymcaggL1OxnJtK', 'funcionario', 1, 0, NULL, '2026-08-11 07:43:14'),
(5, 1, 'Nayni', 'nayni@gmail.com', 'nayni', '$2y$10$oGITdhHvo3ZSyrkyUtUUtuxjKEZDYkG39/2e5YtT7R8FGYRXrEl/K', 'funcionario', 1, 0, NULL, '2026-08-11 07:45:27'),
(6, 1, 'Carol', 'carolynne@gmail.com', 'carol', '$2y$10$b4aX8mwd0M0nLaDiKoxr2uBSauauogjHVZ.6tBo2nt4q1K6coafbG', 'funcionario', 1, 0, NULL, '2026-08-11 07:48:16'),
(7, 1, 'Isabelly', 'isabelly@gmail.com', 'isabelly', '$2y$10$NFjvQnbeoV9AV4E8fBFYOusa87vMxec67qe/l8wjZDToAazucImgm', 'funcionario', 1, 0, NULL, '2026-08-11 07:49:46'),
(8, 1, 'Kettelyn', 'kettelyn@gmail.com', 'kettelyn', '$2y$10$t5ZmrBzH/ZjWNNUQnwdWVevl5BidkL5/aGdGuCaTfi5CnoO5gwFbq', 'funcionario', 1, 0, NULL, '2026-08-11 07:50:48'),
(9, 1, 'Marina', 'marina@gmail.com', 'marina', '$2y$10$g.D0N22CwB/9J1VXJV1QUeuBMI0EP1lmzAlJmim.KPQoLVL9dd4sK', 'funcionario', 1, 0, NULL, '2026-08-11 07:51:40'),
(10, 1, 'Duda', 'maria@gmail.com', 'duda', '$2y$10$RWjr162wBdlOE7jhyKRtAuX3HfUa/mEpfEWjwnvRfAkylxiOF.Q06', 'funcionario', 1, 0, NULL, '2026-08-11 07:53:37'),
(11, 1, 'Lorena', 'lorena@gmail.com', 'lorena', '$2y$10$H.FlHuXvbpKuT3AiM5YOxudxdrx7CHM/nQKG2NSHYP0nIKwlp7t2C', 'funcionario', 1, 0, NULL, '2026-08-11 07:54:32'),
(12, 1, 'Yasmin', 'yasmin@gmail.com', 'yasmin', '$2y$10$YdGLwp0o8/iZK/.Mvrao/.7NT5cjent4NEMUh4Hjs4FD3ZNvOjxo6', 'funcionario', 1, 0, NULL, '2026-08-11 07:55:52'),
(13, 1, 'Nikolly', 'nikolly@gmail.com', 'nikolly', '$2y$10$.KT1JfZJy.mYV8ULDHGsn.E9QvfwNiKmraHVEnW24Xp8oso.ZA4ty', 'funcionario', 1, 0, NULL, '2026-08-11 07:56:48'),
(14, 1, 'Camilly', 'camilly@gmail.com', 'camilly', '$2y$10$b2y.WvAHWff3n2vuBWo.9.eVXTRSaqk7sXnTdJ3REYR2KAmjhEYw6', 'funcionario', 1, 0, NULL, '2026-08-11 07:57:39'),
(15, 1, 'Rayssa', 'rayssa@gmail.com', 'rayssa', '$2y$10$.oyd.FQ.KyX/yo/QG5XlNu9L.Qn6lFoJ7m.hcTbbdZXTAPQq.jKTu', 'funcionario', 1, 0, NULL, '2026-08-11 07:58:44'),
(16, 1, 'Luismar', 'luismar@gmail.com', 'luismar', '$2y$10$vv8AJJsd21fIoTciV9.x0umSBqFZime8E4VU.XEfODgEY.jNXcpD6', 'funcionario', 1, 0, NULL, '2026-08-11 08:00:26'),
(17, 1, 'Daniella', 'daniella@gmail.com', 'daniella', '$2y$10$UeSo/RvTBJGyzv.gsp0QYOqcNgvmxqgUdT/VBvAX2RWVqgOxNyl2a', 'funcionario', 1, 0, NULL, '2026-08-11 08:01:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vagas`
--

CREATE TABLE `vagas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `descricao` text NOT NULL,
  `requisitos` text DEFAULT NULL,
  `departamento_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('efetivo','temporario','estagio','aprendiz') NOT NULL DEFAULT 'efetivo',
  `modalidade` enum('presencial','hibrido','remoto') NOT NULL DEFAULT 'presencial',
  `salario` decimal(10,2) DEFAULT NULL,
  `vagas_abertas` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `status` enum('rascunho','aberta','encerrada') NOT NULL DEFAULT 'rascunho',
  `publicada_em` datetime DEFAULT NULL,
  `encerra_em` date DEFAULT NULL,
  `criado_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `arquivos`
--
ALTER TABLE `arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_arq_empresa_data` (`empresa_id`,`criado_em`),
  ADD KEY `idx_arq_func` (`funcionario_id`),
  ADD KEY `fk_arq_autor` (`enviado_por`);

--
-- Índices de tabela `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auditoria_empresa_data` (`empresa_id`,`criado_em`),
  ADD KEY `idx_auditoria_usuario` (`usuario_id`,`criado_em`);

--
-- Índices de tabela `candidaturas`
--
ALTER TABLE `candidaturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cand_vaga_func` (`vaga_id`,`funcionario_id`),
  ADD KEY `idx_cand_empresa_status` (`empresa_id`,`status`),
  ADD KEY `idx_cand_func` (`funcionario_id`);

--
-- Índices de tabela `cercas_virtuais`
--
ALTER TABLE `cercas_virtuais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cerca_empresa_nome` (`empresa_id`,`nome`),
  ADD KEY `idx_cerca_empresa_ativa` (`empresa_id`,`ativa`);

--
-- Índices de tabela `comunicados`
--
ALTER TABLE `comunicados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_com_empresa_data` (`empresa_id`,`publicado_em`),
  ADD KEY `fk_com_autor` (`autor_id`),
  ADD KEY `fk_com_depto` (`departamento_id`),
  ADD KEY `fk_com_gestor` (`gestor_id`);

--
-- Índices de tabela `comunicado_leituras`
--
ALTER TABLE `comunicado_leituras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_leitura` (`comunicado_id`,`funcionario_id`),
  ADD KEY `idx_leitura_empresa` (`empresa_id`),
  ADD KEY `fk_leitura_func` (`funcionario_id`);

--
-- Índices de tabela `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_depto_empresa_nome` (`empresa_id`,`nome`);

--
-- Índices de tabela `disponibilidade`
--
ALTER TABLE `disponibilidade`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_disp_func_data_periodo` (`funcionario_id`,`data`,`periodo`),
  ADD KEY `idx_disp_empresa_data` (`empresa_id`,`data`),
  ADD KEY `idx_disp_empresa_status_data` (`empresa_id`,`status`,`data`),
  ADD KEY `idx_disp_decidido_por` (`decidido_por_usuario_id`);

--
-- Índices de tabela `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_empresas_cnpj` (`cnpj`),
  ADD KEY `idx_empresas_status` (`status`),
  ADD KEY `fk_empresas_plano` (`plano_id`);

--
-- Índices de tabela `empresa_config`
--
ALTER TABLE `empresa_config`
  ADD PRIMARY KEY (`empresa_id`),
  ADD KEY `idx_config_cerca_padrao` (`cerca_padrao_id`);

--
-- Índices de tabela `feriados`
--
ALTER TABLE `feriados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_feriado_empresa_data` (`empresa_id`,`data`),
  ADD KEY `idx_feriado_empresa_data` (`empresa_id`,`data`);

--
-- Índices de tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_func_empresa_matricula` (`empresa_id`,`matricula`),
  ADD UNIQUE KEY `uq_func_usuario` (`usuario_id`),
  ADD UNIQUE KEY `uq_func_empresa_cpf` (`empresa_id`,`cpf`),
  ADD KEY `idx_func_empresa_status` (`empresa_id`,`status`),
  ADD KEY `idx_func_gestor` (`gestor_id`),
  ADD KEY `idx_func_depto` (`departamento_id`);

--
-- Índices de tabela `ia_conversas`
--
ALTER TABLE `ia_conversas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_iaconv_empresa_usuario` (`empresa_id`,`usuario_id`,`criado_em`),
  ADD KEY `fk_iaconv_usuario` (`usuario_id`);

--
-- Índices de tabela `ia_mensagens`
--
ALTER TABLE `ia_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_iamsg_conversa` (`conversa_id`,`criado_em`),
  ADD KEY `idx_iamsg_empresa` (`empresa_id`);

--
-- Índices de tabela `localizacoes`
--
ALTER TABLE `localizacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loc_empresa_data` (`empresa_id`,`criado_em`),
  ADD KEY `idx_loc_func` (`funcionario_id`,`criado_em`),
  ADD KEY `idx_loc_ponto` (`ponto_id`);

--
-- Índices de tabela `login_tentativas`
--
ALTER TABLE `login_tentativas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tentativas_ident` (`identificador`,`criado_em`),
  ADD KEY `idx_tentativas_ip` (`ip`,`criado_em`);

--
-- Índices de tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_empresa_data` (`empresa_id`,`criado_em`),
  ADD KEY `idx_msg_conversa` (`empresa_id`,`remetente_id`,`destinatario_id`,`criado_em`),
  ADD KEY `idx_msg_caixa` (`destinatario_id`,`lida_em`),
  ADD KEY `fk_msg_de` (`remetente_id`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_usuario` (`usuario_id`,`lida_em`,`criado_em`),
  ADD KEY `idx_notif_empresa` (`empresa_id`,`criado_em`);

--
-- Índices de tabela `ouvidoria`
--
ALTER TABLE `ouvidoria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ouv_protocolo` (`protocolo_hash`),
  ADD KEY `idx_ouv_empresa_status` (`empresa_id`,`status`,`criado_em`),
  ADD KEY `fk_ouv_func` (`funcionario_id`);

--
-- Índices de tabela `ouvidoria_respostas`
--
ALTER TABLE `ouvidoria_respostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ouvresp_chamado` (`ouvidoria_id`,`criado_em`),
  ADD KEY `idx_ouvresp_empresa` (`empresa_id`),
  ADD KEY `fk_ouvresp_autor` (`autor_id`);

--
-- Índices de tabela `planos`
--
ALTER TABLE `planos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_planos_nome` (`nome`);

--
-- Índices de tabela `pontos`
--
ALTER TABLE `pontos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ponto_token` (`empresa_id`,`cliente_token`),
  ADD KEY `idx_ponto_empresa_datahora` (`empresa_id`,`data_hora`),
  ADD KEY `idx_ponto_func_data` (`funcionario_id`,`data`),
  ADD KEY `idx_ponto_empresa_status` (`empresa_id`,`status`),
  ADD KEY `fk_ponto_cerca` (`cerca_id`);

--
-- Índices de tabela `relatorios`
--
ALTER TABLE `relatorios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rel_empresa_tipo` (`empresa_id`,`tipo`,`criado_em`),
  ADD KEY `fk_rel_autor` (`gerado_por`),
  ADD KEY `fk_rel_arquivo` (`arquivo_id`);

--
-- Índices de tabela `sugestoes`
--
ALTER TABLE `sugestoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sug_empresa_status` (`empresa_id`,`status`,`criado_em`),
  ADD KEY `fk_sug_func` (`funcionario_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`),
  ADD UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
  ADD KEY `idx_usuarios_empresa_nivel` (`empresa_id`,`nivel`);

--
-- Índices de tabela `vagas`
--
ALTER TABLE `vagas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vaga_empresa_status` (`empresa_id`,`status`,`publicada_em`),
  ADD KEY `fk_vaga_depto` (`departamento_id`),
  ADD KEY `fk_vaga_autor` (`criado_por`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `arquivos`
--
ALTER TABLE `arquivos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `candidaturas`
--
ALTER TABLE `candidaturas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cercas_virtuais`
--
ALTER TABLE `cercas_virtuais`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `comunicados`
--
ALTER TABLE `comunicados`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `comunicado_leituras`
--
ALTER TABLE `comunicado_leituras`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `disponibilidade`
--
ALTER TABLE `disponibilidade`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `feriados`
--
ALTER TABLE `feriados`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `ia_conversas`
--
ALTER TABLE `ia_conversas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ia_mensagens`
--
ALTER TABLE `ia_mensagens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `localizacoes`
--
ALTER TABLE `localizacoes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `login_tentativas`
--
ALTER TABLE `login_tentativas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `mensagens`
--
ALTER TABLE `mensagens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ouvidoria`
--
ALTER TABLE `ouvidoria`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ouvidoria_respostas`
--
ALTER TABLE `ouvidoria_respostas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `planos`
--
ALTER TABLE `planos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `pontos`
--
ALTER TABLE `pontos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `relatorios`
--
ALTER TABLE `relatorios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `sugestoes`
--
ALTER TABLE `sugestoes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `vagas`
--
ALTER TABLE `vagas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `arquivos`
--
ALTER TABLE `arquivos`
  ADD CONSTRAINT `fk_arq_autor` FOREIGN KEY (`enviado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_arq_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_arq_func` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `candidaturas`
--
ALTER TABLE `candidaturas`
  ADD CONSTRAINT `fk_cand_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cand_func` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cand_vaga` FOREIGN KEY (`vaga_id`) REFERENCES `vagas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cercas_virtuais`
--
ALTER TABLE `cercas_virtuais`
  ADD CONSTRAINT `fk_cerca_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `comunicados`
--
ALTER TABLE `comunicados`
  ADD CONSTRAINT `fk_com_autor` FOREIGN KEY (`autor_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_com_depto` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_com_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_com_gestor` FOREIGN KEY (`gestor_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `comunicado_leituras`
--
ALTER TABLE `comunicado_leituras`
  ADD CONSTRAINT `fk_leitura_com` FOREIGN KEY (`comunicado_id`) REFERENCES `comunicados` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_leitura_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_leitura_func` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `departamentos`
--
ALTER TABLE `departamentos`
  ADD CONSTRAINT `fk_depto_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `disponibilidade`
--
ALTER TABLE `disponibilidade`
  ADD CONSTRAINT `fk_disp_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_disp_func` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_disp_decidido_por` FOREIGN KEY (`decidido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `empresas`
--
ALTER TABLE `empresas`
  ADD CONSTRAINT `fk_empresas_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `empresa_config`
--
ALTER TABLE `empresa_config`
  ADD CONSTRAINT `fk_config_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_config_cerca_padrao` FOREIGN KEY (`cerca_padrao_id`) REFERENCES `cercas_virtuais` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `feriados`
--
ALTER TABLE `feriados`
  ADD CONSTRAINT `fk_feriado_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `funcionarios`
--
ALTER TABLE `funcionarios`
  ADD CONSTRAINT `fk_func_depto` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_func_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_func_gestor` FOREIGN KEY (`gestor_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_func_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `ia_conversas`
--
ALTER TABLE `ia_conversas`
  ADD CONSTRAINT `fk_iaconv_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_iaconv_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ia_mensagens`
--
ALTER TABLE `ia_mensagens`
  ADD CONSTRAINT `fk_iamsg_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `ia_conversas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_iamsg_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `localizacoes`
--
ALTER TABLE `localizacoes`
  ADD CONSTRAINT `fk_loc_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loc_func` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loc_ponto` FOREIGN KEY (`ponto_id`) REFERENCES `pontos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mensagens`
--
ALTER TABLE `mensagens`
  ADD CONSTRAINT `fk_msg_de` FOREIGN KEY (`remetente_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_para` FOREIGN KEY (`destinatario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `fk_notif_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ouvidoria`
--
ALTER TABLE `ouvidoria`
  ADD CONSTRAINT `fk_ouv_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ouv_func` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `ouvidoria_respostas`
--
ALTER TABLE `ouvidoria_respostas`
  ADD CONSTRAINT `fk_ouvresp_autor` FOREIGN KEY (`autor_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ouvresp_chamado` FOREIGN KEY (`ouvidoria_id`) REFERENCES `ouvidoria` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ouvresp_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pontos`
--
ALTER TABLE `pontos`
  ADD CONSTRAINT `fk_ponto_cerca` FOREIGN KEY (`cerca_id`) REFERENCES `cercas_virtuais` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ponto_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ponto_func` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `relatorios`
--
ALTER TABLE `relatorios`
  ADD CONSTRAINT `fk_rel_arquivo` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rel_autor` FOREIGN KEY (`gerado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rel_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `sugestoes`
--
ALTER TABLE `sugestoes`
  ADD CONSTRAINT `fk_sug_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sug_func` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `vagas`
--
ALTER TABLE `vagas`
  ADD CONSTRAINT `fk_vaga_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vaga_depto` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vaga_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
