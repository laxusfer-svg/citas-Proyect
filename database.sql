/* =========================================================
   BASE DE DATOS
   SISTEMA DE CITAS MÉDICAS
   ========================================================= */

CREATE DATABASE IF NOT EXISTS sistema
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE sistema;


/* =========================================================
   ELIMINAR TABLAS SI YA EXISTEN
   ========================================================= */

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS citas;
DROP TABLE IF EXISTS doctores;
DROP TABLE IF EXISTS pacientes;
DROP TABLE IF EXISTS usuarios;

SET FOREIGN_KEY_CHECKS = 1;


/* =========================================================
   TABLA: USUARIOS
   =========================================================

   ROLES:

   0 = Paciente
   1 = Doctor
   2 = Administrador

   ========================================================= */

CREATE TABLE usuarios (

    id INT AUTO_INCREMENT PRIMARY KEY,

    usuario VARCHAR(50) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    rol TINYINT NOT NULL,

    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_rol
        CHECK (rol IN (0,1,2))

) ENGINE=InnoDB;


/* =========================================================
   TABLA: PACIENTES
   ========================================================= */

CREATE TABLE pacientes (

    id INT AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT NOT NULL,

    nombres VARCHAR(100) NOT NULL,

    apellidos VARCHAR(100) NOT NULL,

    dni VARCHAR(20) NOT NULL UNIQUE,

    fecha_nacimiento DATE NULL,

    sexo CHAR(1) NULL,

    telefono VARCHAR(20) NULL,

    direccion VARCHAR(200) NULL,

    correo VARCHAR(100) NULL,

    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_paciente_usuario

        FOREIGN KEY (usuario_id)

        REFERENCES usuarios(id)

        ON UPDATE CASCADE

        ON DELETE CASCADE

) ENGINE=InnoDB;


/* =========================================================
   TABLA: DOCTORES
   =========================================================

   Cada doctor tiene su propio horario.

   hora_inicio       = inicio de atención
   hora_fin          = fin de atención

   refrigerio_inicio = inicio del refrigerio
   refrigerio_fin    = fin del refrigerio

   Las citas duran 30 minutos.

   ========================================================= */

CREATE TABLE doctores (

    id INT AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT NOT NULL,

    nombres VARCHAR(100) NOT NULL,

    apellidos VARCHAR(100) NOT NULL,

    cmp VARCHAR(30) NULL,

    especialidad VARCHAR(100) NOT NULL,

    telefono VARCHAR(20) NULL,

    correo VARCHAR(100) NULL,

    hora_inicio TIME NOT NULL DEFAULT '08:00:00',

    hora_fin TIME NOT NULL DEFAULT '17:00:00',

    refrigerio_inicio TIME NULL,

    refrigerio_fin TIME NULL,

    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_doctor_usuario

        FOREIGN KEY (usuario_id)

        REFERENCES usuarios(id)

        ON UPDATE CASCADE

        ON DELETE CASCADE

) ENGINE=InnoDB;


/* =========================================================
   TABLA: CITAS
   ========================================================= */

CREATE TABLE citas (

    id INT AUTO_INCREMENT PRIMARY KEY,

    paciente_id INT NOT NULL,

    doctor_id INT NOT NULL,

    fecha DATE NOT NULL,

    hora TIME NOT NULL,

    motivo VARCHAR(255) NULL,

    estado ENUM(

        'pendiente',

        'confirmada',

        'atendida',

        'cancelada'

    ) NOT NULL DEFAULT 'pendiente',

    turno_activo TINYINT GENERATED ALWAYS AS (
        CASE WHEN estado = 'cancelada' THEN NULL ELSE 1 END
    ) STORED,

    observaciones TEXT NULL,

    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    /* -----------------------------------------------
       RELACIÓN CON PACIENTE
    ----------------------------------------------- */

    CONSTRAINT fk_cita_paciente

        FOREIGN KEY (paciente_id)

        REFERENCES pacientes(id)

        ON UPDATE CASCADE

        ON DELETE RESTRICT,


    /* -----------------------------------------------
       RELACIÓN CON DOCTOR
    ----------------------------------------------- */

    CONSTRAINT fk_cita_doctor

        FOREIGN KEY (doctor_id)

        REFERENCES doctores(id)

        ON UPDATE CASCADE

        ON DELETE RESTRICT,


    /* -----------------------------------------------
       EVITAR DOS CITAS DEL MISMO DOCTOR
       EN LA MISMA FECHA Y HORA
    ----------------------------------------------- */

    CONSTRAINT unique_doctor_fecha_hora_activa

        UNIQUE (doctor_id, fecha, hora, turno_activo)

) ENGINE=InnoDB;


/* =========================================================
   ÍNDICES
   ========================================================= */

CREATE INDEX idx_pacientes_usuario
ON pacientes(usuario_id);

CREATE INDEX idx_doctores_usuario
ON doctores(usuario_id);

CREATE INDEX idx_citas_paciente
ON citas(paciente_id);

CREATE INDEX idx_citas_doctor
ON citas(doctor_id);

CREATE INDEX idx_citas_fecha
ON citas(fecha);

CREATE INDEX idx_citas_estado
ON citas(estado);

CREATE INDEX idx_pacientes_dni
ON pacientes(dni);


/* =========================================================
   USUARIOS DE PRUEBA
   =========================================================

   Password de todos:
   123456

   SHA2-256
   ========================================================= */

INSERT INTO usuarios
(usuario, password, rol)
VALUES

/* ADMINISTRADOR */

(
    'admin',
    SHA2('123456',256),
    2
),

/* PACIENTES */

