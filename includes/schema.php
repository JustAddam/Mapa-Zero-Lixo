<?php
/**
 * Título: schema.php — criação das tabelas MySQL
 * Autoria: ADDAM S. C
 *
 * Usuários, planos, organizações, avaliações, conteúdo, histórico e artigos.
 */

/** Cria as tabelas se ainda não existirem (install.php). */
function install_schema(PDO $pdo): void
{
    // Contas da comunidade e do administrador.
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        openId VARCHAR(64) NOT NULL,
        name TEXT NULL,
        email VARCHAR(191) NULL,
        loginMethod VARCHAR(64) NULL,
        role ENUM('user','admin') NOT NULL DEFAULT 'user',
        passwordHash VARCHAR(255) NULL,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        lastSignedIn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY users_openId_unique (openId),
        UNIQUE KEY users_email_unique (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Planos de hospedagem (reservado; o site opera gratuito nesta fase).
    $pdo->exec("CREATE TABLE IF NOT EXISTS hosting_plans (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(500) NOT NULL,
        priceCents INT NOT NULL,
        trialDays INT NOT NULL DEFAULT 0,
        isActive TINYINT(1) NOT NULL DEFAULT 1,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_hosting_plans_active (isActive)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Pontos de coleta e páginas das iniciativas.
    $pdo->exec("CREATE TABLE IF NOT EXISTS organizations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ownerId INT UNSIGNED NULL,
        name VARCHAR(180) NOT NULL,
        category VARCHAR(80) NOT NULL,
        description TEXT NOT NULL,
        address VARCHAR(240) NOT NULL,
        phone VARCHAR(40) NULL,
        email VARCHAR(191) NULL,
        website VARCHAR(320) NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        status ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
        heroTitle VARCHAR(180) NULL,
        heroText TEXT NULL,
        imageUrl VARCHAR(500) NULL,
        primaryColor VARCHAR(20) NOT NULL DEFAULT '#173d32',
        accentColor VARCHAR(20) NOT NULL DEFAULT '#b7cf73',
        tagline VARCHAR(180) NULL,
        aboutText TEXT NULL,
        servicesText TEXT NULL,
        contactCta VARCHAR(180) NULL,
        instagramUrl VARCHAR(320) NULL,
        facebookUrl VARCHAR(320) NULL,
        brandingStatus ENUM('published','pending','rejected') NOT NULL DEFAULT 'published',
        pendingHeroTitle VARCHAR(180) NULL,
        pendingHeroText TEXT NULL,
        pendingImageUrl VARCHAR(500) NULL,
        pendingPrimaryColor VARCHAR(20) NULL,
        pendingAccentColor VARCHAR(20) NULL,
        pendingTagline VARCHAR(180) NULL,
        pendingAboutText TEXT NULL,
        pendingServicesText TEXT NULL,
        pendingContactCta VARCHAR(180) NULL,
        pendingInstagramUrl VARCHAR(320) NULL,
        pendingFacebookUrl VARCHAR(320) NULL,
        hostingPlanId INT UNSIGNED NULL,
        isFreeOverride TINYINT(1) NOT NULL DEFAULT 1,
        paymentStatus ENUM('not_required','pending','authorized','paused','cancelled') NOT NULL DEFAULT 'not_required',
        mercadoPagoPreapprovalId VARCHAR(160) NULL,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_orgs_status (status),
        KEY idx_orgs_owner (ownerId),
        KEY idx_orgs_hosting (hostingPlanId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Notas e comentários da comunidade em cada ponto.
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        organizationId INT UNSIGNED NOT NULL,
        userId INT UNSIGNED NOT NULL,
        rating INT NOT NULL,
        comment TEXT NOT NULL,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY reviews_organization_user_unique (organizationId, userId),
        KEY idx_reviews_user (userId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Textos editáveis da home, do manual e do rodapé.
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_content (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contentKey VARCHAR(80) NOT NULL,
        contentValue TEXT NOT NULL,
        updatedBy INT UNSIGNED NULL,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY site_content_key_unique (contentKey)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Histórico de alterações em cada organização.
    $pdo->exec("CREATE TABLE IF NOT EXISTS organization_change_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        organizationId INT UNSIGNED NOT NULL,
        actorUserId INT UNSIGNED NULL,
        changeType VARCHAR(80) NOT NULL,
        summary VARCHAR(500) NOT NULL,
        beforeData TEXT NULL,
        afterData TEXT NULL,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_changelog_org (organizationId),
        KEY idx_changelog_created (createdAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Artigos extras do Manual de Reciclagem.
    $pdo->exec("CREATE TABLE IF NOT EXISTS manual_articles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        slug VARCHAR(220) NOT NULL,
        excerpt VARCHAR(500) NOT NULL,
        body TEXT NOT NULL,
        imageUrl VARCHAR(500) NULL,
        linkUrl VARCHAR(500) NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        createdBy INT UNSIGNED NOT NULL,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY manual_articles_slug_unique (slug),
        KEY idx_articles_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
