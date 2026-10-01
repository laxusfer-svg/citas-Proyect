ALTER TABLE citas
    ADD COLUMN modalidad ENUM('presencial', 'virtual') NOT NULL DEFAULT 'presencial'
    AFTER hora;