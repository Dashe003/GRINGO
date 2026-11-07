USE gringo_db;
SET NAMES utf8mb4;

ALTER TABLE clothes ADD COLUMN last_washed_at DATETIME NULL AFTER status;
ALTER TABLE calendar_events MODIFY event_type ENUM('planned','worn','cancelled') NOT NULL DEFAULT 'planned';
ALTER TABLE wishlist_items
 ADD COLUMN label_id TINYINT UNSIGNED NULL AFTER name,
 ADD COLUMN photo_path VARCHAR(255) NULL AFTER label_id,
 ADD COLUMN seller VARCHAR(120) NULL AFTER brand,
 ADD COLUMN priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal' AFTER currency,
 MODIFY status ENUM('wanted','pending','purchased','dismissed') NOT NULL DEFAULT 'wanted';
ALTER TABLE trips ADD COLUMN description TEXT NULL AFTER destination;
ALTER TABLE trip_items
 ADD COLUMN quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER clothing_id,
 ADD COLUMN packed TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity;

CREATE TABLE trip_outfits (
 trip_id BIGINT UNSIGNED NOT NULL,
 outfit_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (trip_id,outfit_id),
 CONSTRAINT fk_trip_outfits_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
 CONSTRAINT fk_trip_outfits_outfit FOREIGN KEY (outfit_id) REFERENCES outfits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exchange_rates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 from_currency CHAR(3) NOT NULL,
 to_currency CHAR(3) NOT NULL,
 rate DECIMAL(18,8) NOT NULL,
 effective_date DATE NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_exchange_rate_lookup(user_id,from_currency,to_currency,effective_date),
 CONSTRAINT fk_exchange_rates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO colors (name,hex_code,color_family) VALUES
 ('Noir','#171717','neutre'),('Blanc','#F5F3EE','neutre'),('Gris','#8D8D8A','neutre'),
 ('Beige','#C8B99F','neutre'),('Marron','#795548','neutre'),('Marine','#23324D','bleu'),
 ('Bleu','#4776A8','bleu'),('Vert','#58765B','vert'),('Olive','#70764B','vert'),
 ('Rouge','#A7463D','rouge'),('Bordeaux','#713D4B','rouge'),('Rose','#D28A9B','rose'),
 ('Jaune','#D4B54D','jaune'),('Orange','#C8783C','orange'),('Violet','#78618E','violet')
ON DUPLICATE KEY UPDATE hex_code=VALUES(hex_code),color_family=VALUES(color_family);

INSERT INTO color_compatibilities (color_a_id,color_b_id,compatibility,explanation)
SELECT a.id,b.id,'harmonious','Les tons neutres s’associent facilement et laissent les autres pièces s’exprimer.' FROM colors a JOIN colors b ON a.name IN ('Noir','Blanc','Gris','Beige','Marine') AND b.name IN ('Noir','Blanc','Gris','Beige','Marine') AND a.id<b.id
ON DUPLICATE KEY UPDATE compatibility=VALUES(compatibility),explanation=VALUES(explanation);
INSERT INTO color_compatibilities (color_a_id,color_b_id,compatibility,explanation)
SELECT a.id,b.id,'contrast','Un contraste marqué peut donner du caractère à la tenue; l’équilibre dépend des pièces choisies.' FROM colors a JOIN colors b ON ((a.name='Bleu' AND b.name='Orange') OR (a.name='Rouge' AND b.name='Vert') OR (a.name='Violet' AND b.name='Jaune'))
ON DUPLICATE KEY UPDATE compatibility=VALUES(compatibility),explanation=VALUES(explanation);
