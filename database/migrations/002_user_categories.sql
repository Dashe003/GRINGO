USE gringo_db;
ALTER TABLE categories ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER label_id, ADD KEY idx_categories_owner(user_id,label_id,is_active), ADD CONSTRAINT fk_categories_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE;
