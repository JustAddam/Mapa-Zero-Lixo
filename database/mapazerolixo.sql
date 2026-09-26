-- mapazerolixo — MySQL/MariaDB para Hostinger (plano básico / compartilhado)
--
-- Como importar no hPanel:
-- 1. Bancos de dados MySQL → criar 1 banco e 1 usuário (o plano básico costuma ter 1 banco).
--    Nomes ficam no formato u000000000_mapazerolixo e u000000000_mapa.
-- 2. NÃO rode CREATE DATABASE aqui: o banco já existe no hPanel.
-- 3. phpMyAdmin → selecione esse banco → Importar este arquivo
--    (ou SQL → colar). Charset: utf8mb4.
-- 4. Preencha public_html/config.php com host=localhost, nome, usuário e senha.
-- 5. Abra /install.php uma vez para criar o admin e completar o conteúdo.
--
-- Compatível com MySQL 8.0 e MariaDB 10.4+ (Hostinger compartilhada).
-- Sem CREATE DATABASE, sem DEFINER, sem VIEW, sem TRIGGER, sem EVENT.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `openId` VARCHAR(64) NOT NULL,
  `name` TEXT NULL,
  `email` VARCHAR(191) NULL,
  `loginMethod` VARCHAR(64) NULL,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `passwordHash` VARCHAR(255) NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastSignedIn` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_openId_unique` (`openId`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hosting_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NOT NULL,
  `priceCents` INT NOT NULL,
  `trialDays` INT NOT NULL DEFAULT 0,
  `isActive` TINYINT(1) NOT NULL DEFAULT 1,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hosting_plans_active` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organizations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ownerId` INT UNSIGNED NULL,
  `name` VARCHAR(180) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `description` TEXT NOT NULL,
  `address` VARCHAR(240) NOT NULL,
  `phone` VARCHAR(40) NULL,
  `email` VARCHAR(191) NULL,
  `website` VARCHAR(320) NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `status` ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
  `heroTitle` VARCHAR(180) NULL,
  `heroText` TEXT NULL,
  `imageUrl` VARCHAR(500) NULL,
  `primaryColor` VARCHAR(20) NOT NULL DEFAULT '#173d32',
  `accentColor` VARCHAR(20) NOT NULL DEFAULT '#b7cf73',
  `tagline` VARCHAR(180) NULL,
  `aboutText` TEXT NULL,
  `servicesText` TEXT NULL,
  `contactCta` VARCHAR(180) NULL,
  `instagramUrl` VARCHAR(320) NULL,
  `facebookUrl` VARCHAR(320) NULL,
  `brandingStatus` ENUM('published','pending','rejected') NOT NULL DEFAULT 'published',
  `pendingHeroTitle` VARCHAR(180) NULL,
  `pendingHeroText` TEXT NULL,
  `pendingImageUrl` VARCHAR(500) NULL,
  `pendingPrimaryColor` VARCHAR(20) NULL,
  `pendingAccentColor` VARCHAR(20) NULL,
  `pendingTagline` VARCHAR(180) NULL,
  `pendingAboutText` TEXT NULL,
  `pendingServicesText` TEXT NULL,
  `pendingContactCta` VARCHAR(180) NULL,
  `pendingInstagramUrl` VARCHAR(320) NULL,
  `pendingFacebookUrl` VARCHAR(320) NULL,
  `hostingPlanId` INT UNSIGNED NULL,
  `isFreeOverride` TINYINT(1) NOT NULL DEFAULT 1,
  `paymentStatus` ENUM('not_required','pending','authorized','paused','cancelled') NOT NULL DEFAULT 'not_required',
  `mercadoPagoPreapprovalId` VARCHAR(160) NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orgs_status` (`status`),
  KEY `idx_orgs_owner` (`ownerId`),
  KEY `idx_orgs_hosting` (`hostingPlanId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizationId` INT UNSIGNED NOT NULL,
  `userId` INT UNSIGNED NOT NULL,
  `rating` INT NOT NULL,
  `comment` TEXT NOT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_organization_user_unique` (`organizationId`, `userId`),
  KEY `idx_reviews_user` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_content` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contentKey` VARCHAR(80) NOT NULL,
  `contentValue` TEXT NOT NULL,
  `updatedBy` INT UNSIGNED NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_content_key_unique` (`contentKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organization_change_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizationId` INT UNSIGNED NOT NULL,
  `actorUserId` INT UNSIGNED NULL,
  `changeType` VARCHAR(80) NOT NULL,
  `summary` VARCHAR(500) NOT NULL,
  `beforeData` TEXT NULL,
  `afterData` TEXT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_changelog_org` (`organizationId`),
  KEY `idx_changelog_created` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manual_articles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `excerpt` VARCHAR(500) NOT NULL,
  `body` TEXT NOT NULL,
  `imageUrl` VARCHAR(500) NULL,
  `linkUrl` VARCHAR(500) NULL,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `createdBy` INT UNSIGNED NOT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manual_articles_slug_unique` (`slug`),
  KEY `idx_articles_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `site_content` (`contentKey`, `contentValue`) VALUES
('homeHeroTitle', 'Encontre quem cuida'),
('homeHeroEmphasis', 'do nosso lugar.'),
('homeDescription', 'Um mapa vivo de iniciativas, empresas e grupos que fazem a coleta, a educação ambiental e o descarte correto acontecerem em Macapá.'),
('homeCta', 'Quero hospedar minha iniciativa'),
('homeTrust', 'Cadastros analisados pela equipe'),
('manualIntro', 'Cuidar do ambiente não precisa ser complicado. Este manual reúne atitudes práticas para reduzir resíduos, separar materiais e fortalecer uma cultura de cuidado em Macapá.'),
('manualCard1Title', 'Separe na origem'),
('manualCard1Text', 'Tenha dois recipientes em casa: um para recicláveis secos e outro para orgânicos e rejeitos. Lave embalagens antes de encaminhar.'),
('manualCard2Title', 'Composte o que volta à terra'),
('manualCard2Text', 'Cascas, borra de café e folhas podem virar adubo. A compostagem reduz o volume de lixo e devolve nutrientes ao solo.'),
('manualCard3Title', 'Descarte com responsabilidade'),
('manualCard3Text', 'Pilhas, lâmpadas, eletrônicos e óleo de cozinha precisam de pontos específicos. Nunca despeje óleo no ralo.'),
('manualCard4Title', 'Consuma com intenção'),
('manualCard4Text', 'Antes de comprar, pergunte se precisa. Prefira itens duráveis, reparáveis, retornáveis e com menos embalagem.'),
('manualCalloutTitle', 'O resíduo certo, no lugar certo.'),
('manualCalloutText', 'Quando cada material encontra o destino adequado, toda a cidade ganha: o trabalho de quem coleta é valorizado, os recursos permanecem em circulação e os rios respiram melhor.'),
('footerDescription', 'Conectando cuidado, território e ação ambiental em Macapá.'),
('footerHostDescription', 'Uma presença digital simples para quem transforma o lugar onde vive.'),
('footerCity', 'Feito para Macapá'),
('adminPanelTitle', 'Central de curadoria.'),
('adminPanelIntro', 'Analise cadastros, revise alterações e edite o conteúdo da mapazerolixo.'),
('adminPanelNotice', 'As decisões administrativas ficam registradas no histórico.'),
('adminPanelPrimaryColor', '#173d32'),
('adminPanelAccentColor', '#b7cf73');

INSERT INTO `users` (`openId`, `name`, `email`, `loginMethod`, `role`, `passwordHash`)
VALUES (
  'local-owner',
  'Addam Chagas',
  'addamschagas@gmail.com',
  'password',
  'admin',
  '$2y$10$L0spJYVf0pyzL7BoYDtwseOAlOcLS8qWkALNpGbYTh/bxUoFffMay'
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `role` = 'admin',
  `loginMethod` = 'password',
  `passwordHash` = VALUES(`passwordHash`);

SET FOREIGN_KEY_CHECKS = 1;