(
    'juan.perez',
    SHA2('123456',256),
    0
),

(
    'maria.lopez',
    SHA2('123456',256),
    0
),

/* DOCTORES */

(
    'dr.carlos',
    SHA2('123456',256),
    1
),

(
    'dra.ana',
    SHA2('123456',256),
    1
);


/* =========================================================
   PACIENTES DE PRUEBA
   ========================================================= */

INSERT INTO pacientes
(
    usuario_id,
    nombres,
    apellidos,
    dni,
    fecha_nacimiento,
    sexo,
    telefono,
    direccion,
    correo
)

SELECT
    id,
    'Juan',
    'Pérez García',
    '74561238',
    '1995-05-10',
    'M',
    '987654321',
    'Av. Los Olivos 123',
    'juan@gmail.com'

FROM usuarios

WHERE usuario = 'juan.perez';


INSERT INTO pacientes
(
    usuario_id,
    nombres,
    apellidos,
    dni,
    fecha_nacimiento,
    sexo,
    telefono,
    direccion,
    correo
)

SELECT
    id,
    'María',
    'López Torres',
    '70894561',
    '1998-08-22',
    'F',
    '986123456',
    'Jr. Lima 456',
    'maria@gmail.com'

FROM usuarios

WHERE usuario = 'maria.lopez';


/* =========================================================
   DOCTOR CARLOS
   =========================================================

   Horario:

   08:00 AM - 05:00 PM

   Refrigerio:

   01:00 PM - 02:00 PM

   ========================================================= */

INSERT INTO doctores
(
    usuario_id,
    nombres,
    apellidos,
    cmp,
    especialidad,
    telefono,
    correo,
    hora_inicio,
    hora_fin,
    refrigerio_inicio,
    refrigerio_fin
)

SELECT

    id,

    'Carlos',

    'Gómez Rodríguez',

    'CMP-12345',

    'Cardiología',

    '985123456',

    'carlos@clinica.com',

    '08:00:00',

    '17:00:00',

    '13:00:00',

    '14:00:00'

FROM usuarios

WHERE usuario = 'dr.carlos';


/* =========================================================
   DOCTORA ANA
   =========================================================

   Horario:

   01:00 PM - 10:00 PM

   Sin refrigerio configurado.

   ========================================================= */

INSERT INTO doctores
(
    usuario_id,
    nombres,
    apellidos,
    cmp,
    especialidad,
    telefono,
    correo,
    hora_inicio,
    hora_fin,
    refrigerio_inicio,
    refrigerio_fin
)

SELECT

    id,

    'Ana',

    'Ramírez Flores',

    'CMP-67890',

    'Medicina General',

    '984654321',

    'ana@clinica.com',

    '13:00:00',

    '22:00:00',

    NULL,

    NULL

FROM usuarios

WHERE usuario = 'dra.ana';


/* =========================================================
   DATOS DE PRUEBA DE CITAS
   =========================================================

   Estas citas son opcionales.

   Se insertan utilizando subconsultas para NO depender
   de que los IDs sean 1, 2, 3, etc.

   ========================================================= */


/* CITA DE JUAN CON CARLOS */

INSERT INTO citas
(
    paciente_id,
    doctor_id,
    fecha,
    hora,
    motivo,
    estado
)

SELECT

    p.id,

    d.id,

    '2026-10-05',

    '08:00:00',

    'Dolor en el pecho',

    'pendiente'

FROM pacientes p

INNER JOIN usuarios u

    ON p.usuario_id = u.id

CROSS JOIN doctores d

INNER JOIN usuarios ud

    ON d.usuario_id = ud.id

WHERE

    u.usuario = 'juan.perez'

    AND ud.usuario = 'dr.carlos';


/* CITA DE MARÍA CON ANA */

INSERT INTO citas
(
    paciente_id,
    doctor_id,
    fecha,
    hora,
    motivo,
    estado
)

SELECT

    p.id,

    d.id,

    '2026-10-05',

    '14:00:00',

    'Consulta médica general',

    'pendiente'

FROM pacientes p

INNER JOIN usuarios u

    ON p.usuario_id = u.id

CROSS JOIN doctores d

INNER JOIN usuarios ud

    ON d.usuario_id = ud.id

WHERE

    u.usuario = 'maria.lopez'

    AND ud.usuario = 'dra.ana';


/* =========================================================
   CONSULTAS DE VERIFICACIÓN
   ========================================================= */


/* USUARIOS */

SELECT
    id,
    usuario,
    rol,
    fecha_registro
FROM usuarios;


/* PACIENTES */

SELECT
    p.id,
    p.usuario_id,
    p.nombres,
    p.apellidos,
    p.dni,
    p.telefono
FROM pacientes p;


/* DOCTORES */

SELECT
    id,
    usuario_id,
    nombres,
    apellidos,
    especialidad,
    hora_inicio,
    hora_fin,
    refrigerio_inicio,
    refrigerio_fin
FROM doctores;


/* CITAS */

SELECT
    c.id,
    c.fecha,
    c.hora,

    CONCAT(
        p.nombres,
        ' ',
        p.apellidos
    ) AS paciente,

    p.dni,

    CONCAT(
        d.nombres,
        ' ',
        d.apellidos
    ) AS doctor,

    d.especialidad,

    c.motivo,

    c.estado

FROM citas c

INNER JOIN pacientes p
    ON c.paciente_id = p.id

INNER JOIN doctores d
    ON c.doctor_id = d.id

ORDER BY
    c.fecha ASC,
    c.hora ASC;


/* =========================================================
   FIN DEL SCRIPT
   ========================================================= */