CREATE DATABASE IF NOT EXISTS gringo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gringo_db;
SET NAMES utf8mb4;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 username VARCHAR(40) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 avatar_path VARCHAR(255) NULL,
 theme ENUM('dark','light') NOT NULL DEFAULT 'dark',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_preferences (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 currency CHAR(3) NOT NULL DEFAULT 'USD',
 language VARCHAR(8) NOT NULL DEFAULT 'fr',
 units VARCHAR(16) NOT NULL DEFAULT 'metric',
 preferences_json JSON NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
 identity_hash CHAR(64) NOT NULL,
 ip_hash CHAR(64) NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 window_started_at DATETIME NOT NULL,
 PRIMARY KEY(identity_hash,ip_hash),
 KEY idx_login_window(window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clothing_labels (
 id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(40) NOT NULL UNIQUE,
 sort_order TINYINT UNSIGNED NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO clothing_labels (id,name,sort_order) VALUES (1,'HAUT',1),(2,'BAS',2),(3,'CHAUSSURES',3),(4,'COUVRE-CHEF',4),(5,'ACCESSOIRES',5),(6,'EXTÉRIEUR',6);

CREATE TABLE categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 label_id TINYINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 name VARCHAR(80) NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_category_label_name (label_id,name),
 CONSTRAINT fk_categories_label FOREIGN KEY (label_id) REFERENCES clothing_labels(id)
 ,CONSTRAINT fk_categories_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE subcategories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(80) NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_subcategory_name (category_id,name),
 CONSTRAINT fk_subcategories_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO categories (label_id,name) VALUES
 (1,'T-shirt'),(1,'Polo'),(1,'Chemise'),(1,'Débardeur'),(1,'Pull'),(1,'Sweat'),(1,'Cardigan'),
 (2,'Jean'),(2,'Pantalon'),(2,'Chino'),(2,'Short'),(2,'Bermuda'),(2,'Jogging'),(2,'Jupe'),
 (3,'Baskets'),(3,'Mocassins'),(3,'Chaussures de ville'),(3,'Bottines'),(3,'Sandales'),(3,'Escarpins'),
 (4,'Casquette'),(4,'Chapeau'),(4,'Bonnet'),(4,'Béret'),
 (5,'Ceinture'),(5,'Cravate'),(5,'Nœud papillon'),(5,'Foulard'),(5,'Écharpe'),(5,'Bretelles'),(5,'Lunettes'),
 (6,'Veste'),(6,'Blazer'),(6,'Manteau'),(6,'Parka'),(6,'Imperméable'),(6,'Doudoune');

CREATE TABLE colors (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE, hex_code CHAR(7) NULL, color_family VARCHAR(30) NULL);
INSERT INTO colors (name,hex_code,color_family) VALUES ('Noir','#171717','neutre'),('Blanc','#F5F3EE','neutre'),('Gris','#8D8D8A','neutre'),('Beige','#C8B99F','neutre'),('Marron','#795548','neutre'),('Marine','#23324D','bleu'),('Bleu','#4776A8','bleu'),('Vert','#58765B','vert'),('Olive','#70764B','vert'),('Rouge','#A7463D','rouge'),('Bordeaux','#713D4B','rouge'),('Rose','#D28A9B','rose'),('Jaune','#D4B54D','jaune'),('Orange','#C8783C','orange'),('Violet','#78618E','violet');
CREATE TABLE brands (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, name VARCHAR(100) NOT NULL, UNIQUE KEY uq_brand_user_name(user_id,name), KEY idx_brand_name(name), CONSTRAINT fk_brands_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE styles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, name VARCHAR(80) NOT NULL, UNIQUE KEY uq_style_user_name(user_id,name), CONSTRAINT fk_styles_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE occasions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, name VARCHAR(80) NOT NULL, UNIQUE KEY uq_occasion_user_name(user_id,name), CONSTRAINT fk_occasions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clothes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 label_id TINYINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NULL,
 subcategory_id BIGINT UNSIGNED NULL,
 name VARCHAR(160) NOT NULL,
 description TEXT NULL,
 image_path VARCHAR(255) NULL,
 color VARCHAR(50) NULL,
 brand VARCHAR(100) NULL,
 size VARCHAR(30) NULL,
 material VARCHAR(100) NULL,
 purchase_price DECIMAL(12,2) NULL,
 currency CHAR(3) NOT NULL DEFAULT 'USD',
 purchase_date DATE NULL,
 seller VARCHAR(120) NULL,
 season VARCHAR(80) NULL,
 style VARCHAR(100) NULL,
 formality ENUM('casual','smart_casual','formal','black_tie') NULL,
 rating TINYINT UNSIGNED NULL,
 occasion VARCHAR(100) NULL,
 status ENUM('available','laundry','repair','unavailable','archived') NOT NULL DEFAULT 'available',
 last_washed_at DATETIME NULL,
 notes TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_clothes_user_status (user_id,status), KEY idx_clothes_user_label (user_id,label_id), KEY idx_clothes_user_name(user_id,name),
 CONSTRAINT fk_clothes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_clothes_label FOREIGN KEY (label_id) REFERENCES clothing_labels(id),
 CONSTRAINT fk_clothes_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
 CONSTRAINT fk_clothes_subcategory FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE clothing_images (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, clothing_id BIGINT UNSIGNED NOT NULL, image_path VARCHAR(255) NOT NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_clothing_images_owner(user_id,clothing_id), CONSTRAINT fk_clothing_images_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_clothing_images_clothes FOREIGN KEY(clothing_id) REFERENCES clothes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE clothing_colors (clothing_id BIGINT UNSIGNED NOT NULL, color_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(clothing_id,color_id), CONSTRAINT fk_clothing_colors_clothes FOREIGN KEY(clothing_id) REFERENCES clothes(id) ON DELETE CASCADE, CONSTRAINT fk_clothing_colors_color FOREIGN KEY(color_id) REFERENCES colors(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outfits (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL, description TEXT NULL, cover_path VARCHAR(255) NULL, style VARCHAR(100) NULL, occasion VARCHAR(100) NULL, season VARCHAR(80) NULL, notes TEXT NULL, rating TINYINT UNSIGNED NULL, wear_count INT UNSIGNED NOT NULL DEFAULT 0, last_worn_at DATETIME NULL, status ENUM('active','archived') NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY idx_outfits_user_status(user_id,status), CONSTRAINT fk_outfits_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE outfit_items (outfit_id BIGINT UNSIGNED NOT NULL, clothing_id BIGINT UNSIGNED NOT NULL, position SMALLINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(outfit_id,clothing_id), CONSTRAINT fk_outfit_items_outfit FOREIGN KEY(outfit_id) REFERENCES outfits(id) ON DELETE CASCADE, CONSTRAINT fk_outfit_items_clothes FOREIGN KEY(clothing_id) REFERENCES clothes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE outfit_favorites (user_id BIGINT UNSIGNED NOT NULL, outfit_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id,outfit_id), CONSTRAINT fk_outfit_fav_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_outfit_fav_outfit FOREIGN KEY(outfit_id) REFERENCES outfits(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE clothing_favorites (user_id BIGINT UNSIGNED NOT NULL, clothing_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id,clothing_id), CONSTRAINT fk_clothing_fav_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_clothing_fav_item FOREIGN KEY(clothing_id) REFERENCES clothes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE collections (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, description TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_collections_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE collection_items (collection_id BIGINT UNSIGNED NOT NULL, clothing_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(collection_id,clothing_id), CONSTRAINT fk_collection_items_collection FOREIGN KEY(collection_id) REFERENCES collections(id) ON DELETE CASCADE, CONSTRAINT fk_collection_items_clothes FOREIGN KEY(clothing_id) REFERENCES clothes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE outfit_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, outfit_id BIGINT UNSIGNED NOT NULL, worn_at DATETIME NOT NULL, note TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_outfit_history_user_date(user_id,worn_at), CONSTRAINT fk_outfit_history_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_outfit_history_outfit FOREIGN KEY(outfit_id) REFERENCES outfits(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE calendar_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, outfit_id BIGINT UNSIGNED NULL, event_date DATE NOT NULL, event_type ENUM('planned','worn','cancelled') NOT NULL DEFAULT 'planned', note TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_calendar_user_date(user_id,event_date), CONSTRAINT fk_calendar_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_calendar_outfit FOREIGN KEY(outfit_id) REFERENCES outfits(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE laundry_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, clothing_id BIGINT UNSIGNED NOT NULL, started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, completed_at DATETIME NULL, note VARCHAR(255) NULL, KEY idx_laundry_user_open(user_id,completed_at), CONSTRAINT fk_laundry_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_laundry_clothing FOREIGN KEY(clothing_id) REFERENCES clothes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE wishlist_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL, label_id TINYINT UNSIGNED NULL, photo_path VARCHAR(255) NULL, brand VARCHAR(100) NULL, seller VARCHAR(120) NULL, url VARCHAR(500) NULL, estimated_price DECIMAL(12,2) NULL, currency CHAR(3) NOT NULL DEFAULT 'USD', priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal', notes TEXT NULL, status ENUM('wanted','pending','purchased','dismissed') NOT NULL DEFAULT 'wanted', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_wishlist_user_status(user_id,status), CONSTRAINT fk_wishlist_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_wishlist_label FOREIGN KEY(label_id) REFERENCES clothing_labels(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE purchases (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, clothing_id BIGINT UNSIGNED NULL, item_name VARCHAR(160) NOT NULL, amount DECIMAL(12,2) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'USD', purchased_at DATE NOT NULL, seller VARCHAR(120) NULL, notes TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_purchases_user_date(user_id,purchased_at), CONSTRAINT fk_purchases_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_purchases_clothing FOREIGN KEY(clothing_id) REFERENCES clothes(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE budgets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, amount DECIMAL(12,2) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'USD', period_start DATE NOT NULL, period_end DATE NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_budgets_user_period(user_id,period_start,period_end), CONSTRAINT fk_budgets_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE budget_entries (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, budget_id BIGINT UNSIGNED NULL, purchase_id BIGINT UNSIGNED NULL, amount DECIMAL(12,2) NOT NULL, currency CHAR(3) NOT NULL, entry_date DATE NOT NULL, note VARCHAR(255) NULL, KEY idx_budget_entries_user_date(user_id,entry_date), CONSTRAINT fk_budget_entries_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_budget_entries_budget FOREIGN KEY(budget_id) REFERENCES budgets(id) ON DELETE SET NULL, CONSTRAINT fk_budget_entries_purchase FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE exchange_rates (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, from_currency CHAR(3) NOT NULL, to_currency CHAR(3) NOT NULL, rate DECIMAL(18,8) NOT NULL, effective_date DATE NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_exchange_rate_lookup(user_id,from_currency,to_currency,effective_date), CONSTRAINT fk_exchange_rates_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE trips (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, destination VARCHAR(120) NULL, description TEXT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, notes TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_trips_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE trip_items (trip_id BIGINT UNSIGNED NOT NULL, clothing_id BIGINT UNSIGNED NOT NULL, quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1, packed TINYINT(1) NOT NULL DEFAULT 0, PRIMARY KEY(trip_id,clothing_id), CONSTRAINT fk_trip_items_trip FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE CASCADE, CONSTRAINT fk_trip_items_clothes FOREIGN KEY(clothing_id) REFERENCES clothes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE trip_outfits (trip_id BIGINT UNSIGNED NOT NULL, outfit_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(trip_id,outfit_id), CONSTRAINT fk_trip_outfits_trip FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE CASCADE, CONSTRAINT fk_trip_outfits_outfit FOREIGN KEY(outfit_id) REFERENCES outfits(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE notifications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, type VARCHAR(40) NOT NULL, title VARCHAR(160) NOT NULL, body TEXT NULL, related_type VARCHAR(40) NULL, related_id BIGINT UNSIGNED NULL, read_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_notifications_user_read(user_id,read_at,created_at), CONSTRAINT fk_notifications_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE color_compatibilities (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, color_a_id BIGINT UNSIGNED NOT NULL, color_b_id BIGINT UNSIGNED NOT NULL, compatibility ENUM('harmonious','contrast','difficult') NOT NULL, explanation VARCHAR(255) NOT NULL, UNIQUE KEY uq_color_pair(color_a_id,color_b_id), CONSTRAINT fk_compat_color_a FOREIGN KEY(color_a_id) REFERENCES colors(id) ON DELETE CASCADE, CONSTRAINT fk_compat_color_b FOREIGN KEY(color_b_id) REFERENCES colors(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO color_compatibilities(color_a_id,color_b_id,compatibility,explanation) SELECT a.id,b.id,'harmonious','Les tons neutres s’associent facilement; adaptez selon les pièces et vos préférences.' FROM colors a JOIN colors b ON a.name IN ('Noir','Blanc','Gris','Beige','Marine') AND b.name IN ('Noir','Blanc','Gris','Beige','Marine') AND a.id<b.id;
INSERT INTO color_compatibilities(color_a_id,color_b_id,compatibility,explanation) SELECT a.id,b.id,'contrast','Un contraste marqué peut apporter du caractère; l’équilibre dépend de la tenue.' FROM colors a JOIN colors b ON ((a.name='Bleu' AND b.name='Orange') OR (a.name='Rouge' AND b.name='Vert') OR (a.name='Violet' AND b.name='Jaune'));
