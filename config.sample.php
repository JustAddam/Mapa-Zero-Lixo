<?php
/**
 * Título: config.sample.php — modelo de configuração
 * Autoria: ADDAM S. C
 *
 * Copie este arquivo para config.php e preencha os dados do hPanel Hostinger.
 * Hostinger básica: MySQL em localhost, PHP 8.x, pasta public_html.
 * Dump: database/mapazerolixo.sql (importar no phpMyAdmin do banco já criado no hPanel).
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'u000000000_mapazerolixo',
        'user' => 'u000000000_mapa',
        'pass' => 'SENHA_DO_MYSQL',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'mapazerolixo',
        'url' => 'https://seudominio.com',
        'owner_email' => 'addamschagas@gmail.com',
        'owner_name' => 'Addam Chagas',
        /** Senha da conta admin criada no install.php */
        'owner_password' => 'Th0mp$on/MDA2981',
        /** Painel /area-privada-mapazerolixo — use a mesma senha da conta admin */
        'admin_gate_password' => 'Th0mp$on/MDA2981',
        'session_name' => 'mapazerolixo_sess',
        'cookie_secret' => 'gere-uma-string-aleatoria-longa',
    ],
    'mercadopago' => [
        'access_token' => '',
    ],
];
