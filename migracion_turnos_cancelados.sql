ALTER TABLE citas
    DROP INDEX unique_doctor_fecha_hora,
    ADD COLUMN turno_activo TINYINT GENERATED ALWAYS AS (
        CASE WHEN estado = 'cancelada' THEN NULL ELSE 1 END
    ) STORED,
    ADD UNIQUE INDEX unique_doctor_fecha_hora_activa
        (doctor_id, fecha, hora, turno_activo);